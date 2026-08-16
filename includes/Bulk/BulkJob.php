<?php
/**
 * Persistent record of the current bulk-optimize run.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

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
	 * @return array<string, int|string>
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
	 * @param string $status Outcome (StatusMeta constant).
	 * @return void
	 */
	public static function record( string $status ): void {
		$job = self::get();

		if ( ( $job['state'] ?? '' ) !== self::STATE_RUNNING ) {
			return;
		}

		if ( isset( $job[ $status ] ) ) {
			$job[ $status ] = (int) $job[ $status ] + 1;
		}

		$job['updated_at'] = time();

		update_option( self::OPTION_NAME, $job, false );
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
	 * Move the run to a terminal state.
	 *
	 * @param string $state STATE_DONE or STATE_CANCELLED.
	 * @return void
	 */
	public static function finish( string $state ): void {
		$job = self::get();

		if ( [] === $job ) {
			return;
		}

		$job['state']       = $state;
		$job['finished_at'] = time();

		update_option( self::OPTION_NAME, $job, false );
	}
}
