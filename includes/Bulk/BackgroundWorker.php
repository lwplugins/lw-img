<?php
/**
 * WP-Cron worker that drives a bulk run without an open browser tab.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

use LightweightPlugins\Img\Media\RewriteBuffer;

/**
 * Processes pending attachments in time-budgeted ticks and reschedules
 * itself until nothing is left or the run is cancelled. A transient lock
 * keeps ticks from overlapping. Like all WP-Cron work, ticks only fire
 * while the site gets requests — WP-CLI is the guaranteed path for very
 * large libraries.
 */
final class BackgroundWorker {

	public const HOOK = 'lw_img_bulk_tick';

	private const LOCK       = 'lw_img_bulk_lock';
	private const BUDGET_SEC = 15;

	/**
	 * Hook the cron callback and the lost-event self-heal.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::HOOK, [ self::class, 'tick' ] );
		add_action( 'init', [ self::class, 'ensure_scheduled' ] );
	}

	/**
	 * Re-schedule a lost tick while a run is active (e.g. after a killed
	 * process or on hosts where cron events occasionally vanish).
	 *
	 * @return void
	 */
	public static function ensure_scheduled(): void {
		if ( BulkJob::is_running() && false === wp_next_scheduled( self::HOOK ) && false === get_transient( self::LOCK ) ) {
			wp_schedule_single_event( time(), self::HOOK );
		}
	}

	/**
	 * Per-tick time budget.
	 *
	 * Under WP-CLI (the typical DISABLE_WP_CRON system-cron runner, e.g.
	 * `wp cron event run --due-now` every 5 minutes) there is no PHP time
	 * limit and the next chance to run may be minutes away — so work much
	 * longer per tick. Web-spawned cron keeps the short budget.
	 *
	 * @return int Seconds.
	 */
	private static function budget(): int {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 1200;
		}

		return self::BUDGET_SEC;
	}

	/**
	 * Keep long CLI ticks from accumulating an unbounded in-process object
	 * cache. A full flush is only safe against the in-memory array cache —
	 * with Redis/Memcached it would wipe the whole site's cache, so external
	 * object caches are left alone.
	 *
	 * @return void
	 */
	public static function free_memory(): void {
		if ( function_exists( 'wp_using_ext_object_cache' ) && ! wp_using_ext_object_cache() ) {
			wp_cache_flush();
		}
	}

	/**
	 * Schedule the next tick and try to fire cron immediately.
	 *
	 * @param int $delay Seconds from now.
	 * @return void
	 */
	public static function kick( int $delay = 0 ): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + $delay, self::HOOK );
		}

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Remove any scheduled tick.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Process pending attachments for up to the time budget, then reschedule.
	 *
	 * @return void
	 */
	public static function tick(): void {
		if ( ! BulkJob::is_running() ) {
			return;
		}

		if ( false !== get_transient( self::LOCK ) ) {
			self::kick( 30 );
			return;
		}

		if ( self::run_for( self::budget() ) ) {
			return;
		}

		if ( BulkJob::is_running() ) {
			self::kick( Throttle::tick_gap() );
		}
	}

	/**
	 * Short inline processing burst driven by the status poll.
	 *
	 * Keeps a run moving while the Bulk tab is open even on hosts whose
	 * cron loopback request fails — without it, a dropped loopback would
	 * stall the chain until an ordinary page view respawns cron.
	 *
	 * @return void
	 */
	public static function assist(): void {
		$job     = BulkJob::get();
		$stalled = time() - (int) ( $job['updated_at'] ?? 0 ) > 15;

		if ( ! BulkJob::is_running() || ! $stalled || false !== get_transient( self::LOCK ) ) {
			return;
		}

		self::run_for( 8 );
	}

	/**
	 * Take the lock and process items until the budget runs out.
	 *
	 * @param int $budget Seconds to work for.
	 * @return bool True when the run reached a terminal state (drained/finished).
	 */
	private static function run_for( int $budget ): bool {
		set_transient( self::LOCK, 1, $budget + MINUTE_IN_SECONDS );

		$deadline  = time() + $budget;
		$query     = new UnoptimizedQuery();
		$buffer    = new RewriteBuffer();
		$optimizer = new AttachmentOptimizer( null, null, null, null, $buffer );
		$drained   = false;
		$halted    = false;
		$processed = 0;

		while ( time() < $deadline && BulkJob::is_running() ) {
			Throttle::wait_for_headroom( $deadline );
			if ( time() >= $deadline ) {
				break;
			}

			$ids = $query->claim( Throttle::batch_size() );
			if ( [] === $ids ) {
				$drained = true;
				break;
			}

			$state = self::process_batch( $ids, $optimizer, $deadline, $processed );
			if ( 'halt' === $state ) {
				$halted = true;
				break;
			}
			if ( 'stop' === $state ) {
				break;
			}
		}

		$buffer->flush();
		delete_transient( self::LOCK );

		if ( $halted ) {
			// API quota exhausted: stop the whole run — nothing else will
			// succeed and the images themselves stay pending.
			BulkJob::finish( BulkJob::STATE_CANCELLED );
			self::unschedule();
			return true;
		}

		if ( $drained ) {
			// One automatic second pass for transient failures (timeouts,
			// network errors) before declaring the run finished.
			if ( ! BulkJob::has_retried() ) {
				$requeued = StatusMeta::requeue_transient();
				if ( $requeued > 0 ) {
					BulkJob::mark_retried( $requeued );
					self::kick( 5 );
					return true;
				}
			}

			BulkJob::finish( BulkJob::STATE_DONE );
			return true;
		}

		return false;
	}

	/**
	 * Process one claimed batch with per-image pacing.
	 *
	 * @param array<int, int>     $ids       Claimed attachment IDs.
	 * @param AttachmentOptimizer $optimizer Optimizer wired to the rewrite buffer.
	 * @param int                 $deadline  Unix timestamp ending the budget.
	 * @param int                 $processed Running item counter (by reference).
	 * @return string 'ok' (batch done), 'stop' (budget/cancel), or 'halt' (quota).
	 */
	private static function process_batch( array $ids, AttachmentOptimizer $optimizer, int $deadline, int &$processed ): string {
		foreach ( $ids as $attachment_id ) {
			$outcome = $optimizer->optimize( $attachment_id );

			if ( ! empty( $outcome['halt'] ) ) {
				return 'halt';
			}

			BulkJob::record(
				$outcome['result'],
				(string) get_the_title( $attachment_id ),
				(int) ( $outcome['bytes_in'] ?? 0 ),
				(int) ( $outcome['bytes_saved'] ?? 0 ),
				(string) $outcome['detail']
			);

			++$processed;
			if ( 0 === $processed % 50 ) {
				self::free_memory();
			}

			if ( time() >= $deadline || ! BulkJob::is_running() ) {
				return 'stop';
			}

			Throttle::pause();
		}

		return 'ok';
	}
}
