<?php
/**
 * Stores original uploads before they are replaced by the optimized version.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Backup;

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

		if ( ! wp_mkdir_p( dirname( $target ) ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- copying within the uploads dir; WP_Filesystem adds nothing here.
		if ( ! copy( $file_path, $target ) ) {
			return null;
		}

		return $relative;
	}

	/**
	 * Absolute path for a backup-relative path.
	 *
	 * @param string $relative Backup-relative path.
	 * @return string
	 */
	public function resolve( string $relative ): string {
		return $this->root() . '/' . $relative;
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
