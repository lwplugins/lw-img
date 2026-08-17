<?php
/**
 * Settings sanitiser.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin;

use LightweightPlugins\Img\Options;

/**
 * Sanitizes submitted settings against the known defaults.
 */
final class SettingsSanitizer {

	/**
	 * Server-side clamps for numeric options (mirrors the field min/max, but
	 * enforced here so a hand-crafted POST cannot set out-of-range values).
	 *
	 * @var array<string, array{0: int, 1: int}>
	 */
	private const CLAMPS = [
		'request_timeout'       => [ 5, 120 ],
		'max_filesize_mb'       => [ 1, 10 ],
		'min_filesize_kb'       => [ 0, 10240 ],
		'max_width'             => [ 0, 10000 ],
		'max_height'            => [ 0, 10000 ],
		'backup_retention_days' => [ 0, 3650 ],
	];

	/**
	 * Sanitize submitted settings against the known defaults.
	 *
	 * @param mixed $input Submitted option value (array from the settings form).
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $input ): array {
		$input     = is_array( $input ) ? $input : [];
		$defaults  = Options::get_defaults();
		$current   = Options::get_all();
		$sanitized = [];

		foreach ( $defaults as $key => $default ) {
			$fallback          = $current[ $key ] ?? $default;
			$sanitized[ $key ] = self::sanitize_value( $key, $default, $fallback, $input[ $key ] ?? null );
		}

		return $sanitized;
	}

	private static function sanitize_value( string $key, mixed $default, mixed $fallback, mixed $value ): mixed {
		if ( 'exclusion_patterns' === $key ) {
			return self::sanitize_patterns( $value );
		}

		if ( is_bool( $default ) ) {
			return ! empty( $value );
		}

		if ( is_int( $default ) ) {
			$number = null === $value ? (int) $fallback : absint( $value );

			if ( isset( self::CLAMPS[ $key ] ) ) {
				$number = max( self::CLAMPS[ $key ][0], min( self::CLAMPS[ $key ][1], $number ) );
			}

			return $number;
		}

		if ( is_array( $default ) ) {
			if ( is_array( $value ) ) {
				return array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
			}
			return (array) $fallback;
		}

		if ( 'level' === $key ) {
			$value = sanitize_text_field( (string) $value );
			return in_array( $value, [ 'lossless', 'normal', 'aggressive', 'ultra' ], true ) ? $value : (string) $fallback;
		}

		if ( 'output_format' === $key ) {
			$value = sanitize_text_field( (string) $value );
			return in_array( $value, [ 'webp', 'avif' ], true ) ? $value : (string) $fallback;
		}

		if ( 'bulk_speed' === $key ) {
			$value = sanitize_text_field( (string) $value );
			return in_array( $value, [ 'gentle', 'normal', 'fast' ], true ) ? $value : (string) $fallback;
		}

		return null === $value ? (string) $fallback : sanitize_text_field( (string) $value );
	}

	/**
	 * Normalize the exclusion patterns textarea (or array) into a clean list.
	 *
	 * @param mixed $value Submitted value: newline-separated string or array.
	 * @return array<int, string>
	 */
	private static function sanitize_patterns( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\r\n|\r|\n/', $value );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$patterns = array_map(
			static fn ( $pattern ): string => trim( sanitize_text_field( (string) $pattern ) ),
			$value
		);

		return array_values( array_filter( $patterns, static fn ( string $pattern ): bool => '' !== $pattern ) );
	}
}
