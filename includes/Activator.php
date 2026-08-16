<?php
/**
 * Plugin Activator.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

/**
 * Handles plugin activation and deactivation.
 */
final class Activator {

	public static function activate(): void {
		self::set_defaults();
		self::ensure_log_option();
		\LightweightPlugins\Img\Backup\RetentionCleaner::schedule();
		update_option( 'lw_img_version', LW_IMG_VERSION );
	}

	public static function deactivate(): void {
		\LightweightPlugins\Img\Backup\RetentionCleaner::unschedule();
	}

	private static function set_defaults(): void {
		if ( false === get_option( Options::OPTION_NAME ) ) {
			add_option( Options::OPTION_NAME, Options::get_defaults() );
		}
	}

	private static function ensure_log_option(): void {
		if ( false === get_option( \LightweightPlugins\Img\Log\EventLog::OPTION_NAME, false ) ) {
			add_option( \LightweightPlugins\Img\Log\EventLog::OPTION_NAME, [], '', false );
		}
	}
}
