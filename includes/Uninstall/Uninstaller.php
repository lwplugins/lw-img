<?php
/**
 * Runs the uninstall according to DataPolicy.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Uninstall;

use LightweightPlugins\Img\Backup\RetentionCleaner;
use LightweightPlugins\Img\Bulk\BackgroundWorker;
use LightweightPlugins\Img\Db\Schema;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Upload\SmartCrop\CropScheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Called from uninstall.php (WP_UNINSTALL_PLUGIN context only).
 *
 * The volatile cleanup below duplicates what uninstall.php already runs
 * inline before the autoloader is available; running it again here is a
 * harmless double-delete and keeps this class usable on its own.
 */
final class Uninstaller {

	public static function run(): void {
		self::clear_volatile();

		if ( ! DataPolicy::should_wipe( get_option( Options::OPTION_NAME, [] ) ) ) {
			return;
		}

		self::wipe_persistent();
	}

	private static function clear_volatile(): void {
		foreach ( DataPolicy::VOLATILE_OPTIONS as $option ) {
			delete_option( $option );
		}

		foreach ( DataPolicy::VOLATILE_TRANSIENTS as $transient ) {
			delete_transient( $transient );
		}

		wp_clear_scheduled_hook( RetentionCleaner::HOOK );
		wp_clear_scheduled_hook( BackgroundWorker::HOOK );
		wp_unschedule_hook( CropScheduler::HOOK );
	}

	private static function wipe_persistent(): void {
		global $wpdb;

		foreach ( DataPolicy::PERSISTENT_OPTIONS as $option ) {
			delete_option( $option );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- the plugin's own table; Schema::table() derives the name from $wpdb->prefix, not from input.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . Schema::table() );

		// Backup files under uploads/lw-img-backups/ are intentionally kept:
		// they may hold the only remaining copy of a user's original images.
	}
}
