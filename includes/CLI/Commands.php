<?php
/**
 * WP-CLI Commands.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\CLI;

use LightweightPlugins\Img\Backup\BackupStore;
use LightweightPlugins\Img\Backup\Restorer;
use LightweightPlugins\Img\Bulk\AttachmentOptimizer;
use LightweightPlugins\Img\Bulk\BackgroundWorker;
use LightweightPlugins\Img\Bulk\BulkJob;
use LightweightPlugins\Img\Bulk\StatusMeta;
use LightweightPlugins\Img\Bulk\UnoptimizedQuery;
use LightweightPlugins\Img\Media\RewriteBuffer;
use LightweightPlugins\Img\Stats\SiteStats;
use WP_CLI;

/**
 * Optimize and restore Media Library images via WP-CLI.
 */
final class Commands {

	/**
	 * Show optimization status counts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-img status
	 */
	public function status(): void {
		$counts = StatusMeta::counts();
		$query  = new UnoptimizedQuery();
		$stats  = SiteStats::get( true );

		$items = [
			[
				'metric' => 'pending',
				'value'  => $query->count(),
			],
			[
				'metric' => 'optimized',
				'value'  => $query->optimized_count(),
			],
			[
				'metric' => 'skipped',
				'value'  => $counts[ StatusMeta::SKIPPED ],
			],
			[
				'metric' => 'failed',
				'value'  => $counts[ StatusMeta::FAILED ],
			],
			[
				'metric' => 'storage saved',
				'value'  => size_format( (int) $stats['saved'] ) . ' (-' . round( (float) $stats['percent'], 1 ) . '%)',
			],
			[
				'metric' => 'backup folder',
				'value'  => size_format( (int) $stats['backup']['bytes'] ) . ' / ' . $stats['backup']['files'] . ' files',
			],
		];

		foreach ( (array) $stats['leftovers'] as $name => $dir ) {
			$items[] = [
				'metric' => $name . ' leftover backups',
				'value'  => size_format( (int) $dir['bytes'] ) . ' / ' . $dir['files'] . ' files',
			];
		}

		foreach ( StatusMeta::skip_reasons() as $reason => $total ) {
			$items[] = [
				'metric' => 'skip: ' . $reason,
				'value'  => $total,
			];
		}

		WP_CLI\Utils\format_items( 'table', $items, [ 'metric', 'value' ] );
	}

