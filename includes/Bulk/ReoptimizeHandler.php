<?php
/**
 * Handles the admin-post action that re-optimizes from backup at another level.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\OptimizeRequest;
use LightweightPlugins\Img\Backup\BackupStore;
use LightweightPlugins\Img\Backup\Restorer;

/**
 * Restores the original from backup, then optimizes it again with the
 * requested level. Requires an existing backup.
 */
final class ReoptimizeHandler {

	public const ACTION = 'lw_img_reoptimize';

	/**
	 * Hook the admin-post action.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
	}

	/**
	 * Nonce-protected re-optimize URL.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $level         Target optimization level.
	 * @return string
	 */
	public static function url( int $attachment_id, string $level ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'     => self::ACTION,
					'attachment' => $attachment_id,
					'level'      => $level,
				],
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $attachment_id
		);
	}

	/**
	 * Handle the request.
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'lw-img' ), '', [ 'response' => 403 ] );
		}

		$attachment_id = isset( $_GET['attachment'] ) ? absint( $_GET['attachment'] ) : 0;

		check_admin_referer( self::ACTION . '_' . $attachment_id );

		$level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( (string) $_GET['level'] ) ) : '';
		if ( ! OptimizeRequest::valid_level( $level ) ) {
			$level = OptimizeRequest::LEVEL_NORMAL;
		}

		$backup_rel = (string) get_post_meta( $attachment_id, BackupStore::META_KEY, true );
		$outcome    = AttachmentOptimizer::RESULT_FAILED;

		if ( '' !== $backup_rel && ( new BackupStore() )->exists( $backup_rel ) && ( new Restorer() )->restore( $attachment_id ) ) {
			$outcome = ( new AttachmentOptimizer( null, null, null, $level ) )->optimize( $attachment_id )['result'];
		}

		wp_safe_redirect(
			add_query_arg(
				'lw_img_opt',
				$outcome,
				(string) get_edit_post_link( $attachment_id, 'url' )
			)
		);
		exit;
	}
}
