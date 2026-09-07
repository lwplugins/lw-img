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
			'lossless'     => [ 'lossless', true ],
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

	public function test_smart_crop_payload_carries_resize_with_both_dimensions(): void {
		$request = OptimizeRequest::for_smart_crop( '/tmp/a.webp', 300, 300, 'webp', 'normal', false );

		$payload = $request->to_data_payload();

		$this->assertSame(
			[
				'width'     => 300,
				'height'    => 300,
				'smartcrop' => true,
			],
			$payload['resize']
		);
		$this->assertSame( 'webp', $payload['convert'] );
	}

	public function test_smart_crop_payload_never_emits_the_fit_inside_box(): void {
		// resize.smartcrop and max_width/max_height are mutually exclusive on
		// the API: the box means "fit inside", the crop means "exactly this".
		$payload = OptimizeRequest::for_smart_crop( '/tmp/a.webp', 300, 300, 'webp', 'normal', false )->to_data_payload();

		$this->assertArrayNotHasKey( 'max_width', $payload );
		$this->assertArrayNotHasKey( 'max_height', $payload );
	}

	public function test_plain_requests_emit_no_resize_object(): void {
		$payload = ( new OptimizeRequest( '/tmp/a.jpg' ) )->to_data_payload();

		$this->assertArrayNotHasKey( 'resize', $payload );
	}

	/**
	 * Saved-options stub shared by the rule tests.
	 *
	 * @param array<string, mixed> $saved Stored options.
	 * @return void
	 */
	private function saved( array $saved ): void {
		\Brain\Monkey\Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
		\Brain\Monkey\Functions\when( 'get_option' )->justReturn( $saved );
		\LightweightPlugins\Img\Options::clear_cache();
	}

	public function test_keep_size_rule_drops_the_resize_box(): void {
		$this->saved(
			[
				'max_width'     => 1600,
				'max_height'    => 1600,
				'pattern_rules' => [ [ 'pattern' => '*-full.jpg', 'action' => 'keep_size', 'value' => '' ] ],
			]
		);

		$kept    = OptimizeRequest::from_options( '/up/hero-full.jpg' );
		$resized = OptimizeRequest::from_options( '/up/hero.jpg' );

		$this->assertSame( 0, $kept->max_width );
		$this->assertSame( 0, $kept->max_height );
		$this->assertSame( 1600, $resized->max_width );
		\LightweightPlugins\Img\Options::clear_cache();
	}

	public function test_level_rule_overrides_the_saved_level_but_not_an_explicit_override(): void {
		$this->saved(
			[
				'level'         => 'normal',
				'pattern_rules' => [ [ 'pattern' => 'logo-*', 'action' => 'level', 'value' => 'lossless' ] ],
			]
		);

		$this->assertSame( 'lossless', OptimizeRequest::from_options( '/up/logo-a.png' )->level );
		$this->assertSame( 'normal', OptimizeRequest::from_options( '/up/photo.jpg' )->level );
		$this->assertSame( 'ultra', OptimizeRequest::from_options( '/up/logo-a.png', null, 'ultra' )->level );
		\LightweightPlugins\Img\Options::clear_cache();
	}

	public function test_keep_exif_rule_only_turns_exif_on(): void {
		$this->saved(
			[
				'keep_exif'     => false,
				'pattern_rules' => [ [ 'pattern' => 'photo-*', 'action' => 'keep_exif', 'value' => '' ] ],
			]
		);

		$this->assertTrue( OptimizeRequest::from_options( '/up/photo-1.jpg' )->keep_exif );
		$this->assertFalse( OptimizeRequest::from_options( '/up/other.jpg' )->keep_exif );
		\LightweightPlugins\Img\Options::clear_cache();
	}

	public function test_filtered_applies_the_request_args_filter(): void {
		$this->saved( [ 'max_width' => 1600 ] );
		\Brain\Monkey\Filters\expectApplied( 'lw_img_optimize_request_args' )
			->once()
			->andReturnUsing(
				static fn ( array $args ): array => array_merge( $args, [ 'max_width' => 0, 'level' => 'ultra' ] )
			);

		$request = OptimizeRequest::filtered( '/up/a.jpg' );

		$this->assertSame( 0, $request->max_width );
		$this->assertSame( 'ultra', $request->level );
		\LightweightPlugins\Img\Options::clear_cache();
	}
}
