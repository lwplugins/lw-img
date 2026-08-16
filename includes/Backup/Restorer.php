<?php
/**
 * Restores an attachment's original file from backup.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Backup;

use LightweightPlugins\Img\Media\AttachmentRebuilder;

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

		( new AttachmentRebuilder() )->replace_main_file( $attachment_id, $target, true );
		$this->clear_meta( $attachment_id );

		do_action( 'lw_img_restored', $attachment_id, $target );

		return true;
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
