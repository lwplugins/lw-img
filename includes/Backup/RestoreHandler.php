<?php
/**
 * Handles the admin-post action that restores an original image.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Admin-post endpoint (nonce + capability checked) and its result notice.
 */
final class RestoreHandler {

	public const ACTION = 'lw_img_restore';

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
	 * Nonce-protected restore URL for an attachment.
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
	 * Handle the restore request.
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'lw-img' ), '', [ 'response' => 403 ] );
		}

		$attachment_id = isset( $_GET['attachment'] ) ? absint( $_GET['attachment'] ) : 0;

		check_admin_referer( self::ACTION . '_' . $attachment_id );

		$restored = ( new Restorer() )->restore( $attachment_id );

		wp_safe_redirect(
			add_query_arg(
				$restored ? 'lw_img_restored' : 'lw_img_restore_failed',
				'1',
				admin_url( 'upload.php' )
			)
		);
		exit;
	}

	/**
	 * Render the restore result notice on the Media screen.
	 *
	 * @return void
	 */
	public static function maybe_render_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only flags set by our own redirect.
		if ( isset( $_GET['lw_img_restored'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Original image restored. Thumbnails were regenerated.', 'lw-img' )
			);
		}

		if ( isset( $_GET['lw_img_restore_failed'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( 'Could not restore the original image — the backup file is missing.', 'lw-img' )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
