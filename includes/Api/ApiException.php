<?php
/**
 * Thrown when a HelloImg API call fails.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Api;

use RuntimeException;

/**
 * Exception carrying the HelloImg API error code and HTTP status.
 */
final class ApiException extends RuntimeException {

	/**
	 * Machine-readable API error code.
	 *
	 * @var string
	 */
	private string $error_code;

	public function __construct( string $message, string $error_code = 'unknown', int $http_status = 0 ) {
		parent::__construct( $message, $http_status );
		$this->error_code = $error_code;
	}

	public function get_error_code(): string {
		return $this->error_code;
	}

	public function get_http_status(): int {
		return $this->getCode();
	}

	/**
	 * Whether the failure is transient (worth retrying later).
	 *
	 * Transient: network problems, server-side timeouts (408), 5xx, and
	 * incomplete jobs. Permanent: invalid_request, invalid_level,
	 * file_too_large, processing failures on corrupt images, etc.
	 *
	 * @return bool
	 */
	public function is_transient(): bool {
		if ( $this->getCode() >= 500 || 408 === $this->getCode() ) {
			return true;
		}

		return in_array( $this->error_code, [ 'network_error', 'timeout', 'incomplete' ], true );
	}
}
