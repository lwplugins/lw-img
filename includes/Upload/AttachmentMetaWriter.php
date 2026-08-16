<?php
/**
 * Stores per-attachment optimization metadata.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

use LightweightPlugins\Img\Api\OptimizeResult;
use LightweightPlugins\Img\Backup\BackupStore;

/**
 * Stashes optimization stats and writes them to attachment meta.
 */
final class AttachmentMetaWriter {

	private const PENDING_TRANSIENT_PREFIX = 'lw_img_pending_';

	public function __construct() {
		add_action( 'add_attachment', [ $this, 'apply_pending_meta' ] );
	}

	public function stash( string $file_path, OptimizeResult $result, ?string $backup_rel = null, string $level = '', bool $keep_exif = false ): void {
		set_transient(
			self::PENDING_TRANSIENT_PREFIX . md5( $file_path ),
			[
				'original_size' => $result->original_size,
				'new_size'      => $result->new_size,
				'percent'       => $result->percent,
				'job_id'        => $result->job_id,
				'backup'        => (string) $backup_rel,
				'level'         => $level,
				'keep_exif'     => $keep_exif,
			],
			MINUTE_IN_SECONDS * 5
		);
	}

	public function apply_pending_meta( int $attachment_id ): void {
		$file_path = (string) get_attached_file( $attachment_id );
		if ( '' === $file_path ) {
			return;
		}

		$key  = self::PENDING_TRANSIENT_PREFIX . md5( $file_path );
		$data = get_transient( $key );

		if ( ! is_array( $data ) ) {
			return;
		}

		delete_transient( $key );

		self::write_meta(
			$attachment_id,
			(int) $data['original_size'],
			(int) $data['new_size'],
			(float) $data['percent'],
			(string) $data['job_id'],
			(string) ( $data['backup'] ?? '' ),
			(string) ( $data['level'] ?? '' ),
			(bool) ( $data['keep_exif'] ?? false )
		);
	}

	/**
	 * Write the optimization meta directly (used for existing attachments too).
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param int    $original_size Original file size in bytes.
	 * @param int    $new_size      Optimized file size in bytes.
	 * @param float  $percent       Savings percentage.
	 * @param string $job_id        HelloImg job ID.
	 * @param string $backup_rel    Backup-relative path ('' when no backup).
	 * @param string $level         Optimization level used.
	 * @param bool   $keep_exif     Whether EXIF was kept.
	 * @return void
	 */
	public static function write_meta( int $attachment_id, int $original_size, int $new_size, float $percent, string $job_id, string $backup_rel = '', string $level = '', bool $keep_exif = false ): void {
		update_post_meta( $attachment_id, '_lw_img_optimized', 1 );
		update_post_meta( $attachment_id, '_lw_img_original_size', $original_size );
		update_post_meta( $attachment_id, '_lw_img_new_size', $new_size );
		update_post_meta( $attachment_id, '_lw_img_savings_pct', $percent );
		update_post_meta( $attachment_id, '_lw_img_job_id', $job_id );
		update_post_meta( $attachment_id, '_lw_img_level', $level );
		update_post_meta( $attachment_id, '_lw_img_keep_exif', $keep_exif ? 1 : 0 );
		update_post_meta( $attachment_id, '_lw_img_optimized_at', time() );

		if ( '' !== $backup_rel ) {
			update_post_meta( $attachment_id, BackupStore::META_KEY, $backup_rel );
		}
	}
}
