<?php
/**
 * Lightweight debug logger.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

/**
 * Writes debug/error lines to the PHP error log when WP_DEBUG_LOG is enabled.
 */
final class Logger {

	public static function debug( string $message, array $context = [] ): void {
		if ( ! Options::get( 'debug_mode' ) ) {
			return;
		}

		self::write( 'DEBUG', $message, $context );
	}

	public static function error( string $message, array $context = [] ): void {
		self::write( 'ERROR', $message, $context );
	}

	private static function write( string $level, string $message, array $context ): void {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$line = sprintf( '[lw-img][%s] %s', $level, $message );

		if ( ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $line );
	}
}
