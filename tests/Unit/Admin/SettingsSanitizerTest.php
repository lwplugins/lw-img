<?php
/**
 * Tests for the SettingsSanitizer type-aware sanitization rules.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Admin\SettingsSanitizer;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Admin\SettingsSanitizer
 */
final class SettingsSanitizerTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
		Functions\when( 'absint' )->alias( static fn ( $value ): int => abs( (int) $value ) );
		Functions\when( 'sanitize_text_field' )->alias(
			static fn ( $value ): string => trim( (string) $value )
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn ( $key ): string => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) )
		);
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_checked_checkbox_becomes_true(): void {
		$sanitized = SettingsSanitizer::sanitize( [ 'keep_exif' => '1' ] );

		$this->assertTrue( $sanitized['keep_exif'] );
	}

	public function test_missing_checkbox_becomes_false(): void {
		$sanitized = SettingsSanitizer::sanitize( [] );

		$this->assertFalse( $sanitized['keep_exif'] );
		$this->assertFalse( $sanitized['auto_convert'] );
	}

	public function test_integer_input_is_absint_sanitized(): void {
		$sanitized = SettingsSanitizer::sanitize( [ 'max_filesize_mb' => '-7' ] );

		$this->assertSame( 7, $sanitized['max_filesize_mb'] );
	}

	public function test_missing_integer_keeps_current_value(): void {
		$sanitized = SettingsSanitizer::sanitize( [] );

		$this->assertSame( 10, $sanitized['max_filesize_mb'] );
	}

	public function test_valid_level_is_kept(): void {
		$this->assertSame( 'ultra', SettingsSanitizer::sanitize( [ 'level' => 'ultra' ] )['level'] );
		$this->assertSame( 'lossless', SettingsSanitizer::sanitize( [ 'level' => 'lossless' ] )['level'] );
	}

	public function test_unknown_level_falls_back_to_current_value(): void {
		$sanitized = SettingsSanitizer::sanitize( [ 'level' => 'bogus' ] );

		$this->assertSame( 'normal', $sanitized['level'] );
	}

	public function test_output_format_is_whitelisted(): void {
		$this->assertSame( 'avif', SettingsSanitizer::sanitize( [ 'output_format' => 'avif' ] )['output_format'] );
		$this->assertSame( 'webp', SettingsSanitizer::sanitize( [ 'output_format' => 'jxl' ] )['output_format'] );
	}

	public function test_array_input_is_filtered_and_sanitized(): void {
		$sanitized = SettingsSanitizer::sanitize(
			[ 'mime_types' => [ ' image/jpeg ', '', 'image/png' ] ]
		);

		$this->assertSame( [ 'image/jpeg', 'image/png' ], $sanitized['mime_types'] );
	}

	public function test_non_array_input_for_array_option_keeps_current_value(): void {
		$sanitized = SettingsSanitizer::sanitize( [ 'mime_types' => 'not-an-array' ] );

		$this->assertSame( Options::get_defaults()['mime_types'], $sanitized['mime_types'] );
	}

	public function test_string_input_is_text_sanitized(): void {
		$sanitized = SettingsSanitizer::sanitize( [ 'api_key' => '  himg_abc123  ' ] );

		$this->assertSame( 'himg_abc123', $sanitized['api_key'] );
	}

	public function test_exclusion_patterns_textarea_is_split_into_a_clean_list(): void {
		$sanitized = SettingsSanitizer::sanitize(
			[ 'exclusion_patterns' => "*-original.jpg\r\n\n  2026/08/*  \n\n" ]
		);

		$this->assertSame( [ '*-original.jpg', '2026/08/*' ], $sanitized['exclusion_patterns'] );
	}

	public function test_exclusion_patterns_default_to_empty_list(): void {
		$sanitized = SettingsSanitizer::sanitize( [] );

		$this->assertSame( [], $sanitized['exclusion_patterns'] );
	}

	public function test_smartcrop_sizes_keeps_valid_size_names(): void {
		$result = SettingsSanitizer::sanitize(
			[ 'smartcrop_sizes' => [ 'woocommerce_thumbnail', 'shop_catalog' ] ]
		);

		$this->assertSame( [ 'woocommerce_thumbnail', 'shop_catalog' ], $result['smartcrop_sizes'] );
	}

	public function test_smartcrop_sizes_sanitizes_hostile_names(): void {
		$result = SettingsSanitizer::sanitize(
			[ 'smartcrop_sizes' => [ '<script>alert(1)</script>', 'Thumb Nail!', '' ] ]
		);

		// sanitize_key lowercases and strips everything outside [a-z0-9_-];
		// empties drop out entirely.
		$this->assertSame( [ 'scriptalert1script', 'thumbnail' ], $result['smartcrop_sizes'] );
	}

	public function test_smartcrop_sizes_empty_string_means_none_selected(): void {
		// The checkbox group posts a hidden '' when every box is unchecked.
		// Without this, unchecking everything would silently restore the old
		// selection from the fallback.
		$result = SettingsSanitizer::sanitize( [ 'smartcrop_sizes' => '' ] );

		$this->assertSame( [], $result['smartcrop_sizes'] );
	}

	public function test_smartcrop_enabled_defaults_off_and_absent_means_off(): void {
		$result = SettingsSanitizer::sanitize( [] );

		$this->assertFalse( $result['smartcrop_enabled'] );
	}
}
