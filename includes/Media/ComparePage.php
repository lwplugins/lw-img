<?php
/**
 * Hidden admin page comparing the backup original with the optimized file.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

use LightweightPlugins\Img\Backup\BackupStore;

/**
 * Side-by-side view; reachable from the attachment "LW Image" box.
 */
final class ComparePage {

	public const SLUG = 'lw-img-compare';

	/**
	 * Register the (menu-less) admin page.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				add_submenu_page( '', __( 'Compare', 'lw-img' ), __( 'Compare', 'lw-img' ), 'manage_options', self::SLUG, [ self::class, 'render' ] );
			}
		);
	}

	/**
	 * URL of the compare page for an attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string
	 */
	public static function url( int $attachment_id ): string {
		return add_query_arg(
			[
				'page'       => self::SLUG,
				'attachment' => $attachment_id,
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Render the comparison.
	 *
	 * @return void
	 */
	public static function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view; capability enforced by the page registration.
		$attachment_id = isset( $_GET['attachment'] ) ? absint( $_GET['attachment'] ) : 0;

		$backup_rel = (string) get_post_meta( $attachment_id, BackupStore::META_KEY, true );
		$store      = new BackupStore();

		echo '<div class="wrap"><h1>' . esc_html__( 'Compare — original vs. optimized', 'lw-img' ) . '</h1>';

		if ( 0 === $attachment_id || '' === $backup_rel || ! $store->exists( $backup_rel ) ) {
			echo '<p>' . esc_html__( 'No backup available for this attachment.', 'lw-img' ) . '</p></div>';
			return;
		}

		$backup_url  = wp_get_upload_dir()['baseurl'] . '/' . BackupStore::BACKUP_DIR . '/' . $backup_rel;
		$current_url = (string) wp_get_attachment_url( $attachment_id );

		$backup_size  = (string) size_format( (int) filesize( $store->resolve( $backup_rel ) ) );
		$current_file = (string) get_attached_file( $attachment_id );
		$current_size = file_exists( $current_file ) ? (string) size_format( (int) filesize( $current_file ) ) : '';

		echo '<div style="display:flex;gap:20px;flex-wrap:wrap;">';
		self::render_panel( __( 'Original (backup)', 'lw-img' ), $backup_url, $backup_size );
		self::render_panel( __( 'Optimized', 'lw-img' ), $current_url, $current_size );
		echo '</div>';

		echo '<p><a class="button" href="' . esc_url( (string) get_edit_post_link( $attachment_id ) ) . '">&larr; ' . esc_html__( 'Back to attachment', 'lw-img' ) . '</a></p></div>';
	}

	/**
	 * One side of the comparison.
	 *
	 * @param string $title Panel title.
	 * @param string $url   Image URL.
	 * @param string $size  Human-readable file size.
	 * @return void
	 */
	private static function render_panel( string $title, string $url, string $size ): void {
		echo '<figure style="margin:0;max-width:46%;">';
		echo '<figcaption style="margin-bottom:8px;"><strong>' . esc_html( $title ) . '</strong> <span class="description">' . esc_html( $size ) . '</span></figcaption>';
		echo '<img src="' . esc_url( $url ) . '" style="max-width:100%;height:auto;border:1px solid #ccd0d4;" alt="" />';
		echo '</figure>';
	}
}
