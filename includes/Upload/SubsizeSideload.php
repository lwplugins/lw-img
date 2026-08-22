<?php
/**
 * Recognises WordPress 7.1's per-thumbnail sideload requests.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

defined( 'ABSPATH' ) || exit;

/**
 * The 7.1 client-side uploader uploads every browser-generated sub-size
 * through POST /wp/v2/media/{id}/sideload, and that endpoint runs the same
 * wp_handle_upload filter the interceptor listens on. Converting those
 * files would spend one API call per thumbnail — measured at 3 calls for a
 * 2-size upload, ~11 on a typical store.
 *
 * Precision matters: URL-sideloads of PRIMARY images (an importer pulling
 * an external file) use the /wp/v2/media create route and MUST keep being
 * converted. Only the sub-size route is skipped.
 */
final class SubsizeSideload {

	/**
	 * Whether the current request is a sub-size sideload.
	 *
	 * @return bool
	 */
	public static function is_current_request(): bool {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}

		$route = null;
		if ( isset( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
		}

		return self::is_route( $route );
	}

	/**
	 * Whether a REST route is the media sub-size sideload endpoint.
	 *
	 * @param string|null $route REST route (e.g. "/wp/v2/media/375/sideload"), or null.
	 * @return bool
	 */
	public static function is_route( ?string $route ): bool {
		if ( null === $route || '' === $route ) {
			return false;
		}

		return 1 === preg_match( '#^/wp/v2/media/\d+/sideload/?$#', $route );
	}
}
