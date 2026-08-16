<?php
/**
 * Handles the admin-post action that optimizes a single attachment.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

/**
 * Admin-post endpoint (nonce + capability checked) and its result notice.
 */
final class OptimizeHandler {

	public const ACTION = 'lw_img_optimize';

	/**
	 * Hook the admin-post action and the result notice.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
		add_action( 'admin_notices', [ self::class, 'maybe_render_notice' ] );
	}

	/**
	 * Nonce-protected optimize URL for an attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string
	 */
	public static function url( int $attachment_id ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'     => self::ACTION,
					'attachment' => $attachment_id,
				],
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $attachment_id
		);
	}

	/**
	 * Handle the optimize request.
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'lw-img' ), '', [ 'response' => 403 ] );
		}

		$attachment_id = isset( $_GET['attachment'] ) ? absint( $_GET['attachment'] ) : 0;

		check_admin_referer( self::ACTION . '_' . $attachment_id );

		$outcome = ( new AttachmentOptimizer() )->optimize( $attachment_id );

		wp_safe_redirect(
			add_query_arg( 'lw_img_opt', $outcome['result'], admin_url( 'upload.php' ) )
		);
		exit;
	}

	/**
	 * Render the optimize result notice on the Media screen.
	 *
	 * @return void
	 */
	public static function maybe_render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$result = isset( $_GET['lw_img_opt'] ) ? sanitize_key( wp_unslash( (string) $_GET['lw_img_opt'] ) ) : '';

		if ( '' === $result ) {
			return;
		}

		$messages = [
			AttachmentOptimizer::RESULT_OPTIMIZED => [ 'success', __( 'Image optimized and converted to WebP.', 'lw-img' ) ],
			AttachmentOptimizer::RESULT_SKIPPED   => [ 'warning', __( 'Image was skipped — see the LW Img log for the reason.', 'lw-img' ) ],
			AttachmentOptimizer::RESULT_FAILED    => [ 'error', __( 'Optimization failed — see the LW Img log for details.', 'lw-img' ) ],
		];

		if ( ! isset( $messages[ $result ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $messages[ $result ][0] ),
			esc_html( $messages[ $result ][1] )
		);
	}
}
