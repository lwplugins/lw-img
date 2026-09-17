<?php
/**
 * The host this site identifies itself with to the API.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Derives the X-HIMG-Site value. The API binds a live key to a website and
 * rejects requests without this header, so an underivable host must fail
 * loudly here: cURL silently drops a header whose value is empty, and the
 * API would only answer a bare "X-HIMG-Site header required".
 */
final class SiteHost {

	/**
	 * The site's host: home_url(), then site_url(), then the filter.
	 *
	 * @return string Lowercase host without port or trailing dot; '' when unknown.
	 */
	public static function current(): string {
		$host = self::from_url( (string) home_url() );

		if ( '' === $host ) {
			$host = self::from_url( (string) site_url() );
		}

		/**
		 * Override the host sent as X-HIMG-Site (multisite, reverse proxies).
		 *
		 * @param string $host Host derived from home_url() (site_url() as a fallback).
		 */
		return trim( (string) apply_filters( 'lw_img_site_host', $host ) );
	}

	/**
	 * The value for the X-HIMG-Site request header.
	 *
	 * @return string
	 * @throws ApiException When no host can be derived — the request would be refused anyway.
	 */
	public static function header_value(): string {
		$host = self::current();

		if ( '' === $host ) {
			throw new ApiException(
				'Could not determine this site\'s host for the X-HIMG-Site header — check the Site Address (WP_HOME) or set it with the lw_img_site_host filter',
				'site_header_required',
				403
			);
		}

		return $host;
	}

	/**
	 * Host part of a URL, tolerating a scheme-less or protocol-relative value
	 * (a WP_HOME of "example.com" parses as a path, not a host).
	 *
	 * @param string $url URL or bare host.
	 * @return string Lowercase host without port or trailing dot; '' when there is none.
	 */
	public static function from_url( string $url ): string {
		$url = trim( $url );

		if ( str_starts_with( $url, '//' ) ) {
			$url = 'https:' . $url;
		} elseif ( '' !== $url && ! str_starts_with( $url, '/' ) && 1 !== preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ) {
			$url = 'https://' . $url;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) ? rtrim( strtolower( $host ), '.' ) : '';
	}
}
