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

wp_clear_scheduled_hook( 'lw_img_backup_cleanup' );
wp_clear_scheduled_hook( 'lw_img_bulk_tick' );

// Backup files under uploads/lw-img-backups/ are intentionally kept: they may
// hold the only remaining copy of a user's original images.
