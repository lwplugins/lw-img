<?php
/**
 * Settings sanitiser.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\OptimizeRequest;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Upload\Rules\RuleSet;

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
		if ( 'pattern_rules' === $key ) {
			return self::sanitize_rules( $value );
		}

		if ( 'smartcrop_sizes' === $key ) {
			return self::sanitize_size_names( $value );
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
	 * Normalize the pattern-rule rows: trim the pattern, whitelist the
	 * action, validate the level value, drop anything incomplete.
	 *
	 * @param mixed $value Submitted rows: list of {pattern, action, value}.
	 * @return array<int, array{pattern: string, action: string, value: string}>
	 */
	private static function sanitize_rules( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$rules = [];

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$pattern = trim( sanitize_text_field( (string) ( $row['pattern'] ?? '' ) ) );
			$action  = sanitize_key( (string) ( $row['action'] ?? '' ) );

			if ( '' === $pattern || ! in_array( $action, RuleSet::ACTIONS, true ) ) {
				continue;
			}

			$level = '';
			if ( RuleSet::ACTION_LEVEL === $action ) {
				$level = sanitize_key( (string) ( $row['value'] ?? '' ) );
				if ( ! OptimizeRequest::valid_level( $level ) ) {
					continue;
				}
			}

			$rules[] = [
				'pattern' => $pattern,
				'action'  => $action,
				'value'   => $level,
			];
		}

		return $rules;
	}

	/**
	 * Normalize the smart-crop size selection into a clean list of size names.
	 *
	 * The checkbox group posts a hidden empty string when nothing is checked,
	 * so '' deliberately means "none selected" rather than "keep the old
	 * value". Names are sanitize_key'd only — they are NOT validated against
	 * the live size registry, because themes change: a stale name simply
	 * never matches at crop time.
	 *
	 * @param mixed $value Submitted value: array of names, or '' for none.
	 * @return array<int, string>
	 */
	private static function sanitize_size_names( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$names = array_map(
			static fn ( $name ): string => sanitize_key( (string) $name ),
			$value
		);

		return array_values( array_filter( $names, static fn ( string $name ): bool => '' !== $name ) );
	}
}
