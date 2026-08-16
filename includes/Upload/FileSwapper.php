<?php
/**
 * Replaces the locally-uploaded file with the optimized HelloImg result.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

use LightweightPlugins\Img\Api\OptimizeResult;

/**
 * Downloads the optimized image and replaces the uploaded file on disk.
 */
final class FileSwapper {

	public function swap( string $original_path, string $original_url, OptimizeResult $result ): array {
		$bytes = $this->download( $result->image_url );

		$new_path = $this->target_path( $original_path, $result->format );
		$new_url  = $this->target_url( $original_url, $result->format );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing next to the upload in the uploads dir; WP_Filesystem adds nothing here.
		if ( false === file_put_contents( $new_path, $bytes ) ) {
			throw new \RuntimeException( esc_html( 'Failed to write optimized file: ' . $new_path ) );
		}

		if ( $new_path !== $original_path && file_exists( $original_path ) ) {
			wp_delete_file( $original_path );
		}

		return [
			'file' => $new_path,
			'url'  => $new_url,
			'type' => $this->mime_for_format( $result->format ),
		];
	}

	private function download( string $url ): string {
		$response = wp_safe_remote_get( $url, [ 'timeout' => 30 ] );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( esc_html( 'Download failed: ' . $response->get_error_message() ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			throw new \RuntimeException( esc_html( 'Download HTTP ' . $status ) );
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			throw new \RuntimeException( 'Empty response body from CDN' );
		}

		return $body;
	}

	private function target_path( string $original_path, string $format ): string {
		$ext  = $this->extension_for_format( $format );
		$dir  = dirname( $original_path );
		$base = pathinfo( $original_path, PATHINFO_FILENAME );

		return $dir . '/' . $base . '.' . $ext;
	}

	private function target_url( string $original_url, string $format ): string {
		$ext  = $this->extension_for_format( $format );
		$path = wp_parse_url( $original_url, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return $original_url;
		}

		$dir  = dirname( $path );
		$base = pathinfo( $path, PATHINFO_FILENAME );

		$replaced = $dir . '/' . $base . '.' . $ext;

		return str_replace( $path, $replaced, $original_url );
	}

	private function extension_for_format( string $format ): string {
		return match ( strtolower( $format ) ) {
			'jpeg', 'jpg' => 'jpg',
			'png'         => 'png',
			'avif'        => 'avif',
			'gif'         => 'gif',
			default       => 'webp',
		};
	}

	private function mime_for_format( string $format ): string {
		return match ( strtolower( $format ) ) {
			'jpeg', 'jpg' => 'image/jpeg',
			'png'         => 'image/png',
			'avif'        => 'image/avif',
			'gif'         => 'image/gif',
			default       => 'image/webp',
		};
	}
}
