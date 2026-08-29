<?php
/**
 * Tests for the CLI --level option parsing.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\CLI;

use LightweightPlugins\Img\CLI\LevelOption;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\CLI\LevelOption
 */
final class LevelOptionTest extends MonkeyTestCase {

	public function test_absent_level_means_no_override(): void {
		$this->assertNull( LevelOption::normalize( null ) );
	}

	/**
	 * @dataProvider provide_valid_levels
	 */
	public function test_valid_levels_pass_through_lowercased( string $raw, string $expected ): void {
		$this->assertSame( $expected, LevelOption::normalize( $raw ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_valid_levels(): array {
		return [
			'lossless'        => [ 'lossless', 'lossless' ],
			'normal'          => [ 'normal', 'normal' ],
			'aggressive'      => [ 'aggressive', 'aggressive' ],
			'ultra'           => [ 'ultra', 'ultra' ],
			'mixed case'      => [ 'Lossless', 'lossless' ],
		];
	}

	public function test_unknown_level_is_rejected(): void {
		\Brain\Monkey\Functions\when( 'sanitize_key' )->returnArg();
		$this->expectException( \InvalidArgumentException::class );

		LevelOption::normalize( 'bogus' );
	}
}
