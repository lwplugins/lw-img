<?php
/**
 * Restores an attachment's original file from backup.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Backup;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Bulk\StatusMeta;
use LightweightPlugins\Img\Media\AttachmentRebuilder;
use LightweightPlugins\Img\Media\UrlPairs;
use LightweightPlugins\Img\Media\UrlRewriter;

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

		$old_url  = (string) wp_get_attachment_url( $attachment_id );
		$old_meta = wp_get_attachment_metadata( $attachment_id );

		( new AttachmentRebuilder() )->replace_main_file( $attachment_id, $target, true );

		( new UrlRewriter() )->rewrite(
			UrlPairs::build(
				$old_url,
				UrlPairs::sizes_map( $old_meta ),
				(string) wp_get_attachment_url( $attachment_id ),
				UrlPairs::sizes_map( wp_get_attachment_metadata( $attachment_id ) )
			)
		);

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
		delete_post_meta( $attachment_id, BackupStore::META_KEY );

		// Dropping the record makes the attachment pending again, so it can
		// be re-optimized.
		StatusMeta::clear( $attachment_id );
	}
}
