<?php
/**
 * Maps the image editor's output format to the plugin's.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;

/**
 * WordPress 7.1's client-side uploader learns the browser's conversion
 * target from the REST attachment response's image_output_format field,
 * which core computes by running this filter inside
 * prepare_item_for_response() during a REST request. Mapping jpeg/png to
 * the plugin's output format there hands the thumbnail conversion to the
 * browser while the main file still goes through the API once.
 *
 * The map is deliberately scoped to REST requests only. Every server-side
 * regeneration path — Restorer (admin-post), WP-CLI, cron, and the
 * wp-admin image editor (admin-ajax) — is non-REST and must see the map
 * untouched, or core would re-convert a restored JPEG back to WebP the
 * moment its thumbnails regenerate, orphaning the recovered original on
 * disk and re-queuing the attachment. The whole mapping is further gated
 * on auto_convert so switching the pipeline off restores stock behaviour
 * on REST requests too.
 *
 * REST alone is not a narrow enough gate: create_item() runs
 * wp_generate_attachment_metadata() inside the REST request on EVERY
 * WordPress version, and core consults this map for the MAIN file too —
 * not just client-side thumbnails. Two further gates keep the map inert
 * where it would otherwise silently convert a file the plugin itself
 * skipped:
 * - function_exists( 'wp_is_client_side_media_processing_enabled' ) —
 *   that symbol is WP 7.1+ only; below 7.1 there is no client-side
 *   processing to hand off to, so the map has zero upside and only downside.
 * - lw_img_is_configured() — no API key means the plugin converts
 *   nothing server-side either, so the browser must not convert anything
 *   in its place (a fresh, unconfigured install must behave stock).
 *
 * GIF is deliberately absent (a client-side conversion would flatten
 * animation) and HEIC stays on core's own heic-to-jpeg mapping.
 */
final class OutputFormatMap {

	/**
	 * Hook the filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'image_editor_output_format', [ self::class, 'map' ] );
	}

	/**
	 * Extend the mapping with the plugin's output format.
	 *
	 * @param array<string, string> $map Mime-to-mime output mapping.
	 * @return array<string, string>
	 */
	public static function map( array $map ): array {
		if ( ! self::is_rest_request() ) {
			return $map;
		}

		if ( ! function_exists( 'wp_is_client_side_media_processing_enabled' ) ) {
			return $map;
		}

		if ( ! \LightweightPlugins\Img\lw_img_is_configured() ) {
			return $map;
		}

		if ( ! (bool) Options::get( 'auto_convert' ) ) {
			return $map;
		}

		$target = 'avif' === Options::get( 'output_format' ) ? 'image/avif' : 'image/webp';

		$map['image/jpeg'] = $target;
		$map['image/png']  = $target;

		return $map;
	}

	/**
	 * Detect whether the current request is being served through the REST API.
	 *
	 * Prefers wp_is_serving_rest_request() (WP 6.5+); falls back to the
	 * REST_REQUEST constant that core defines from the plugin's WP 6.0 floor
	 * onward, covering 6.0-6.4.
	 *
	 * @return bool
	 */
	private static function is_rest_request(): bool {
		if ( function_exists( 'wp_is_serving_rest_request' ) ) {
			return wp_is_serving_rest_request();
		}

		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}
}
