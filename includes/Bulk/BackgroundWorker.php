<?php
/**
 * WP-Cron worker that drives a bulk run without an open browser tab.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

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
			return 240;
		}

		return self::BUDGET_SEC;
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
			self::kick( 1 );
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
		$optimizer = new AttachmentOptimizer();
		$drained   = false;

		while ( time() < $deadline ) {
			if ( ! BulkJob::is_running() ) {
				break;
			}

			$ids = $query->ids( 3 );
			if ( [] === $ids ) {
				$drained = true;
				break;
			}

			foreach ( $ids as $attachment_id ) {
				$outcome = $optimizer->optimize( $attachment_id );
				BulkJob::record(
					$outcome['result'],
					(string) get_the_title( $attachment_id ),
					(int) ( $outcome['bytes_in'] ?? 0 ),
					(int) ( $outcome['bytes_saved'] ?? 0 ),
					(string) $outcome['detail']
				);

				if ( time() >= $deadline || ! BulkJob::is_running() ) {
					break;
				}
			}
		}

		delete_transient( self::LOCK );

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
}
