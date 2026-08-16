<?php
/**
 * Options management.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

/**
 * Reads and writes the plugin's options with an in-request cache.
 */
final class Options {

	public const OPTION_NAME = 'lw_img_options';

	/**
	 * In-request options cache.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $options = null;

	public static function get_defaults(): array {
		return DefaultOptions::all();
	}

	public static function get_all(): array {
		if ( null === self::$options ) {
			$saved         = get_option( self::OPTION_NAME, [] );
			self::$options = wp_parse_args( $saved, self::get_defaults() );
		}

		return self::$options;
	}

	public static function get( string $key, mixed $default = null ): mixed {
		$options = self::get_all();

		if ( array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		return $default ?? ( self::get_defaults()[ $key ] ?? null );
	}

	public static function set( string $key, mixed $value ): bool {
		$options         = self::get_all();
		$options[ $key ] = $value;

		return self::save( $options );
	}

	public static function save( array $options ): bool {
		self::$options = $options;
		return update_option( self::OPTION_NAME, $options );
	}

	public static function clear_cache(): void {
		self::$options = null;
	}
}
