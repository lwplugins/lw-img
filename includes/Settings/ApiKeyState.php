<?php
/**
 * What the admin screen may know about the API key.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The key itself never leaves the server: the screen gets whether one is
 * set, where it comes from, and a hint (prefix and last four characters)
 * so the user can tell which key it is.
 */
final class ApiKeyState {

	public const SOURCE_CONSTANT = 'constant';
	public const SOURCE_OPTION   = 'option';
	public const SOURCE_NONE     = 'none';

	/**
	 * Keys shorter than this get no tail in the hint: four characters of a
	 * short key would give away too much of it.
	 */
	private const MIN_TAIL_LENGTH = 12;

	/**
	 * Describe the effective key.
	 *
	 * @param string|null $constant_key Key from LW_IMG_API_KEY, or null.
	 * @param string      $stored_key   Key stored in the options.
	 * @return array{set: bool, source: string, hint: string}
	 */
	public static function describe( ?string $constant_key, string $stored_key ): array {
		if ( null !== $constant_key && '' !== trim( $constant_key ) ) {
			return self::shape( self::SOURCE_CONSTANT, trim( $constant_key ) );
		}

		if ( '' !== trim( $stored_key ) ) {
			return self::shape( self::SOURCE_OPTION, trim( $stored_key ) );
		}

		return [
			'set'    => false,
			'source' => self::SOURCE_NONE,
			'hint'   => '',
		];
	}

	/**
	 * Masked form of a key: "himg_…abcd".
	 *
	 * @param string $key API key.
	 * @return string
	 */
	public static function hint( string $key ): string {
		$underscore = strpos( $key, '_' );
		$prefix     = false !== $underscore && $underscore < 8 ? substr( $key, 0, $underscore + 1 ) : '';
		$tail       = strlen( $key ) >= self::MIN_TAIL_LENGTH ? substr( $key, -4 ) : '';

		return $prefix . '…' . $tail;
	}

	/**
	 * The state of a set key.
	 *
	 * @param string $source Where the key comes from.
	 * @param string $key    The key.
	 * @return array{set: bool, source: string, hint: string}
	 */
	private static function shape( string $source, string $key ): array {
		return [
			'set'    => true,
			'source' => $source,
			'hint'   => self::hint( $key ),
		];
	}
}
