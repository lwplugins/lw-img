<?php
/**
 * Uninstall handler.
 *
 * Runs only when the plugin is deleted from the Plugins screen. By default
 * the settings, API key, event log and per-image statistics are KEPT so a
 * delete-and-reinstall loses nothing; they are removed only when the site
 * owner chose "delete all data" in the deactivation dialog or on the Backup
 * tab. Backup originals under uploads/lw-img-backups/ are never touched.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Volatile, always-safe cleanup (rebuilt at runtime) runs inline here, with
 * literal option/transient/hook names instead of the plugin's own class
 * constants: this file executes without the Composer autoloader, so it must
 * not depend on it for something this small. It must run whether or not
 * vendor/autoload.php exists.
 */
delete_option( 'lw_img_bulk_job' );
delete_option( 'lw_img_bulk_cursor' );
delete_option( 'lw_img_leftovers' );

delete_transient( 'lw_img_stats' );
delete_transient( 'lw_img_pending_count' );
delete_transient( 'lw_img_redirect_probe' );
delete_transient( 'lw_img_health_report' );

wp_clear_scheduled_hook( 'lw_img_backup_cleanup' );
wp_clear_scheduled_hook( 'lw_img_bulk_tick' );
wp_unschedule_hook( 'lw_img_smart_crop' );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';

	LightweightPlugins\Img\Uninstall\Uninstaller::run();
} else {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- no autoloader means no logger; this is the only way to surface why persistent data was kept.
	error_log( 'lw-img uninstall: vendor/autoload.php missing — settings and statistics were kept (nothing else could be removed).' );
}