	/**
	 * Re-queue skipped and/or failed attachments for the next run.
	 *
	 * ## OPTIONS
	 *
	 * [--failed]
	 * : Re-queue attachments whose last attempt failed.
	 *
	 * [--skipped]
	 * : Re-queue attachments that were skipped (e.g. after changing settings).
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-img requeue --failed
	 *     wp lw-img requeue --failed --skipped
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function requeue( array $args, array $assoc_args ): void {
		if ( ! isset( $assoc_args['failed'] ) && ! isset( $assoc_args['skipped'] ) ) {
			WP_CLI::error( 'Pass --failed and/or --skipped.' );
		}

		$total = 0;
		if ( isset( $assoc_args['failed'] ) ) {
			$total += StatusMeta::requeue_by_status( StatusMeta::FAILED );
		}
		if ( isset( $assoc_args['skipped'] ) ) {
			$total += StatusMeta::requeue_by_status( StatusMeta::SKIPPED );
		}

		WP_CLI::success( sprintf( '%d attachment(s) re-queued.', $total ) );
	}

	/**
	 * Optimize attachments.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Attachment IDs to optimize.
	 *
	 * [--all]
	 * : Optimize every unoptimized image in the Media Library.
	 *
	 * [--limit=<number>]
	 * : With --all, stop after this many images.
	 *
	 * [--dry-run]
	 * : Only list what would be optimized.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-img optimize 123 456
	 *     wp lw-img optimize --all
	 *     wp lw-img optimize --all --limit=50 --dry-run
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function optimize( array $args, array $assoc_args ): void {
		if ( BulkJob::is_running() ) {
			WP_CLI::log( 'A background bulk run is active — this process will cooperate with it (shared queue claims) and its progress shows up on the Bulk tab.' );
		}

		if ( isset( $assoc_args['dry-run'] ) ) {
			$this->dry_run( $args, $assoc_args );
			return;
		}

		$buffer    = new RewriteBuffer();
		$optimizer = new AttachmentOptimizer( null, null, null, null, $buffer );
		$counts    = [
			AttachmentOptimizer::RESULT_OPTIMIZED => 0,
			AttachmentOptimizer::RESULT_SKIPPED   => 0,
			AttachmentOptimizer::RESULT_FAILED    => 0,
		];

		if ( isset( $assoc_args['all'] ) ) {
			$limit = isset( $assoc_args['limit'] ) ? max( 1, absint( $assoc_args['limit'] ) ) : PHP_INT_MAX;
			$this->drain( $optimizer, $limit, $counts );
		} else {
			foreach ( array_map( 'absint', $args ) as $id ) {
				$this->process_one( $optimizer, $id, $counts );
			}
		}

		$buffer->flush();

		WP_CLI::success(
			sprintf(
				'Done. Optimized: %d, skipped: %d, failed: %d.',
				$counts[ AttachmentOptimizer::RESULT_OPTIMIZED ],
				$counts[ AttachmentOptimizer::RESULT_SKIPPED ],
				$counts[ AttachmentOptimizer::RESULT_FAILED ]
			)
		);
	}

	/**
	 * List what would be optimized without touching anything.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	private function dry_run( array $args, array $assoc_args ): void {
		$ids = array_map( 'absint', $args );

		if ( isset( $assoc_args['all'] ) ) {
			$limit = isset( $assoc_args['limit'] ) ? max( 1, absint( $assoc_args['limit'] ) ) : 100000;
			$ids   = ( new UnoptimizedQuery() )->ids( $limit );
		}

		foreach ( $ids as $id ) {
			WP_CLI::log( sprintf( 'Would optimize #%d (%s)', $id, (string) get_attached_file( $id ) ) );
		}
		WP_CLI::success( sprintf( '%d image(s) would be processed.', count( $ids ) ) );
	}

	/**
	 * Claim and process pending images in small batches until the queue is
	 * empty or the limit is reached. Claiming lets several parallel CLI
	 * workers (and the cron worker) drain the same queue safely.
	 *
	 * @param AttachmentOptimizer $optimizer Optimizer wired to a rewrite buffer.
	 * @param int                 $limit     Maximum number of images to process.
	 * @param array<string, int>  $counts    Outcome counters (by reference).
	 */
	private function drain( AttachmentOptimizer $optimizer, int $limit, array &$counts ): void {
		$query     = new UnoptimizedQuery();
		$processed = 0;

		while ( $processed < $limit ) {
			$ids = $query->claim( (int) min( 10, $limit - $processed ) );
			if ( [] === $ids ) {
				break;
			}

			foreach ( $ids as $id ) {
				$this->process_one( $optimizer, $id, $counts );

				++$processed;
				if ( 0 === $processed % 50 ) {
					BackgroundWorker::free_memory();
				}
			}
		}
	}

	/**
	 * Optimize one attachment, log it, and mirror the outcome into an
	 * active background job so the Bulk tab dashboard stays live.
	 *
	 * @param AttachmentOptimizer $optimizer Optimizer instance.
	 * @param int                 $id        Attachment ID.
	 * @param array<string, int>  $counts    Outcome counters (by reference).
	 */
	private function process_one( AttachmentOptimizer $optimizer, int $id, array &$counts ): void {
		$outcome = $optimizer->optimize( $id );
		++$counts[ $outcome['result'] ];
		WP_CLI::log( sprintf( '#%d: %s (%s)', $id, $outcome['result'], $outcome['detail'] ) );

		if ( BulkJob::is_running() ) {
			BulkJob::record(
				$outcome['result'],
				(string) get_the_title( $id ),
				(int) ( $outcome['bytes_in'] ?? 0 ),
				(int) ( $outcome['bytes_saved'] ?? 0 ),
				(string) $outcome['detail']
			);
		}
	}

	/**
	 * Restore attachments from backup.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Attachment IDs to restore.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-img restore 123
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function restore( array $args ): void {
		$restorer = new Restorer( new BackupStore() );
		$restored = 0;

		foreach ( array_map( 'absint', $args ) as $id ) {
			if ( $restorer->restore( $id ) ) {
				++$restored;
				WP_CLI::log( sprintf( '#%d: restored', $id ) );
			} else {
				WP_CLI::warning( sprintf( '#%d: no usable backup', $id ) );
			}
		}

		WP_CLI::success( sprintf( '%d attachment(s) restored.', $restored ) );
	}
}
