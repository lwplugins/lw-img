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
	 * Transient holding the last definite verdict ('yes' / 'no').
	 */
	public const CACHE_KEY = 'lw_img_redirect_probe';

	/**
	 * Seconds a verdict stays cached: long enough that the Bulk tab does
	 * not fire a loopback request on every view, short enough that a
	 * server fix shows up without waiting.
	 */
	public const CACHE_TTL = 600;

	/**
	 * The probe verdict, cached for CACHE_TTL. Only definite results are
	 * stored — an unreachable loopback (null) is retried next time.
	 *
	 * @return bool|null See works().
	 */
	public static function cached(): ?bool {
		$stored = get_transient( self::CACHE_KEY );
		if ( 'yes' === $stored || 'no' === $stored ) {
			return 'yes' === $stored;
		}

		$works = self::works();
		self::remember( $works );

		return $works;
	}

	/**
	 * Keep a live verdict for the screens that read the cache (a start
	 * refused on redirects should show the same reason afterwards).
	 *
	 * @param bool|null $works Verdict from works(); null is not stored.
	 * @return void
	 */
	public static function remember( ?bool $works ): void {
		if ( null !== $works ) {
			set_transient( self::CACHE_KEY, $works ? 'yes' : 'no', self::CACHE_TTL );
		}
	}

	/**
	 * Drop the cached verdict (settings saved, Tester re-run).
	 *
	 * @return void
	 */
	public static function forget(): void {
		delete_transient( self::CACHE_KEY );
	}

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
