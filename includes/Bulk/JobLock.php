<?php
/**
 * Advisory lock serializing writes to the bulk job record.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Db\LockName;

/**
 * Wraps a read-modify-write of the job record in a MySQL advisory lock.
 *
 * Cron ticks, poll assists and parallel WP-CLI workers all update the same
 * option row; without a lock two of them can read the same counters and the
 * later write drops the earlier increment. Like the queue claim lock, this
 * degrades to unlocked behavior when the database refuses the lock (the
 * work still happens — a lost counter beats a stalled run).
 */
final class JobLock {

	/**
	 * Lock purpose; LockName adds the per-site suffix.
	 */
	private const NAME = 'lw_img_job';

	/**
	 * Seconds to wait for the lock. Writes are tiny, so a holder releases it
	 * within milliseconds; the wait only bounds a pathological case.
	 */
	private const WAIT = 5;

	/**
	 * Run a callback while holding the lock.
	 *
	 * @param callable $callback Work to do under the lock.
	 * @return mixed The callback's return value.
	 */
	public static function run( callable $callback ): mixed {
		global $wpdb;

		$name = LockName::for_site( self::NAME );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock; no data is read.
		$locked = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, self::WAIT ) );

		try {
			return $callback();
		} finally {
			if ( $locked ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock release; no data is read.
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
			}
		}
	}
}
