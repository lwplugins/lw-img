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

	public function stash( string $file_path, OptimizeResult $result, ?string $backup_rel = null ): void {
		set_transient(
			self::PENDING_TRANSIENT_PREFIX . md5( $file_path ),
			[
				'original_size' => $result->original_size,
				'new_size'      => $result->new_size,
				'percent'       => $result->percent,
				'job_id'        => $result->job_id,
				'backup'        => (string) $backup_rel,
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

		update_post_meta( $attachment_id, '_lw_img_optimized', 1 );
		update_post_meta( $attachment_id, '_lw_img_original_size', (int) $data['original_size'] );
		update_post_meta( $attachment_id, '_lw_img_new_size', (int) $data['new_size'] );
		update_post_meta( $attachment_id, '_lw_img_savings_pct', (float) $data['percent'] );
		update_post_meta( $attachment_id, '_lw_img_job_id', (string) $data['job_id'] );

		$backup = (string) ( $data['backup'] ?? '' );
		if ( '' !== $backup ) {
			update_post_meta( $attachment_id, BackupStore::META_KEY, $backup );
		}
	}
}
