<?php
/**
 * List-of-names parser.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Accepts a list of strings that each match a pattern: image size names for
 * smart crop, MIME types for the conversion queue. Duplicates are dropped.
 */
final class NameListParser {

	/**
	 * Most entries a list may hold.
	 */
	private const MAX_ENTRIES = 200;

	/**
	 * Image size names: WordPress registers them through sanitize_key-like
	 * slugs. Stale names (a theme switch removed the size) are accepted on
	 * purpose — they simply never match at crop time.
	 *
	 * @param mixed $raw Submitted list.
	 * @return ParseResult
	 */
	public static function sizes( mixed $raw ): ParseResult {
		return self::parse( $raw, '/^[a-z0-9_-]{1,100}$/', false );
	}

	/**
	 * Image MIME types for the conversion queue. At least one is required:
	 * an empty list would silently stop every conversion.
	 *
	 * @param mixed $raw Submitted list.
	 * @return ParseResult
	 */
	public static function mime_types( mixed $raw ): ParseResult {
		return self::parse( $raw, '#^image/[a-z0-9.+-]{1,60}$#', true );
	}

	/**
	 * Parse a list against a pattern.
	 *
	 * @param mixed  $raw      Submitted list.
	 * @param string $pattern  Regex every entry must match (after lower-casing).
	 * @param bool   $required Whether the list must not be empty.
	 * @return ParseResult
	 */
	private static function parse( mixed $raw, string $pattern, bool $required ): ParseResult {
		if ( ! is_array( $raw ) || count( $raw ) > self::MAX_ENTRIES ) {
			return ParseResult::fail( [ __( 'Must be a list of names.', 'lw-img' ) ] );
		}

		$names   = [];
		$invalid = [];

		foreach ( $raw as $entry ) {
			$name = is_string( $entry ) ? strtolower( trim( $entry ) ) : '';

			if ( 1 !== preg_match( $pattern, $name ) ) {
				$invalid[] = is_scalar( $entry ) ? (string) $entry : gettype( $entry );
				continue;
			}

			$names[ $name ] = true;
		}

		if ( [] !== $invalid ) {
			/* translators: %s: comma-separated list of refused entries */
			return ParseResult::fail( [ sprintf( __( 'Not valid: %s.', 'lw-img' ), implode( ', ', array_slice( $invalid, 0, 10 ) ) ) ] );
		}

		if ( $required && [] === $names ) {
			return ParseResult::fail( [ __( 'At least one entry is required.', 'lw-img' ) ] );
		}

		return ParseResult::ok( array_keys( $names ) );
	}
}
