<?php
/**
 * Deletes backup files older than the configured retention period.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Backup;

defined( 'ABSPATH' ) || exit;

use FilesystemIterator;
use LightweightPlugins\Img\Options;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Daily WP-Cron task; a retention of 0 days disables cleanup entirely.
 * A restore link disappears naturally once its backup file is deleted.
 */
final class RetentionCleaner {

	public const HOOK = 'lw_img_backup_cleanup';

	/**
	 * Hook the cron callback and make sure the event stays scheduled.
	 *
	 * Scheduling is ensured on init (not only on activation) because plugin
	 * updates do not fire the activation hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
		add_action( 'init', [ self::class, 'schedule' ] );
	}

	/**
	 * Schedule the daily event (on plugin activation).
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Remove the scheduled event (on deactivation/uninstall).
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Delete expired backup files.
	 *
	 * @return void
	 */
	public static function run(): void {
		$days = (int) Options::get( 'backup_retention_days' );
		if ( $days <= 0 ) {
			return;
		}

		$root = ( new BackupStore() )->root();
		if ( ! is_dir( $root ) ) {
			return;
		}

		$expired = self::filter_expired( self::list_files( $root ), time(), $days );

		foreach ( $expired as $path ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Select the paths whose modification time is older than the retention window.
	 *
	 * @param array<string, int> $mtimes_by_path Map of absolute path => mtime.
	 * @param int                $now            Current Unix timestamp.
	 * @param int                $days           Retention in days; <= 0 keeps everything.
	 * @return array<int, string> Absolute paths to delete.
	 */
	public static function filter_expired( array $mtimes_by_path, int $now, int $days ): array {
		if ( $days <= 0 ) {
			return [];
		}

		$cutoff = $now - ( $days * DAY_IN_SECONDS );

		return array_keys(
			array_filter(
				$mtimes_by_path,
				static fn ( int $mtime ): bool => $mtime < $cutoff
			)
		);
	}

	/**
	 * Walk the backup directory and collect file modification times.
	 *
	 * @param string $root Backup root directory.
	 * @return array<string, int> Map of absolute path => mtime.
	 */
	private static function list_files( string $root ): array {
		$files    = [];
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$files[ $file->getPathname() ] = (int) $file->getMTime();
			}
		}

		return $files;
	}
}
