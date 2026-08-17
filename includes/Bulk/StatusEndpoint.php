<?php
/**
 * Read-only AJAX endpoint the Bulk tab polls for progress.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

/**
 * Returns the job record; never triggers processing and never counts —
 * progress comes from the job's own counters.
 */
final class StatusEndpoint {

	public const ACTION = 'lw_img_bulk_status';

	public const NONCE_ACTION = 'lw_img_bulk';

	/**
	 * Hook the AJAX action.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'handle' ] );
	}

	/**
	 * Send the current job state.
	 *
	 * @return void
	 */
	public static function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'lw-img' ) ], 403 );
		}

		$job        = BulkJob::get();
		$started_at = (int) ( $job['started_at'] ?? 0 );

		wp_send_json_success(
			[
				'state'     => (string) ( $job['state'] ?? '' ),
				'total'     => (int) ( $job['total'] ?? 0 ),
				'optimized' => (int) ( $job['optimized'] ?? 0 ),
				'skipped'   => (int) ( $job['skipped'] ?? 0 ),
				'failed'    => (int) ( $job['failed'] ?? 0 ),
				'processed' => BulkJob::processed(),
				'elapsed'   => $started_at > 0 ? max( 0, time() - $started_at ) : 0,
				'current'   => (string) ( $job['current'] ?? '' ),
			]
		);
	}
}
