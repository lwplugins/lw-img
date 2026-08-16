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
use LightweightPlugins\Img\Bulk\UnoptimizedQuery;
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
		$query = new UnoptimizedQuery();

		$items = [
			[
				'metric' => 'optimized',
				'value'  => $query->optimized_count(),
			],
			[
				'metric' => 'not optimized',
				'value'  => $query->count(),
			],
		];

		WP_CLI\Utils\format_items( 'table', $items, [ 'metric', 'value' ] );
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
		$ids = array_map( 'absint', $args );

		if ( isset( $assoc_args['all'] ) ) {
			$limit = isset( $assoc_args['limit'] ) ? max( 1, absint( $assoc_args['limit'] ) ) : PHP_INT_MAX;
			$ids   = ( new UnoptimizedQuery() )->ids( min( $limit, 100000 ) );
		}

		if ( [] === $ids ) {
			WP_CLI::success( 'Nothing to optimize.' );
			return;
		}

		if ( isset( $assoc_args['dry-run'] ) ) {
			foreach ( $ids as $id ) {
				WP_CLI::log( sprintf( 'Would optimize #%d (%s)', $id, (string) get_attached_file( $id ) ) );
			}
			WP_CLI::success( sprintf( '%d image(s) would be processed.', count( $ids ) ) );
			return;
		}

		$optimizer = new AttachmentOptimizer();
		$counts    = [
			AttachmentOptimizer::RESULT_OPTIMIZED => 0,
			AttachmentOptimizer::RESULT_SKIPPED   => 0,
			AttachmentOptimizer::RESULT_FAILED    => 0,
		];

		foreach ( $ids as $id ) {
			$outcome = $optimizer->optimize( $id );
			++$counts[ $outcome['result'] ];
			WP_CLI::log( sprintf( '#%d: %s (%s)', $id, $outcome['result'], $outcome['detail'] ) );
		}

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
