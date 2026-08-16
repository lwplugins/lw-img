<?php
/**
 * Restores an attachment's original file from backup.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Backup;

/**
 * Moves the backed-up original back into place, removes the optimized
 * files, and regenerates attachment metadata (thumbnails) from the original.
 */
final class Restorer {

	/**
	 * Backup file storage.
	 *
	 * @var BackupStore
	 */
	private BackupStore $store;

	public function __construct( ?BackupStore $store = null ) {
		$this->store = $store ?? new BackupStore();
	}

	/**
	 * Restore the original file for an attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool True on success, false when no usable backup exists.
	 */
	public function restore( int $attachment_id ): bool {
		$relative = (string) get_post_meta( $attachment_id, BackupStore::META_KEY, true );

		if ( '' === $relative || ! $this->store->exists( $relative ) ) {
			return false;
		}

		$basedir = (string) wp_get_upload_dir()['basedir'];
		$current = (string) get_attached_file( $attachment_id );

		$target_rel = $this->store->unique_relative(
			$relative,
			static fn ( string $rel ): bool => file_exists( $basedir . '/' . $rel )
		);
		$target     = $basedir . '/' . $target_rel;

		if ( ! wp_mkdir_p( dirname( $target ) ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- moving within the uploads dir; WP_Filesystem adds nothing here.
		if ( ! rename( $this->store->resolve( $relative ), $target ) ) {
			return false;
		}

		$this->delete_optimized_files( $attachment_id, $current, $target );
		$this->update_attachment( $attachment_id, $target );
		$this->clear_meta( $attachment_id );

		do_action( 'lw_img_restored', $attachment_id, $target );

		return true;
	}

	/**
	 * Delete the optimized main file and its generated sub-sizes.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $current       Absolute path of the optimized main file.
	 * @param string $target        Absolute path of the restored original.
	 * @return void
	 */
	private function delete_optimized_files( int $attachment_id, string $current, string $target ): void {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$dir      = dirname( $current );

		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				$file = (string) ( $size['file'] ?? '' );
				if ( '' !== $file && file_exists( $dir . '/' . $file ) ) {
					wp_delete_file( $dir . '/' . $file );
				}
			}
		}

		if ( '' !== $current && $current !== $target && file_exists( $current ) ) {
			wp_delete_file( $current );
		}
	}

	/**
	 * Point the attachment at the restored file and regenerate metadata.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $target        Absolute path of the restored original.
	 * @return void
	 */
	private function update_attachment( int $attachment_id, string $target ): void {
		update_attached_file( $attachment_id, $target );

		$filetype = wp_check_filetype( $target );
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

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $target ) );
	}

	/**
	 * Remove all plugin meta from the attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	private function clear_meta( int $attachment_id ): void {
		$keys = [
			'_lw_img_optimized',
			'_lw_img_original_size',
			'_lw_img_new_size',
			'_lw_img_savings_pct',
			'_lw_img_job_id',
			BackupStore::META_KEY,
		];

		foreach ( $keys as $key ) {
			delete_post_meta( $attachment_id, $key );
		}
	}
}
