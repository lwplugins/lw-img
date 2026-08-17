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
