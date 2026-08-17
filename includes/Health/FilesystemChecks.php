<?php
/**
 * Uploads, backup directory, and disk space checks.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Health;

use LightweightPlugins\Img\Backup\BackupStore;

/**
 * Conversion swaps files in uploads and copies originals into the backup
 * folder — both must be writable and the disk must have headroom.
 */
final class FilesystemChecks {

	/**
	 * Check rows.
	 *
	 * @return array<int, array{label: string, status: string, message: string}>
	 */
	public static function rows(): array {
		$rows    = [];
		$uploads = wp_get_upload_dir();
		$basedir = (string) $uploads['basedir'];

		$writable = '' === (string) ( $uploads['error'] ?? '' ) && wp_is_writable( $basedir );
		$rows[]   = [
			'label'   => __( 'Uploads directory', 'lw-img' ),
			'status'  => $writable ? 'ok' : 'critical',
			'message' => $writable ? __( 'writable', 'lw-img' ) : __( 'not writable — converted files cannot be saved', 'lw-img' ),
		];

		$backup_dir = ( new BackupStore() )->root();
		$backup_ok  = is_dir( $backup_dir ) ? wp_is_writable( $backup_dir ) : wp_is_writable( dirname( $backup_dir ) );
		$rows[]     = [
			'label'   => __( 'Backup directory', 'lw-img' ),
			'status'  => $backup_ok ? 'ok' : 'warning',
			'message' => $backup_ok ? __( 'writable', 'lw-img' ) : __( 'not writable — originals cannot be backed up before conversion', 'lw-img' ),
		];

		$free = function_exists( 'disk_free_space' ) ? disk_free_space( $basedir ) : false;
		if ( false !== $free ) {
			$status = 'ok';
			if ( $free < 200 * MB_IN_BYTES ) {
				$status = 'critical';
			} elseif ( $free < GB_IN_BYTES ) {
				$status = 'warning';
			}

			$rows[] = [
				'label'   => __( 'Free disk space', 'lw-img' ),
				'status'  => $status,
				'message' => (string) size_format( (int) $free ),
			];
		}

		return $rows;
	}
}
