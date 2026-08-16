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
}
