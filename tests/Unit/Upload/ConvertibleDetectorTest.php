<?php
/**
 * Tests for the ConvertibleDetector skip rules.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\ConvertibleDetector;

/**
 * @covers \LightweightPlugins\Img\Upload\ConvertibleDetector
 */
final class ConvertibleDetectorTest extends MonkeyTestCase {

	private const FIXTURES = __DIR__ . '/../../Fixtures/';

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	/**
	 * Stub the saved options for Options::get() lookups.
	 *
	 * @param array<string, mixed> $overrides Saved option values overriding the defaults.
	 * @return void
	 */
	private function saved_options( array $overrides ): void {
		Functions\when( 'get_option' )->justReturn( $overrides );
		Options::clear_cache();
	}

	public function test_skips_when_auto_convert_is_disabled(): void {
		$this->saved_options(
			[
				'auto_convert' => false,
				'api_key'      => 'himg_x',
			]
		);

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/jpeg' ) );
	}

	public function test_skips_when_api_key_is_missing(): void {
		$this->saved_options( [ 'api_key' => '' ] );

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/jpeg' ) );
	}

	public function test_skips_non_image_mime_types(): void {
		$this->saved_options( [ 'api_key' => 'himg_x' ] );

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'static.gif', 'application/pdf' ) );
	}

	public function test_skips_already_webp_uploads(): void {
		$this->saved_options( [ 'api_key' => 'himg_x' ] );

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/webp' ) );
	}

	public function test_skips_mime_types_outside_the_allowed_list(): void {
		$this->saved_options( [ 'api_key' => 'himg_x' ] );

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/x-icon' ) );
	}

	public function test_skips_unreadable_files(): void {
		$this->saved_options( [ 'api_key' => 'himg_x' ] );

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'does-not-exist.jpg', 'image/jpeg' ) );
	}

	public function test_skips_animated_gif_when_skip_rule_is_on(): void {
		$this->saved_options( [ 'api_key' => 'himg_x' ] );

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'animated.gif', 'image/gif' ) );
	}

	public function test_converts_static_gif_despite_animated_skip_rule(): void {
		$this->saved_options( [ 'api_key' => 'himg_x' ] );

		$detector = new ConvertibleDetector();

		$this->assertTrue( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/gif' ) );
	}

	public function test_converts_readable_allowed_image(): void {
		$this->saved_options( [ 'api_key' => 'himg_x' ] );

		$detector = new ConvertibleDetector();

		$this->assertTrue( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/jpeg' ) );
	}

	public function test_skips_files_matching_an_exclusion_pattern(): void {
		$this->saved_options(
			[
				'api_key'            => 'himg_x',
				'exclusion_patterns' => [ 'static.*' ],
			]
		);

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/jpeg' ) );
	}

	public function test_on_demand_conversion_ignores_the_auto_convert_toggle(): void {
		$this->saved_options(
			[
				'auto_convert' => false,
				'api_key'      => 'himg_x',
			]
		);

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/jpeg' ) );
		$this->assertTrue( $detector->should_convert_on_demand( self::FIXTURES . 'static.gif', 'image/jpeg' ) );
	}
}
