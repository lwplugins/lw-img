<?php
/**
 * JSON typing of stored settings.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Casts every setting to its default's type, so the REST response carries
 * real booleans, numbers and lists whatever an older version stored.
 */
final class SettingsTypes {

	/**
	 * Typed copy of the settings, one entry per default key.
	 *
	 * @param array<string, mixed> $values   Stored values.
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	public static function typed( array $values, array $defaults ): array {
		$typed = [];

		foreach ( $defaults as $key => $default ) {
			$value = array_key_exists( $key, $values ) ? $values[ $key ] : $default;

			if ( 'pattern_rules' === $key ) {
				$typed[ $key ] = self::rules( $value );
			} elseif ( is_bool( $default ) ) {
				$typed[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$typed[ $key ] = is_numeric( $value ) ? (int) $value : $default;
			} elseif ( is_array( $default ) ) {
				$typed[ $key ] = is_array( $value ) ? array_values( array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) : [];
			} else {
				$typed[ $key ] = is_scalar( $value ) ? (string) $value : (string) $default;
			}
		}

		return $typed;
	}

	/**
	 * Pattern rules as clean {pattern, action, value} rows.
	 *
	 * @param mixed $value Stored rules.
	 * @return array<int, array{pattern: string, action: string, value: string}>
	 */
	private static function rules( mixed $value ): array {
		$rules = [];

		foreach ( is_array( $value ) ? $value : [] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$rules[] = [
				'pattern' => is_scalar( $row['pattern'] ?? null ) ? (string) $row['pattern'] : '',
				'action'  => is_scalar( $row['action'] ?? null ) ? (string) $row['action'] : '',
				'value'   => is_scalar( $row['value'] ?? null ) ? (string) $row['value'] : '',
			];
		}

		return $rules;
	}
}
