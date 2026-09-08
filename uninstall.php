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

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

LightweightPlugins\Img\Uninstall\Uninstaller::run();
