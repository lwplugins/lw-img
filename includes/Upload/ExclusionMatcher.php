<?php
/**
 * Matches file paths against user-defined exclusion patterns.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

defined( 'ABSPATH' ) || exit;

/**
 * Wildcard matcher: `*` matches any characters, `?` a single character.
 * Patterns without a slash are matched against the filename (full match);
 * patterns containing a slash are matched anywhere in the full path.
 * Matching is case-insensitive.
 */
final class ExclusionMatcher {

	/**
	 * Whether any pattern matches the given file.
	 *
	 * @param string             $file_path Absolute file path.
	 * @param array<int, string> $patterns  Exclusion patterns.
	 * @return bool
	 */
	public function matches( string $file_path, array $patterns ): bool {
		$basename = basename( $file_path );

		foreach ( $patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}

			$is_path = str_contains( $pattern, '/' );
			$subject = $is_path ? $file_path : $basename;

			if ( 1 === preg_match( self::to_regex( $pattern, $is_path ), $subject ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert a wildcard pattern to a regular expression.
	 *
	 * @param string $pattern Wildcard pattern.
	 * @param bool   $is_path Whether the pattern targets the full path (substring match).
	 * @return string
	 */
	private static function to_regex( string $pattern, bool $is_path ): string {
		$quoted = str_replace(
			[ '\*', '\?' ],
			[ '.*', '.' ],
			preg_quote( $pattern, '#' )
		);

		return $is_path ? '#' . $quoted . '#i' : '#^' . $quoted . '$#i';
	}
}
