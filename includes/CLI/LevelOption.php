<?php
/**
 * Parses the CLI --level override.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\CLI;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\OptimizeRequest;
use WP_CLI;

/**
 * Turns the optional --level flag into an optimizer level override, so
 * a run (or a single image) can be converted at a different level than
 * the saved setting — e.g. comparing lossless and normal output.
 */
final class LevelOption {

	/**
	 * Validate and normalize a raw level value.
	 *
	 * @param string|null $raw Raw flag value, or null when the flag is absent.
	 * @return string|null The level, or null for "use the saved setting".
	 * @throws \InvalidArgumentException On a level the API does not know.
	 */
	public static function normalize( ?string $raw ): ?string {
		if ( null === $raw ) {
			return null;
		}

		$level = strtolower( trim( $raw ) );
		$known = [
			OptimizeRequest::LEVEL_LOSSLESS,
			OptimizeRequest::LEVEL_NORMAL,
			OptimizeRequest::LEVEL_AGGRESSIVE,
			OptimizeRequest::LEVEL_ULTRA,
		];

		if ( ! in_array( $level, $known, true ) ) {
			$message = sprintf( 'unknown level "%s" — use one of: %s', sanitize_key( $raw ), implode( ', ', $known ) );

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-only: the message goes to the terminal via WP_CLI::error(), where HTML entities would render literally.
			throw new \InvalidArgumentException( $message );
		}

		return $level;
	}

	/**
	 * Parse the flag from assoc args, exiting with a CLI error when invalid.
	 *
	 * @param array<string, string> $assoc_args Named CLI arguments.
	 * @return string|null Level override, or null.
	 */
	public static function parse( array $assoc_args ): ?string {
		try {
			return self::normalize( isset( $assoc_args['level'] ) ? (string) $assoc_args['level'] : null );
		} catch ( \InvalidArgumentException $e ) {
			WP_CLI::error( $e->getMessage() );
			return null; // Unreachable — error() exits; satisfies the declared return type.
		}
	}
}
