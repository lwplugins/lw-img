<?php
/**
 * Detects whether a GIF file is animated.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

defined( 'ABSPATH' ) || exit;

/**
 * Byte-scans for multiple GIF frame markers without decoding the image.
 */
final class AnimatedGifProbe {

	/**
	 * Whether the file contains more than one animation frame.
	 *
	 * @param string $file_path Absolute file path.
	 * @return bool
	 */
	public static function is_animated( string $file_path ): bool {
		if ( ! is_readable( $file_path ) ) {
			return false;
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions -- chunked binary read for frame-marker detection; WP_Filesystem has no partial-read API.
		$handle = fopen( $file_path, 'rb' );
		if ( false === $handle ) {
			return false;
		}

		$count = 0;
		$chunk = '';

		while ( ! feof( $handle ) && $count < 2 ) {
			$chunk .= fread( $handle, 1024 * 100 );
			$count  = preg_match_all( '#\x00\x21\xF9\x04#s', $chunk );
		}

		fclose( $handle );
		// phpcs:enable WordPress.WP.AlternativeFunctions

		return $count > 1;
	}
}
