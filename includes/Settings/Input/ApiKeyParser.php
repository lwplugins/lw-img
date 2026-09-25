<?php
/**
 * API key parser.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Accepts a single-token key, or an empty string to remove the stored key.
 * The format is not checked beyond that — the Test connection call is the
 * real validation, and future key formats must not need a plugin update.
 */
final class ApiKeyParser {

	private const MAX_LENGTH = 200;

	/**
	 * Parse a raw value.
	 *
	 * @param mixed $raw Raw value.
	 * @return ParseResult
	 */
	public static function parse( mixed $raw ): ParseResult {
		if ( ! is_string( $raw ) ) {
			return ParseResult::fail( [ __( 'The API key must be text.', 'lw-img' ) ] );
		}

		$key = trim( $raw );

		if ( '' === $key ) {
			return ParseResult::ok( '' );
		}

		if ( strlen( $key ) > self::MAX_LENGTH || 1 !== preg_match( '/^[\x21-\x7e]+$/', $key ) ) {
			return ParseResult::fail( [ __( 'This does not look like an API key: paste the key alone, without spaces.', 'lw-img' ) ] );
		}

		return ParseResult::ok( $key );
	}
}
