<?php
/**
 * HelloImg HTTP client.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Api;

use LightweightPlugins\Img\Logger;
use LightweightPlugins\Img\Options;

/**
 * Thin HTTP client for the HelloImg REST API.
 */
final class Client {

	private const BASE_URL = 'https://api.helloimg.io/v1';

	/**
	 * HelloImg API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private int $timeout;

	public function __construct( ?string $api_key = null, ?int $timeout = null ) {
		$this->api_key = $api_key ?? (string) Options::get( 'api_key' );
		$this->timeout = $timeout ?? (int) Options::get( 'request_timeout' );
	}

	public function optimize( OptimizeRequest $request ): OptimizeResult {
		$this->require_api_key();

		if ( ! is_readable( $request->file_path ) ) {
			throw new ApiException( esc_html( 'File not readable: ' . $request->file_path ), 'invalid_request' );
		}

		$boundary = wp_generate_password( 24, false );
		$body     = $this->build_multipart_body( $request, $boundary );

		$response = wp_remote_post(
			self::BASE_URL . '/optimize',
			[
				'timeout' => $this->timeout,
				'headers' => [
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				],
				'body'    => $body,
			]
		);

		return $this->parse_optimize_response( $response );
	}

	public function get_account(): array {
		$this->require_api_key();

		$response = wp_remote_get(
			self::BASE_URL . '/account',
			[
				'timeout' => 10,
				'headers' => [
					'Authorization' => 'Bearer ' . $this->api_key,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new ApiException( esc_html( $response->get_error_message() ), 'network_error' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status ) {
			[ $code, $message ] = self::error_parts( $body, $status );
			throw new ApiException( esc_html( $message ), $code, $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- code and status are internal scalars, never output as HTML.
		}

		return is_array( $body ) ? $body : [];
	}

	/**
	 * Extract [code, message] from an error body.
	 *
	 * Handles both response shapes the API uses: the nested
	 * `{"error": {"code", "message"}}` and the flat
	 * `{"error": "message", "code": "..."}` (e.g. invalid_request rejections).
	 *
	 * @param mixed $body   Decoded response body.
	 * @param int   $status HTTP status code.
	 * @return array{0: string, 1: string}
	 */
	private static function error_parts( mixed $body, int $status ): array {
		if ( ! is_array( $body ) ) {
			return [ 'http_' . $status, 'HTTP ' . $status ];
		}

		$error = $body['error'] ?? null;

		if ( is_string( $error ) && '' !== $error ) {
			return [ (string) ( $body['code'] ?? 'http_' . $status ), $error ];
		}

		if ( is_array( $error ) ) {
			return [
				(string) ( $error['code'] ?? 'http_' . $status ),
				(string) ( $error['message'] ?? 'HTTP ' . $status ),
			];
		}

		return [ 'http_' . $status, 'HTTP ' . $status ];
	}

	private function require_api_key(): void {
		if ( '' === $this->api_key ) {
			throw new ApiException( 'API key not configured', 'unauthorized', 401 );
		}
	}

	private function parse_optimize_response( mixed $response ): OptimizeResult {
		if ( is_wp_error( $response ) ) {
			Logger::error( 'optimize: network error', [ 'msg' => $response->get_error_message() ] );
			throw new ApiException( esc_html( $response->get_error_message() ), 'network_error' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		// Slow job: the API hands back a job id and poll URL — wait for it
		// instead of failing, the work is already in flight server-side.
		if ( 408 === $status && is_array( $body ) && ! empty( $body['poll_url'] ) ) {
			return $this->poll_job( (string) $body['poll_url'] );
		}

		if ( 200 !== $status ) {
			[ $code, $message ] = self::error_parts( $body, $status );
			Logger::error(
				'optimize: api error',
				[
					'status'  => $status,
					'code'    => $code,
					'message' => $message,
				]
			);
			throw new ApiException( esc_html( $message ), $code, $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- code and status are internal scalars, never output as HTML.
		}

		if ( ! is_array( $body ) ) {
			throw new ApiException( 'Malformed response body', 'invalid_response' );
		}

		$result = OptimizeResult::from_response( $body );

		if ( ! $result->is_completed() ) {
			throw new ApiException( 'Optimize result not completed', 'incomplete', $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- status is an internal scalar, never output as HTML.
		}

		return $result;
	}

	/**
	 * Poll a slow job for a bounded time.
	 *
	 * @param string $poll_url Poll URL returned by the 408 response.
	 * @return OptimizeResult
	 * @throws ApiException When the job fails or is still processing after the budget.
	 */
	private function poll_job( string $poll_url ): OptimizeResult {
		if ( ! str_starts_with( $poll_url, 'https://' ) ) {
			throw new ApiException( 'Invalid poll URL from API', 'invalid_response', 408 );
		}

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			sleep( 3 );

			$response = wp_remote_get( $poll_url, [ 'timeout' => 10 ] );
			if ( is_wp_error( $response ) ) {
				continue;
			}

			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
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

	private function build_multipart_body( OptimizeRequest $request, string $boundary ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading the just-uploaded local file, not a remote URL.
		$file_contents = (string) file_get_contents( $request->file_path );
		$filename      = basename( $request->file_path );
		$mime          = (string) mime_content_type( $request->file_path );
		$data_json     = (string) wp_json_encode( $request->to_data_payload() );

		$eol  = "\r\n";
		$body = '';

		$body .= '--' . $boundary . $eol;
		$body .= 'Content-Disposition: form-data; name="image"; filename="' . $filename . '"' . $eol;
		$body .= 'Content-Type: ' . ( '' !== $mime ? $mime : 'application/octet-stream' ) . $eol . $eol;
		$body .= $file_contents . $eol;

		$body .= '--' . $boundary . $eol;
		$body .= 'Content-Disposition: form-data; name="data"' . $eol;
		$body .= 'Content-Type: application/json' . $eol . $eol;
		$body .= $data_json . $eol;

		$body .= '--' . $boundary . '--' . $eol;

		return $body;
	}
}
