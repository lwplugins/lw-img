<?php
/**
 * Uninstall handler.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'lw_img_options' );
delete_option( 'lw_img_version' );
delete_option( 'lw_img_log' );
delete_option( 'lw_img_bulk_job' );
delete_option( 'lw_img_competitor_notice_dismissed' );
delete_option( 'lw_img_leftovers' );
delete_option( 'lw_img_db_version' );
delete_option( 'lw_img_bulk_cursor' );
delete_transient( 'lw_img_stats' );
delete_transient( 'lw_img_pending_count' );

wp_clear_scheduled_hook( 'lw_img_backup_cleanup' );
wp_clear_scheduled_hook( 'lw_img_bulk_tick' );
wp_unschedule_hook( 'lw_img_smart_crop' );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the plugin's own table; the name comes from the $wpdb prefix, not from input.
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'lw_img_images' );

// Backup files under uploads/lw-img-backups/ are intentionally kept: they may
// hold the only remaining copy of a user's original images.
