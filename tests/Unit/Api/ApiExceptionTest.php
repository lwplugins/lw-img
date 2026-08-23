<?php
/**
 * Tests for the transient/permanent failure classification.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Api;

use LightweightPlugins\Img\Api\ApiException;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Api\ApiException
 */
final class ApiExceptionTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_failures
	 */
	public function test_transient_classification( string $code, int $status, bool $expected ): void {
		$exception = new ApiException( 'msg', $code, $status );

		$this->assertSame( $expected, $exception->is_transient() );
	}

	/**
	 * @dataProvider provide_quota_cases
	 */
	public function test_quota_classification( string $code, int $status, bool $expected ): void {
		$exception = new ApiException( 'msg', $code, $status );

		$this->assertSame( $expected, $exception->is_quota() );
	}

	/**
	 * @return array<string, array{string, int, bool}>
	 */
	public static function provide_quota_cases(): array {
		return [
			'payment required (402)'  => [ 'unknown', 402, true ],
			'insufficient balance'    => [ 'insufficient_balance', 200, true ],
			'rate limit is not quota' => [ 'rate_limited', 429, false ],
			'server error not quota'  => [ 'http_500', 500, false ],
		];
	}

	/**
	 * @dataProvider provide_auth_cases
	 */
	public function test_auth_classification( string $code, int $status, bool $expected ): void {
		$this->assertSame( $expected, ( new ApiException( 'x', $code, $status ) )->is_auth() );
	}

	/**
	 * The live API answers a bad key with 401 and the flat
	 * {"error":"invalid api key"} body, which error_parts() maps to the
	 * synthetic http_401 code — so the status is the primary signal.
	 *
	 * @return array<string, array{string, int, bool}>
	 */
	public static function provide_auth_cases(): array {
		return [
			'bad key (401, flat body)'  => [ 'http_401', 401, true ],
			'forbidden (403)'           => [ 'http_403', 403, true ],
			'unauthorized by code'      => [ 'unauthorized', 0, true ],
			'invalid_key by code'       => [ 'invalid_key', 0, true ],
			'quota (402) not auth'      => [ 'insufficient_balance', 402, false ],
			'timeout not auth'          => [ 'timeout', 408, false ],
			'server error not auth'     => [ 'http_500', 500, false ],
		];
	}

	/**
	 * Failure cases based on the documented HelloImg error codes.
	 *
	 * @return array<string, array{string, int, bool}>
	 */
	public static function provide_failures(): array {
		return [
			'network error'          => [ 'network_error', 0, true ],
			'sync timeout (408)'     => [ 'timeout', 408, true ],
			'rate limited (429)'     => [ 'unknown', 429, true ],
			'rate limited by code'   => [ 'rate_limited', 200, true ],
			'server error (500)'     => [ 'http_500', 500, true ],
			'incomplete job'         => [ 'incomplete', 200, true ],
			'invalid request (400)'  => [ 'invalid_request', 400, false ],
			'invalid level (400)'    => [ 'invalid_level', 400, false ],
			'file too large (413)'   => [ 'file_too_large', 413, false ],
			'processing failed (422)' => [ 'processing_failed', 422, false ],
			'unauthorized (401)'     => [ 'unauthorized', 401, false ],
		];
	}
}
