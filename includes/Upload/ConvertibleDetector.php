<?php
/**
 * Decides whether a freshly uploaded file should be sent to HelloImg.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

use LightweightPlugins\Img\Options;

/**
 * Applies the skip rules that decide whether an upload is sent to HelloImg.
 */
final class ConvertibleDetector {

	private const SKIP_TYPES = [
		'image/webp',
		'image/avif',
	];

	public function should_convert( string $file_path, string $mime_type ): bool {
		if ( ! Options::get( 'auto_convert' ) ) {
			return $this->skip( $file_path, 'auto_convert disabled' );
		}

		if ( '' === (string) Options::get( 'api_key' ) ) {
			return $this->skip( $file_path, 'no API key' );
		}

		if ( ! str_starts_with( $mime_type, 'image/' ) ) {
			return $this->skip( $file_path, 'not an image' );
		}

		if ( Options::get( 'skip_already_webp' ) && in_array( $mime_type, self::SKIP_TYPES, true ) ) {
			return $this->skip( $file_path, 'already webp/avif' );
		}

		$allowed = (array) Options::get( 'mime_types' );
		if ( ! in_array( $mime_type, $allowed, true ) ) {
			return $this->skip( $file_path, 'mime not in allowed list' );
		}

		if ( ! is_readable( $file_path ) ) {
			return $this->skip( $file_path, 'file not readable' );
		}

		$max_bytes = ( (int) Options::get( 'max_filesize_mb' ) ) * 1024 * 1024;
		if ( $max_bytes > 0 && filesize( $file_path ) > $max_bytes ) {
			return $this->skip( $file_path, 'exceeds max_filesize_mb' );
		}

		if ( Options::get( 'skip_animated_gif' ) && 'image/gif' === $mime_type && $this->is_animated_gif( $file_path ) ) {
			return $this->skip( $file_path, 'animated gif' );
		}

		return (bool) apply_filters( 'lw_img_should_convert', true, $file_path, $mime_type );
	}

	private function skip( string $file_path, string $reason ): bool {
		do_action( 'lw_img_upload_skipped', $file_path, $reason );
		return false;
	}

	private function is_animated_gif( string $file_path ): bool {
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
