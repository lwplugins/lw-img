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

	/**
	 * Temp directories created by make_temp_file(), removed in tearDown().
	 *
	 * @var array<int, string>
	 */
	private array $temp_dirs = [];

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
	}

	protected function tearDown(): void {
		Options::clear_cache();
		foreach ( $this->temp_dirs as $dir ) {
			$files = glob( $dir . '/*' );
			array_map( 'unlink', false !== $files ? $files : [] );
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort test cleanup.
		}
		$this->temp_dirs = [];
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
		$this->saved_options(
			[
				'api_key'           => 'himg_x',
				'skip_animated_gif' => true,
			]
		);

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'animated.gif', 'image/gif' ) );
	}

	public function test_converts_animated_gif_by_default(): void {
		$this->saved_options( [ 'api_key' => 'himg_x' ] );

		$detector = new ConvertibleDetector();

		$this->assertTrue( $detector->should_convert( self::FIXTURES . 'animated.gif', 'image/gif' ) );
	}

	public function test_converts_static_gif_despite_animated_skip_rule(): void {
		$this->saved_options(
			[
				'api_key'           => 'himg_x',
				'skip_animated_gif' => true,
			]
		);

		$detector = new ConvertibleDetector();

		$this->assertTrue( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/gif' ) );
	}

	public function test_converts_readable_allowed_image(): void {
		$this->saved_options( [ 'api_key' => 'himg_x' ] );

		$detector = new ConvertibleDetector();

		$this->assertTrue( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/jpeg' ) );
	}

	public function test_skips_files_below_the_min_filesize(): void {
		$this->saved_options(
			[
				'api_key'         => 'himg_x',
				'min_filesize_kb' => 1,
			]
		);

		$detector = new ConvertibleDetector();

		$this->assertFalse( $detector->should_convert( self::FIXTURES . 'static.gif', 'image/jpeg' ) );
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

	public function test_smart_crop_eligible_accepts_an_allowed_mime(): void {
		$this->saved_options( [] );
		$file = $this->make_temp_file( 200 * 1024 );

		$this->assertTrue( ( new ConvertibleDetector() )->smart_crop_eligible( $file, 'image/jpeg' ) );
	}

	public function test_smart_crop_eligible_accepts_webp_although_conversion_skips_it(): void {
		// The conversion pipeline skips already-webp uploads; the crop must
		// not — a native WebP upload still deserves subject-aware thumbs.
		$this->saved_options( [] );
		$file = $this->make_temp_file( 200 * 1024 );

		$this->assertTrue( ( new ConvertibleDetector() )->smart_crop_eligible( $file, 'image/webp' ) );
	}

	public function test_smart_crop_eligible_rejects_non_images(): void {
		$this->saved_options( [] );
		$file = $this->make_temp_file( 10 * 1024 );

		$this->assertFalse( ( new ConvertibleDetector() )->smart_crop_eligible( $file, 'application/pdf' ) );
	}

	public function test_smart_crop_eligible_honours_exclusion_patterns(): void {
		$this->saved_options( [ 'exclusion_patterns' => [ '*.jpg' ] ] );
		$file = $this->make_temp_file( 200 * 1024, 'excluded.jpg' );

		$this->assertFalse( ( new ConvertibleDetector() )->smart_crop_eligible( $file, 'image/jpeg' ) );
	}

	public function test_smart_crop_eligible_honours_the_size_ceiling(): void {
		// max_filesize_mb caps what is sent to the API, and smart crop
		// re-sends the main file once per size — the cap must hold here too.
		$this->saved_options( [ 'max_filesize_mb' => 1 ] );
		$file = $this->make_temp_file( 2 * 1024 * 1024 );

		$this->assertFalse( ( new ConvertibleDetector() )->smart_crop_eligible( $file, 'image/jpeg' ) );
	}

	/**
	 * Create a temp file of a given size for filesystem-dependent checks
	 * (is_readable(), filesize()) that Brain Monkey cannot stub.
	 *
	 * @param int    $bytes File size in bytes.
	 * @param string $name  File name.
	 * @return string Absolute path to the created file.
	 */
	private function make_temp_file( int $bytes, string $name = 'photo.jpg' ): string {
		$dir              = sys_get_temp_dir() . '/lw-img-test-' . uniqid();
		mkdir( $dir );
		$path             = $dir . '/' . $name;
		file_put_contents( $path, str_repeat( 'x', $bytes ) );
		$this->temp_dirs[] = $dir;
		return $path;
	}
}
