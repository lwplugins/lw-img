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
use WP_Post;

/**
 * Adds a "Restore original" row action for optimized attachments with a backup.
 */
final class RowActions {

	/**
	 * Hook the media row actions filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'media_row_actions', [ self::class, 'add_restore_action' ], 10, 2 );
	}

	/**
	 * Add the restore link when a usable backup exists.
	 *
	 * @param array<string, string> $actions Existing row action links.
	 * @param WP_Post               $post    Attachment post.
	 * @return array<string, string>
	 */
	public static function add_restore_action( array $actions, WP_Post $post ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		if ( ! get_post_meta( $post->ID, '_lw_img_optimized', true ) ) {
			return $actions;
		}

		$relative = (string) get_post_meta( $post->ID, BackupStore::META_KEY, true );
		if ( '' === $relative || ! ( new BackupStore() )->exists( $relative ) ) {
			return $actions;
		}

		$actions['lw_img_restore'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( RestoreHandler::url( $post->ID ) ),
			esc_html__( 'Restore original', 'lw-img' )
		);

		return $actions;
	}
}
