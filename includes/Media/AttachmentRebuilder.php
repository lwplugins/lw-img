<?php
/**
 * Re-points an attachment at a new main file and rebuilds its metadata.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Shared by restore (webp -> original) and bulk optimize (original -> webp):
 * deletes the current sub-size files, optionally the current main file,
 * updates the attached file and mime type, and regenerates thumbnails.
 */
final class AttachmentRebuilder {

	/**
	 * Replace the attachment's main file and regenerate metadata.
	 *
	 * @param int    $attachment_id   Attachment post ID.
	 * @param string $new_main        Absolute path of the new main file (must exist).
	 * @param bool   $delete_old_main Whether to delete the current main file from disk.
	 * @return void
	 */
	public function replace_main_file( int $attachment_id, string $new_main, bool $delete_old_main ): void {
		$old_main = (string) get_attached_file( $attachment_id );

		$this->delete_current_sub_sizes( $attachment_id, $old_main );

		if ( $delete_old_main && '' !== $old_main && $old_main !== $new_main && file_exists( $old_main ) ) {
			wp_delete_file( $old_main );
		}

		update_attached_file( $attachment_id, $new_main );

		$filetype = wp_check_filetype( $new_main );
		$mime     = is_string( $filetype['type'] ) ? $filetype['type'] : '';
		if ( '' !== $mime ) {
			wp_update_post(
				[
					'ID'             => $attachment_id,
					'post_mime_type' => $mime,
				]
			);
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $new_main ) );
	}

	/**
	 * Delete the sub-size files recorded in the current attachment metadata.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $old_main      Absolute path of the current main file.
	 * @return void
	 */
	private function delete_current_sub_sizes( int $attachment_id, string $old_main ): void {
		if ( '' === $old_main ) {
			return;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) ) {
			return;
		}

		$dir = dirname( $old_main );

		foreach ( $metadata['sizes'] as $size ) {
			$file = (string) ( $size['file'] ?? '' );
			if ( '' !== $file && file_exists( $dir . '/' . $file ) ) {
				wp_delete_file( $dir . '/' . $file );
			}
		}
	}
}
