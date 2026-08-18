<?php
/**
 * 301-redirects requests for a converted image's old URL to the new file.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Backup\BackupStore;
use LightweightPlugins\Img\Options;

/**
 * Safety net for references the database rewrite cannot reach (external
 * links, sent newsletters, search engines): when a request for an image
 * under the uploads directory 404s, the converted counterpart is looked up
 * (via the backup mapping, or the .webp/.avif sibling of the requested
 * name) and the request is permanently redirected to it.
 */
final class NotFoundRedirect {

	private const OLD_EXTENSIONS = [ 'jpg', 'jpeg', 'png', 'gif', 'heic', 'heif', 'tif', 'tiff', 'bmp' ];

	/**
	 * Hook the 404 handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', [ self::class, 'maybe_redirect' ] );
	}

	/**
	 * Redirect old image URLs on 404.
	 *
	 * @return void
	 */
	public static function maybe_redirect(): void {
		if ( ! is_404() || ! (bool) Options::get( 'redirect_missing_images' ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed and matched against known attachment paths only, never echoed.
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$base_path   = (string) wp_parse_url( (string) wp_get_upload_dir()['baseurl'], PHP_URL_PATH );

		$parsed = self::parse_request( $path, $base_path );
		if ( null === $parsed ) {
			return;
		}

		$target = self::target_url( $parsed );
		if ( null === $target ) {
			return;
		}

		wp_safe_redirect( $target, 301, 'lw-img' );
		exit;
	}

	/**
	 * Break an uploads path into the pieces needed for the lookup.
	 *
	 * @param string $path      Request path (e.g. /wp-content/uploads/2026/08/a-300x200.jpg).
	 * @param string $base_path Uploads base path (e.g. /wp-content/uploads).
	 * @return array{rel: string, stem_rel: string, width: int}|null Null when the
	 *         request is not an old-format image under the uploads directory.
	 */
	public static function parse_request( string $path, string $base_path ): ?array {
		if ( '' === $base_path || ! str_starts_with( $path, $base_path . '/' ) ) {
			return null;
		}

		$rel = rawurldecode( substr( $path, strlen( $base_path ) + 1 ) );
		if ( '' === $rel || str_contains( $rel, '..' ) ) {
			return null;
		}

		$dot = strrpos( $rel, '.' );
		if ( false === $dot ) {
			return null;
		}

		$extension = strtolower( substr( $rel, $dot + 1 ) );
		if ( ! in_array( $extension, self::OLD_EXTENSIONS, true ) ) {
			return null;
		}

		$stem  = substr( $rel, 0, $dot );
		$width = 0;

		if ( 1 === preg_match( '#-(\d+)x(\d+)$#', $stem, $matches ) ) {
			$width = (int) $matches[1];
			$stem  = substr( $stem, 0, -strlen( $matches[0] ) );
		}

		return [
			'rel'      => $rel,
			'stem_rel' => $stem,
			'width'    => $width,
		];
	}

	/**
	 * Resolve the redirect target for a parsed request.
	 *
	 * @param array{rel: string, stem_rel: string, width: int} $parsed Parsed request.
	 * @return string|null New URL, or null when no converted counterpart exists.
	 */
	private static function target_url( array $parsed ): ?string {
		$attachment_id = self::find_attachment( $parsed );
		if ( 0 === $attachment_id ) {
			return null;
		}

		$main = (string) wp_get_attachment_url( $attachment_id );
		if ( '' === $main ) {
			return null;
		}

		if ( $parsed['width'] > 0 ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size ) {
					$file = (string) ( $size['file'] ?? '' );
					if ( '' !== $file && (int) ( $size['width'] ?? 0 ) === $parsed['width'] ) {
						$slash = strrpos( $main, '/' );
						return false === $slash ? $main : substr( $main, 0, $slash + 1 ) . $file;
					}
				}
			}
		}

		return $main;
	}

	/**
	 * Find the converted attachment for an old path.
	 *
	 * @param array{rel: string, stem_rel: string, width: int} $parsed Parsed request.
	 * @return int Attachment ID, or 0.
	 */
	private static function find_attachment( array $parsed ): int {
		global $wpdb;

		// Exact backup mapping first — covers renamed/suffixed originals too.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- single indexed lookup on a frontend 404; no meta-API equivalent for value-based search.
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				BackupStore::META_KEY,
				$parsed['rel']
			)
		);

		if ( $id > 0 ) {
			return $id;
		}

		// The fallback below searches postmeta by value, which no index
		// covers. This runs on an unauthenticated 404, so it is rate-limited
		// rather than offered to anyone who asks for missing image paths.
		if ( ! LookupBudget::consume() ) {
			return 0;
		}

		// Fallback: a converted sibling of the requested name.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- single indexed lookup on a frontend 404; no meta-API equivalent for value-based search.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value IN (%s, %s) LIMIT 1",
				$parsed['stem_rel'] . '.webp',
				$parsed['stem_rel'] . '.avif'
			)
		);
	}
}
