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
use LightweightPlugins\Img\Db\ImageRepository;

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
		ImageRepository::save(
			$attachment_id,
			[
				'status'       => ImageRepository::STATUS_OPTIMIZED,
				'detail'       => sprintf( '-%.1f%%', $percent ),
				'is_transient' => 0,
				'orig_size'    => $original_size,
				'new_size'     => $new_size,
				'level'        => $level,
				'job_id'       => $job_id,
				'keep_exif'    => $keep_exif ? 1 : 0,
				'optimized_at' => time(),
				'claimed_at'   => 0,
			]
		);

		if ( '' !== $backup_rel ) {
			update_post_meta( $attachment_id, BackupStore::META_KEY, $backup_rel );
		}
	}
}
