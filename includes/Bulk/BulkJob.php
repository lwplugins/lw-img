<?php
/**
 * Persistent record of the current bulk-optimize run.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

/**
 * One option row holds the run state and counters. The background worker
 * increments counters as it goes, so progress never needs a COUNT query.
 */
final class BulkJob {

	public const OPTION_NAME = 'lw_img_bulk_job';

	public const STATE_RUNNING   = 'running';
	public const STATE_CANCELLED = 'cancelled';
	public const STATE_DONE      = 'done';

	/**
	 * Current job record (empty array when none exists).
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$job = get_option( self::OPTION_NAME, [] );

		return is_array( $job ) ? $job : [];
	}

	/**
	 * Whether a run is in progress.
	 *
	 * @phpstan-impure The stored state changes between calls (cancel, finish).
	 *
	 * @return bool
	 */
	public static function is_running(): bool {
		return self::STATE_RUNNING === ( self::get()['state'] ?? '' );
	}

	/**
	 * Start a new run.
	 *
	 * @param int $total Number of pending attachments at start.
	 * @return void
	 */
	public static function start( int $total ): void {
		delete_option( UnoptimizedQuery::CURSOR_OPTION );
		update_option(
			self::OPTION_NAME,
			[
				'state'       => self::STATE_RUNNING,
				'total'       => $total,
				'optimized'   => 0,
				'skipped'     => 0,
				'failed'      => 0,
				'started_at'  => time(),
				'updated_at'  => time(),
				'finished_at' => 0,
			],
			false
		);
	}

	/**
	 * Record one processed item.
	 *
	 * @param string $status      Outcome (StatusMeta constant).
	 * @param string $current     Label of the item just processed (shown as progress).
	 * @param int    $bytes_in    Original size in bytes (optimized items).
	 * @param int    $bytes_saved Bytes saved (optimized items).
	 * @param string $detail      Human-readable outcome detail for the activity feed.
	 * @return void
	 */
	public static function record( string $status, string $current = '', int $bytes_in = 0, int $bytes_saved = 0, string $detail = '' ): void {
		self::mutate(
			static function ( array $job ) use ( $status, $current, $bytes_in, $bytes_saved, $detail ): ?array {
				if ( ( $job['state'] ?? '' ) !== self::STATE_RUNNING ) {
					return null;
				}

				if ( isset( $job[ $status ] ) ) {
					$job[ $status ] = (int) $job[ $status ] + 1;
				}

				if ( '' !== $current ) {
					$job['current'] = $current;
				}

				$job['bytes_in']    = (int) ( $job['bytes_in'] ?? 0 ) + $bytes_in;
				$job['bytes_saved'] = (int) ( $job['bytes_saved'] ?? 0 ) + $bytes_saved;

				$recent = is_array( $job['recent'] ?? null ) ? $job['recent'] : [];
				array_unshift(
					$recent,
					[
						'ts'     => time(),
						'label'  => $current,
						'result' => $status,
						'detail' => $detail,
					]
				);
				$job['recent'] = array_slice( $recent, 0, 5 );

				$job['updated_at'] = time();

				return $job;
			}
		);
	}

	/**
	 * Locked read-modify-write of the job record.
	 *
	 * The row is re-read from storage under the lock (the in-request copy
	 * may be stale: another worker can have written since this process
	 * last looked), so concurrent writers never drop each other's updates.
	 *
	 * @param callable $change Receives the current record; returns the new one, or null to leave it alone.
	 * @return void
	 */
	private static function mutate( callable $change ): void {
		JobLock::run(
			static function () use ( $change ): void {
				wp_cache_delete( self::OPTION_NAME, 'options' );

				$job = $change( self::get() );

				if ( is_array( $job ) ) {
					update_option( self::OPTION_NAME, $job, false );
				}
			}
		);
	}

	/**
	 * Number of items processed so far in this run.
	 *
	 * @return int
	 */
	public static function processed(): int {
		$job = self::get();

		return (int) ( $job['optimized'] ?? 0 ) + (int) ( $job['skipped'] ?? 0 ) + (int) ( $job['failed'] ?? 0 );
	}

	/**
	 * Record the automatic transient-retry pass: mark it used and roll the
	 * re-queued items out of the failed counter so totals stay consistent.
	 *
	 * @param int $requeued Number of re-queued attachments.
	 * @return void
	 */
	public static function mark_retried( int $requeued ): void {
		self::mutate(
			static function ( array $job ) use ( $requeued ): ?array {
				if ( [] === $job ) {
					return null;
				}

				$job['retried']    = 1;
				$job['failed']     = max( 0, (int) ( $job['failed'] ?? 0 ) - $requeued );
				$job['updated_at'] = time();

				return $job;
			}
		);
	}

	/**
	 * Whether the automatic transient-retry pass already ran.
	 *
	 * @return bool
	 */
	public static function has_retried(): bool {
		return ! empty( self::get()['retried'] );
	}

	/**
	 * Move the run to a terminal state.
	 *
	 * @param string $state STATE_DONE or STATE_CANCELLED.
	 * @return void
	 */
	public static function finish( string $state ): void {
		self::mutate(
			static function ( array $job ) use ( $state ): ?array {
				if ( [] === $job ) {
					return null;
				}

				$job['state']       = $state;
				$job['finished_at'] = time();

				return $job;
			}
		);
	}
}
