<?php
/**
 * Plugin Activator.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation and deactivation.
 */
final class Activator {

	public static function activate(): void {
		\LightweightPlugins\Img\Db\Schema::install();
		self::set_defaults();
		self::ensure_log_option();
		\LightweightPlugins\Img\Backup\RetentionCleaner::schedule();
		update_option( 'lw_img_version', LW_IMG_VERSION );
	}

	public static function deactivate(): void {
		\LightweightPlugins\Img\Backup\RetentionCleaner::unschedule();
		\LightweightPlugins\Img\Bulk\BackgroundWorker::unschedule();
		wp_unschedule_hook( \LightweightPlugins\Img\Upload\SmartCrop\CropScheduler::HOOK );
		if ( \LightweightPlugins\Img\Bulk\BulkJob::is_running() ) {
			\LightweightPlugins\Img\Bulk\BulkJob::finish( \LightweightPlugins\Img\Bulk\BulkJob::STATE_CANCELLED );
		}
	}

	private static function set_defaults(): void {
		if ( false === get_option( Options::OPTION_NAME ) ) {
			// autoload=false: the options row holds the API key, no need to
			// keep it in memory on every request.
			add_option( Options::OPTION_NAME, Options::get_defaults(), '', false );
		}

		( new \LightweightPlugins\Img\Backup\BackupStore() )->ensure_protected();
	}

	private static function ensure_log_option(): void {
		if ( false === get_option( \LightweightPlugins\Img\Log\EventLog::OPTION_NAME, false ) ) {
			add_option( \LightweightPlugins\Img\Log\EventLog::OPTION_NAME, [], '', false );
		}
	}
}
