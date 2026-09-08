<?php
/**
 * Runs the uninstall according to DataPolicy.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Uninstall;

defined( 'ABSPATH' ) || exit;

/**
 * Called from uninstall.php (WP_UNINSTALL_PLUGIN context only).
 */
final class Uninstaller {

	public static function run(): void {
		self::clear_volatile();

		if ( ! DataPolicy::should_wipe( get_option( 'lw_img_options', [] ) ) ) {
			return;
		}

		self::wipe_persistent();
	}

	private static function clear_volatile(): void {
		foreach ( DataPolicy::VOLATILE_OPTIONS as $option ) {
			delete_option( $option );
		}

		delete_transient( 'lw_img_stats' );
		delete_transient( 'lw_img_pending_count' );
		delete_transient( 'lw_img_redirect_probe' );
		delete_transient( 'lw_img_health_report' );

		wp_clear_scheduled_hook( 'lw_img_backup_cleanup' );
		wp_clear_scheduled_hook( 'lw_img_bulk_tick' );
		wp_unschedule_hook( 'lw_img_smart_crop' );
	}

	private static function wipe_persistent(): void {
		global $wpdb;

		foreach ( DataPolicy::PERSISTENT_OPTIONS as $option ) {
			delete_option( $option );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the plugin's own table; the name comes from the $wpdb prefix, not from input.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'lw_img_images' );

		// Backup files under uploads/lw-img-backups/ are intentionally kept:
		// they may hold the only remaining copy of a user's original images.
	}
}
