<?php
/**
 * Polls a slow optimize job until it finishes.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Api;

defined( 'ABSPATH' ) || exit;

use Closure;

/**
 * When the gateway's own wait runs out it answers 408 with a poll URL; the
 * work is already in flight server-side, so wait for it instead of failing
 * and re-uploading the image.
 */
final class JobPoller {

	private const ATTEMPTS = 5;

	private const INTERVAL_SECONDS = 3;

	/**
	 * HelloImg API key — /v1/jobs is authenticated like every /v1 route.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Pause between attempts; receives the number of seconds.
	 *
	 * @var Closure
	 */
	private Closure $wait;

	public function __construct( string $api_key, ?Closure $wait = null ) {
		$this->api_key = $api_key;
		$this->wait    = $wait ?? static function ( int $seconds ): void {
			sleep( $seconds );
		};
	}

	/**
	 * Poll a job for a bounded time.
	 *
	 * @param string $poll_url Poll URL from the 408 response body.
	 * @return OptimizeResult
	 * @throws ApiException When the URL is not a job on the API host, the key
	 *                      is rejected, the job fails, or it is still
	 *                      processing after the budget.
	 */
	public function wait_for( string $poll_url ): OptimizeResult {
		$url = self::resolve_url( $poll_url );
		if ( null === $url ) {
			throw new ApiException( 'Invalid poll URL from API', 'invalid_response', 408 );
		}

		$site = SiteHost::header_value();

		for ( $attempt = 0; $attempt < self::ATTEMPTS; $attempt++ ) {
			( $this->wait )( self::INTERVAL_SECONDS );

			$response = wp_safe_remote_get(
				$url,
				[
					'timeout'             => 10,
					'redirection'         => 0,
					'limit_response_size' => 256 * KB_IN_BYTES,
					'headers'             => [
						'Authorization' => 'Bearer ' . $this->api_key,
						'X-HIMG-Site'   => $site,
					],
				]
			);
			if ( is_wp_error( $response ) ) {
				continue;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

			// A rejected key or exhausted quota will not recover within the
			// wait budget — surface it so a bulk run halts on it.
			if ( in_array( $status, [ 401, 402, 403 ], true ) ) {
				[ $code, $message ] = Client::error_parts( $body, $status );
				throw new ApiException( esc_html( $message ), $code, $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- code and status are internal scalars, never output as HTML.
			}

			if ( ! is_array( $body ) ) {
				continue;
			}

			$job_status = (string) ( $body['status'] ?? '' );

			if ( 'completed' === $job_status ) {
				return OptimizeResult::from_response( $body );
			}

			if ( 'failed' === $job_status ) {
				$message = is_string( $body['error'] ?? null ) ? (string) $body['error'] : 'Processing failed';
				throw new ApiException( esc_html( $message ), 'processing_failed', 422 );
			}
		}

		throw new ApiException( 'Optimization still processing after extended wait', 'timeout', 408 );
	}

	/**
	 * Absolute job URL on the API host, or null when the value is not one.
	 *
	 * The gateway answers with a path (`/v1/jobs/{id}`). The value comes from
	 * a response body and the request carries the API key, so it is pinned to
	 * a job path on the API host over HTTPS: a spoofed response can neither
	 * turn this into an SSRF nor send the key anywhere else.
	 *
	 * @param string $poll_url Poll URL or path from the API.
	 * @return string|null
	 */
	public static function resolve_url( string $poll_url ): ?string {
		$api_host = (string) wp_parse_url( Client::BASE_URL, PHP_URL_HOST );

		if ( str_starts_with( $poll_url, '/' ) && ! str_starts_with( $poll_url, '//' ) ) {
			$poll_url = 'https://' . $api_host . $poll_url;
		}

		$parts = wp_parse_url( $poll_url );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['port'] ) ) {
			return null;
		}

		$on_api_host = 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
			&& strtolower( (string) ( $parts['host'] ?? '' ) ) === $api_host;

		return $on_api_host && str_starts_with( (string) ( $parts['path'] ?? '' ), '/v1/jobs/' ) ? $poll_url : null;
	}
}
