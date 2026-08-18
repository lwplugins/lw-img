<?php
/**
 * Stores original uploads before they are replaced by the optimized version.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Maps upload paths into the backup directory and moves files in and out.
 *
 * Backups live in wp-content/uploads/lw-img-backups/, mirroring the
 * relative path of the original upload (e.g. 2026/08/photo.jpg). Only the
 * main file is ever backed up: conversion happens on wp_handle_upload,
 * before any sub-sizes exist, and thumbnails are regenerated on restore.
 */
final class BackupStore {

	public const BACKUP_DIR = 'lw-img-backups';

	public const META_KEY = '_lw_img_backup_path';

	/**
	 * Absolute path of the backup root directory (no trailing slash).
	 *
	 * @return string
	 */
	public function root(): string {
		return wp_get_upload_dir()['basedir'] . '/' . self::BACKUP_DIR;
	}

	/**
	 * Map an absolute upload path to its backup-relative path.
	 *
	 * @param string $file_path Absolute path of the uploaded file.
	 * @return string|null Relative path (e.g. "2026/08/photo.jpg"), or null
	 *                     when the file is not inside the uploads directory.
	 */
	public function relative_for( string $file_path ): ?string {
		$basedir = (string) wp_get_upload_dir()['basedir'];

		if ( '' === $basedir || ! str_starts_with( $file_path, $basedir . '/' ) ) {
			return null;
		}

		$relative = substr( $file_path, strlen( $basedir ) + 1 );

		if ( '' === $relative || str_starts_with( $relative, self::BACKUP_DIR . '/' ) ) {
			return null;
		}

		return $relative;
	}

	/**
	 * Copy a file into the backup directory.
	 *
	 * The source file is left untouched — the caller decides whether (and
	 * when) it is safe to delete or overwrite it.
	 *
	 * @param string $file_path Absolute path of the file to back up.
	 * @return string|null The backup-relative path on success, null on failure.
	 */
	public function store( string $file_path ): ?string {
		$relative = $this->relative_for( $file_path );

		if ( null === $relative || ! is_readable( $file_path ) ) {
			return null;
		}

		$relative = $this->unique_relative( $relative, fn ( string $rel ): bool => $this->exists( $rel ) );
		$target   = $this->resolve( $relative );

		if ( '' === $target || ! wp_mkdir_p( dirname( $target ) ) ) {
			return null;
		}

		$this->ensure_protected();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- copying within the uploads dir; WP_Filesystem adds nothing here.
		if ( ! copy( $file_path, $target ) ) {
			return null;
		}

		return $relative;
	}

	/**
	 * Absolute path for a backup-relative path, contained to the backup root.
	 *
	 * The relative path can originate from postmeta (_lw_img_backup_path),
	 * which the plugin itself only ever writes as a validated value — but any
	 * other actor (importer, wp post meta update, a compromised admin) could
	 * set "../../wp-config.php". Rejecting traversal and off-root symlinks here
	 * turns every backup file operation (exists/delete/restore/compare) into a
	 * no-op on such input, since they all route through resolve().
	 *
	 * @param string $relative Backup-relative path.
	 * @return string Absolute path, or '' when it escapes the backup root.
	 */
	public function resolve( string $relative ): string {
		$relative = ltrim( $relative, '/\\' );

		if ( '' === $relative || str_contains( $relative, '../' ) || str_contains( $relative, '..\\' ) ) {
			return '';
		}

		$root = $this->root();
		$path = $root . '/' . $relative;

		// If the target already exists, it must physically resolve inside the
		// backup root (this also defeats symlinks pointing outside).
		$real      = realpath( $path );
		$real_root = realpath( $root );
		if ( false !== $real && false !== $real_root && ! str_starts_with( $real, $real_root . DIRECTORY_SEPARATOR ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Whether a backup file exists.
	 *
	 * @param string $relative Backup-relative path.
	 * @return bool
	 */
	public function exists( string $relative ): bool {
		return '' !== $relative && file_exists( $this->resolve( $relative ) );
	}

	/**
	 * Drop an index.php and .htaccess into the backup root so the originals
	 * are not directory-listed or executed. Idempotent and cheap.
	 *
	 * Note: .htaccess is Apache-only. On nginx the files remain reachable by
	 * their (guessable) URL — the readme documents this, and hardening the
	 * folder name is planned.
	 *
	 * @return void
	 */
	public function ensure_protected(): void {
		$root = $this->root();

		if ( ! wp_mkdir_p( $root ) ) {
			return;
		}

		$guards = [
			$root . '/index.php' => "<?php\n// Silence is golden.\n",
			$root . '/.htaccess' => "Options -Indexes\n<FilesMatch \"\\.php$\">\nRequire all denied\n</FilesMatch>\n",
		];

		foreach ( $guards as $file => $contents ) {
			if ( ! file_exists( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a static guard file into the uploads dir; WP_Filesystem adds nothing here.
				file_put_contents( $file, $contents );
			}
		}
	}

	/**
	 * Delete a backup file.
	 *
	 * @param string $relative Backup-relative path.
	 * @return void
	 */
	public function delete( string $relative ): void {
		if ( $this->exists( $relative ) ) {
			wp_delete_file( $this->resolve( $relative ) );
		}
	}

	/**
	 * Find a collision-free relative path by appending -1, -2, ... before the extension.
	 *
	 * @param string   $relative Candidate relative path.
	 * @param callable $exists   Callback( string $relative ): bool.
	 * @return string
	 */
	public function unique_relative( string $relative, callable $exists ): string {
		if ( ! $exists( $relative ) ) {
			return $relative;
		}

		$dot       = strrpos( $relative, '.' );
		$stem      = false === $dot ? $relative : substr( $relative, 0, $dot );
		$extension = false === $dot ? '' : substr( $relative, $dot );

		$n = 1;
		do {
			$candidate = $stem . '-' . $n . $extension;
			++$n;
		} while ( $exists( $candidate ) && $n < 1000 );

		return $candidate;
	}
}
