<?php
/**
 * Removes the backup file when its attachment is deleted.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Backup;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Db\ImageRepository;

/**
 * Prevents orphaned backups from accumulating after attachments are deleted.
 */
final class AttachmentDeleteCleanup {

	/**
	 * Hook attachment deletion.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'delete_attachment', [ self::class, 'on_delete_attachment' ] );
	}

	/**
	 * Delete the backup belonging to the deleted attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public static function on_delete_attachment( int $attachment_id ): void {
		$relative = (string) get_post_meta( $attachment_id, BackupStore::META_KEY, true );

		if ( '' !== $relative ) {
			( new BackupStore() )->delete( $relative );
		}

		// Our record lives in its own table now, so unlike postmeta it is not
		// cleaned up by WordPress when the attachment goes away.
		ImageRepository::forget( $attachment_id );
	}
}
