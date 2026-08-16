<?php
/**
 * Tests for the OptimizeRequest value object.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Api;

use LightweightPlugins\Img\Api\OptimizeRequest;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Api\OptimizeRequest
 */
final class OptimizeRequestTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_levels
	 */
	public function test_valid_level_accepts_only_known_levels( string $level, bool $expected ): void {
		$this->assertSame( $expected, OptimizeRequest::valid_level( $level ) );
	}

	/**
	 * Level validity cases.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function provide_levels(): array {
		return [
			'normal'       => [ 'normal', true ],
			'aggressive'   => [ 'aggressive', true ],
			'ultra'        => [ 'ultra', true ],
			'empty string' => [ '', false ],
			'unknown'      => [ 'extreme', false ],
			'case matters' => [ 'Normal', false ],
		];
	}

	public function test_to_data_payload_includes_convert_when_set(): void {
		$request = new OptimizeRequest( '/tmp/a.jpg', 'aggressive', true, 'webp' );

		$this->assertSame(
			[
				'level'     => 'aggressive',
				'keep_exif' => true,
				'convert'   => 'webp',
			],
			$request->to_data_payload()
		);
	}

	public function test_to_data_payload_omits_convert_when_null(): void {
		$request = new OptimizeRequest( '/tmp/a.jpg', 'normal', false, null );

		$this->assertSame(
			[
				'level'     => 'normal',
				'keep_exif' => false,
			],
			$request->to_data_payload()
		);
	}
}
