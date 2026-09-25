<?php
/**
 * Tests for the bulk start gate.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Bulk;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Bulk\BulkGate;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Bulk\BulkGate
 */
final class BulkGateTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_states
	 *
	 * @param array<int, string> $expected Expected reason codes.
	 */
	public function test_reasons( bool $running, bool $key_set, ?bool $redirects, int $pending, array $expected ): void {
		$this->assertSame( $expected, BulkGate::reasons( $running, $key_set, $redirects, $pending ) );
	}

	/**
	 * @return array<string, array{bool, bool, bool|null, int, array<int, string>}>
	 */
	public static function provide_states(): array {
		return [
			'ready'                    => [ false, true, true, 10, [] ],
			'unverifiable probe'       => [ false, true, null, 10, [] ],
			'running wins'             => [ true, false, false, 0, [ 'running' ] ],
			'no key'                   => [ false, false, null, 10, [ 'no_key' ] ],
			'server swallows 404s'     => [ false, true, false, 10, [ 'redirects' ] ],
			'nothing left'             => [ false, true, true, 0, [ 'nothing_pending' ] ],
			'several at once'          => [ false, false, false, 0, [ 'no_key', 'redirects', 'nothing_pending' ] ],
		];
	}

	public function test_describe_carries_a_message_per_reason(): void {
		Functions\stubTranslationFunctions();

		$gate = BulkGate::describe( [ 'no_key' ] );

		$this->assertFalse( $gate['can_start'] );
		$this->assertSame( 'no_key', $gate['reasons'][0]['code'] );
		$this->assertNotSame( '', $gate['reasons'][0]['message'] );
	}
}
