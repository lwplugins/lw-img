<?php
/**
 * Builds old-URL => new-URL replacement pairs for a converted attachment.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

/**
 * Sub-size URLs are matched by size name; a size that no longer exists
 * after regeneration falls back to the new main URL so no reference is
 * ever left pointing at a deleted file.
 */
final class UrlPairs {

	/**
	 * Build the replacement pairs.
	 *
	 * @param string                $old_main_url Old main file URL.
	 * @param array<string, string> $old_sizes    Map of size name => file name before.
	 * @param string                $new_main_url New main file URL.
	 * @param array<string, string> $new_sizes    Map of size name => file name after.
	 * @return array<string, string> Map of old URL => new URL.
	 */
	public static function build( string $old_main_url, array $old_sizes, string $new_main_url, array $new_sizes ): array {
		if ( '' === $old_main_url || '' === $new_main_url ) {
			return [];
		}

		$old_dir = self::dir_url( $old_main_url );
		$new_dir = self::dir_url( $new_main_url );
		$pairs   = [ $old_main_url => $new_main_url ];

		foreach ( $old_sizes as $name => $old_file ) {
			if ( '' === $old_file ) {
				continue;
			}

			$new_file = (string) ( $new_sizes[ $name ] ?? '' );

			$pairs[ $old_dir . '/' . $old_file ] = '' !== $new_file
				? $new_dir . '/' . $new_file
				: $new_main_url;
		}

		return array_filter(
			$pairs,
			static fn ( string $new_url, string $old_url ): bool => $new_url !== $old_url,
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Extract the size-name => file-name map from attachment metadata.
	 *
	 * @param mixed $metadata Attachment metadata (or anything else).
	 * @return array<string, string>
	 */
	public static function sizes_map( mixed $metadata ): array {
		if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return [];
		}

		$map = [];
		foreach ( $metadata['sizes'] as $name => $size ) {
			$file = (string) ( $size['file'] ?? '' );
			if ( '' !== $file ) {
				$map[ (string) $name ] = $file;
			}
		}

		return $map;
	}

	/**
	 * URL of the containing directory (no trailing slash).
	 *
	 * @param string $url File URL.
	 * @return string
	 */
	private static function dir_url( string $url ): string {
		$pos = strrpos( $url, '/' );

		return false === $pos ? $url : substr( $url, 0, $pos );
	}
}
