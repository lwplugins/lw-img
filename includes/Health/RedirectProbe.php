<?php
/**
 * Loopback probe: do missing-image requests reach WordPress?
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Health;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Media\NotFoundRedirect;

/**
 * Requests a guaranteed-missing image under the uploads directory. When
 * the request reaches WordPress, NotFoundRedirect answers it with a
 * marker header; a web server that serves uploads 404s itself (nginx
 * without an index.php fallback for that location) returns a plain 404
 * instead — and then the plugin's old-URL redirects can never run.
 */
final class RedirectProbe {

	/**
	 * Run the probe.
	 *
	 * @return bool|null True when the marker came back (redirects work),
	 *                   false on a plain response (the server swallows
	 *                   uploads 404s), null when the site could not
	 *                   request itself.
	 */
	public static function works(): ?bool {
		$base = (string) ( wp_get_upload_dir()['baseurl'] ?? '' );
		if ( '' === $base ) {
			return null;
		}

		// The query string busts CDN/proxy caches (Cloudflare caches plain
		// uploads 404s), without changing which server location answers.
		$url = $base . '/' . NotFoundRedirect::PROBE_BASENAME . '?lw-img-probe=' . wp_rand();

		$response = wp_safe_remote_get( $url, [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		return 'ok' === strtolower( (string) wp_remote_retrieve_header( $response, 'x-lw-img-probe' ) );
	}
}
