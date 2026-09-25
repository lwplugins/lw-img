<?php
/**
 * Tests for the bulk-run halt reasons.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Bulk;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Api\ApiException;
use LightweightPlugins\Img\Bulk\HaltReason;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Bulk\HaltReason
 */
final class HaltReasonTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_exceptions
	 */
	public function test_classifies_run_stopping_api_errors( ApiException $error, ?string $expected ): void {
		$this->assertSame( $expected, HaltReason::from_exception( $error ) );
	}

	/**
	 * @return array<string, array{ApiException, string|null}>
	 */
	public static function provide_exceptions(): array {
		return [
			'402 payment required' => [ new ApiException( 'No credit', 'insufficient_balance', 402 ), HaltReason::QUOTA ],
			'balance code only'    => [ new ApiException( 'No credit', 'insufficient_balance', 400 ), HaltReason::QUOTA ],
			'401 bad key'          => [ new ApiException( 'Unauthorized', 'http_401', 401 ), HaltReason::AUTH ],
			'403 other site'       => [ new ApiException( 'Forbidden', 'forbidden', 403 ), HaltReason::AUTH ],
			'timeout is not halt'  => [ new ApiException( 'Timed out', 'timeout', 408 ), null ],
		];
	}

	/**
	 * @dataProvider provide_codes
	 */
	public function test_every_reason_has_a_human_message( string $code ): void {
		Functions\when( '__' )->returnArg();

		$this->assertNotSame( '', HaltReason::message( $code ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provide_codes(): array {
		return [
			'no key'  => [ HaltReason::NO_KEY ],
			'quota'   => [ HaltReason::QUOTA ],
			'auth'    => [ HaltReason::AUTH ],
			'unknown' => [ 'something-else' ],
		];
	}
}
