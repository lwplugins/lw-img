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

	public function test_to_data_payload_includes_resize_limits_only_when_positive(): void {
		$request = new OptimizeRequest( '/tmp/a.jpg', 'normal', false, 'avif', 1600, 0 );

		$this->assertSame(
			[
				'level'     => 'normal',
				'keep_exif' => false,
				'convert'   => 'avif',
				'max_width' => 1600,
			],
			$request->to_data_payload()
		);
	}

	/**
	 * @dataProvider provide_formats
	 */
	public function test_valid_format_accepts_only_offered_formats( string $format, bool $expected ): void {
		$this->assertSame( $expected, OptimizeRequest::valid_format( $format ) );
	}

	/**
	 * Output format cases.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function provide_formats(): array {
		return [
			'webp'    => [ 'webp', true ],
			'avif'    => [ 'avif', true ],
			'jxl'     => [ 'jxl', false ],
			'empty'   => [ '', false ],
			'uppercase' => [ 'WEBP', false ],
		];
	}

	public function test_from_options_reads_saved_settings_and_falls_back_on_invalid_values(): void {
		\Brain\Monkey\Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
		\Brain\Monkey\Functions\when( 'get_option' )->justReturn(
			[
				'level'         => 'bogus',
				'output_format' => 'avif',
				'keep_exif'     => true,
				'max_width'     => -5,
				'max_height'    => 1200,
			]
		);
		\LightweightPlugins\Img\Options::clear_cache();

		$request = OptimizeRequest::from_options( '/tmp/a.jpg' );

		$this->assertSame( 'normal', $request->level );
		$this->assertSame( 'avif', $request->convert );
		$this->assertTrue( $request->keep_exif );
		$this->assertSame( 0, $request->max_width );
		$this->assertSame( 1200, $request->max_height );

		\LightweightPlugins\Img\Options::clear_cache();
	}
}
