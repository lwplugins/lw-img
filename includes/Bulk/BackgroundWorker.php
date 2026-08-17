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
	 * Hook the cron callback.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::HOOK, [ self::class, 'tick' ] );
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

		set_transient( self::LOCK, 1, 2 * MINUTE_IN_SECONDS );

		$deadline  = time() + self::BUDGET_SEC;
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
				BulkJob::record( $outcome['result'] );

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
					return;
				}
			}

			BulkJob::finish( BulkJob::STATE_DONE );
			return;
		}

		if ( BulkJob::is_running() ) {
			self::kick( 1 );
		}
	}
}
