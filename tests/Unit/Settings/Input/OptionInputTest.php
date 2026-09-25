<?php
/**
 * Tests for the settings input layer.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Settings\Input;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Settings\Input\OptionInput;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Settings\Input\OptionInput
 * @covers \LightweightPlugins\Img\Settings\Input\BoolParser
 * @covers \LightweightPlugins\Img\Settings\Input\IntParser
 * @covers \LightweightPlugins\Img\Settings\Input\EnumParser
 * @covers \LightweightPlugins\Img\Settings\Input\ApiKeyParser
 * @covers \LightweightPlugins\Img\Settings\Input\NameListParser
 */
final class OptionInputTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'sanitize_text_field' )->alias( static fn ( $value ): string => trim( (string) $value ) );
	}

	/**
	 * @dataProvider provide_valid_values
	 */
	public function test_accepts_valid_values( string $key, mixed $raw, mixed $expected ): void {
		$report = OptionInput::parse( [ $key => $raw ], false );

		$this->assertSame( [ $key => $expected ], $report->values() );
	}

	/**
	 * @return array<string, array{string, mixed, mixed}>
	 */
	public static function provide_valid_values(): array {
		return [
			'bool true'            => [ 'keep_exif', true, true ],
			'json string false'    => [ 'auto_convert', 'false', false ],
			'numeric string one'   => [ 'backup_enabled', '1', true ],
			'int in range'         => [ 'request_timeout', 45, 45 ],
			'numeric string'       => [ 'backup_retention_days', '0', 0 ],
			'enum level'           => [ 'level', 'ULTRA', 'ultra' ],
			'enum format'          => [ 'output_format', 'avif', 'avif' ],
			'enum speed'           => [ 'bulk_speed', 'gentle', 'gentle' ],
			'api key trimmed'      => [ 'api_key', '  himg_abc123  ', 'himg_abc123' ],
			'empty key clears'     => [ 'api_key', '', '' ],
			'sizes deduplicated'   => [ 'smartcrop_sizes', [ 'thumbnail', 'Thumbnail', 'shop_single' ], [ 'thumbnail', 'shop_single' ] ],
			'no sizes'             => [ 'smartcrop_sizes', [], [] ],
			'mime list'            => [ 'mime_types', [ 'image/jpeg', 'image/png' ], [ 'image/jpeg', 'image/png' ] ],
			'debug mode (no UI)'   => [ 'debug_mode', true, true ],
		];
	}

	/**
	 * A blank or negative number used to be saved as the clamp minimum
	 * (absint('') = 0, absint(-5) = 5): it must be refused instead.
	 *
	 * @dataProvider provide_invalid_values
	 */
	public function test_refuses_invalid_values( string $key, mixed $raw ): void {
		$report = OptionInput::parse( [ $key => $raw ], false );

		$this->assertSame( [], $report->values() );
		$this->assertArrayHasKey( $key, $report->errors() );
	}

	/**
	 * @return array<string, array{string, mixed}>
	 */
	public static function provide_invalid_values(): array {
		return [
			'blank timeout'        => [ 'request_timeout', '' ],
			'blank max size'       => [ 'max_filesize_mb', '' ],
			'blank retention'      => [ 'backup_retention_days', '' ],
			'negative width'       => [ 'max_width', -5 ],
			'above range'          => [ 'max_filesize_mb', 11 ],
			'below range'          => [ 'request_timeout', 4 ],
			'decimal'              => [ 'max_height', '1.5' ],
			'bool word'            => [ 'keep_exif', 'maybe' ],
			'unknown level'        => [ 'level', 'extreme' ],
			'key with a space'     => [ 'api_key', 'himg abc' ],
			'key not text'         => [ 'api_key', 123 ],
			'size with a space'    => [ 'smartcrop_sizes', [ 'post thumbnail' ] ],
			'sizes not a list'     => [ 'smartcrop_sizes', 'thumbnail' ],
			'mime not an image'    => [ 'mime_types', [ 'application/pdf' ] ],
			'empty mime list'      => [ 'mime_types', [] ],
		];
	}

	public function test_a_pinned_api_key_cannot_be_written(): void {
		$report = OptionInput::parse( [ 'api_key' => 'himg_new' ], true );

		$this->assertSame( [], $report->values() );
		$this->assertStringContainsString( 'LW_IMG_API_KEY', $report->errors()['api_key'][0] );
	}

	public function test_unknown_keys_are_set_aside(): void {
		$report = OptionInput::parse( [ 'exclusion_patterns' => [ '*.png' ] ], false );

		$this->assertSame( [], $report->values() );
		$this->assertFalse( $report->has_errors() );
		$this->assertSame( [ 'exclusion_patterns' ], $report->unknown() );
	}

	public function test_valid_fields_are_reported_next_to_invalid_ones(): void {
		$report = OptionInput::parse(
			[
				'keep_exif'       => true,
				'request_timeout' => '',
			],
			false
		);

		$this->assertTrue( $report->has_errors() );
		$this->assertSame( [ 'request_timeout' ], array_keys( $report->errors() ) );
	}
}
