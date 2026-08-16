<?php
/**
 * Media Library list row actions.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

use LightweightPlugins\Img\Backup\BackupStore;
use LightweightPlugins\Img\Backup\RestoreHandler;
use LightweightPlugins\Img\Bulk\OptimizeHandler;
use LightweightPlugins\Img\Options;
use WP_Post;

/**
 * Adds "Restore original" / "Optimize now" row actions for attachments.
 */
final class RowActions {

	/**
	 * Hook the media row actions filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'media_row_actions', [ self::class, 'add_actions' ], 10, 2 );
	}

	/**
	 * Add the restore or optimize link, depending on the attachment state.
	 *
	 * @param array<string, string> $actions Existing row action links.
	 * @param WP_Post               $post    Attachment post.
	 * @return array<string, string>
	 */
	public static function add_actions( array $actions, WP_Post $post ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		if ( get_post_meta( $post->ID, '_lw_img_optimized', true ) ) {
			return self::add_restore_action( $actions, $post->ID );
		}

		if ( in_array( (string) $post->post_mime_type, (array) Options::get( 'mime_types' ), true ) ) {
			$actions['lw_img_optimize'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( OptimizeHandler::url( $post->ID ) ),
				esc_html__( 'Optimize now', 'lw-img' )
			);
		}

		return $actions;
	}

	/**
	 * Add the restore link when a usable backup exists.
	 *
	 * @param array<string, string> $actions       Existing row action links.
	 * @param int                   $attachment_id Attachment post ID.
	 * @return array<string, string>
	 */
	private static function add_restore_action( array $actions, int $attachment_id ): array {
		$relative = (string) get_post_meta( $attachment_id, BackupStore::META_KEY, true );
		if ( '' === $relative || ! ( new BackupStore() )->exists( $relative ) ) {
			return $actions;
		}

		$actions['lw_img_restore'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( RestoreHandler::url( $attachment_id ) ),
			esc_html__( 'Restore original', 'lw-img' )
		);

		return $actions;
	}
}
