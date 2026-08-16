<?php
/**
 * AJAX endpoint driving the bulk-optimize loop on the settings page.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

/**
 * Processes a small batch per request; the admin JS keeps calling until
 * nothing is left. Nonce + capability checked on every step.
 */
final class AjaxHandler {

	public const ACTION = 'lw_img_bulk_step';

	public const NONCE_ACTION = 'lw_img_bulk';

	private const BATCH_SIZE = 3;

	/**
	 * Hook the AJAX action.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'handle' ] );
	}

	/**
	 * Process one batch of unoptimized attachments.
	 *
	 * @return void
	 */
	public static function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'lw-img' ) ], 403 );
		}

		$query     = new UnoptimizedQuery();
		$optimizer = new AttachmentOptimizer();
		$processed = [];

		foreach ( $query->ids( self::BATCH_SIZE ) as $attachment_id ) {
			$outcome = $optimizer->optimize( $attachment_id );

			$processed[] = [
				'id'     => $attachment_id,
				'title'  => get_the_title( $attachment_id ),
				'result' => $outcome['result'],
				'detail' => $outcome['detail'],
			];
		}

		wp_send_json_success(
			[
				'processed' => $processed,
				'remaining' => $query->count(),
			]
		);
	}
}
