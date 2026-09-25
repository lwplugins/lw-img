<?php
/**
 * Tests for partial, atomic settings saves.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Settings\SettingsStore;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Settings\SettingsStore
 * @covers \LightweightPlugins\Img\Settings\SettingsTypes
 */
final class SettingsStoreTest extends MonkeyTestCase {

	/**
	 * The stored option row.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * How many times the option was written.
	 *
	 * @var int
	 */
	private int $writes = 0;

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\stubTranslationFunctions();

		$this->stored = [
			'api_key'    => 'himg_storedkey12345678',
			'debug_mode' => true,
			'mime_types' => [ 'image/jpeg' ],
			'keep_exif'  => false,
		];
		$this->writes = 0;

		Functions\when( 'get_option' )->alias( fn ( $name, $default_value = false ) => Options::OPTION_NAME === $name ? $this->stored : $default_value );
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ): bool {
				$this->stored = $value;
				++$this->writes;
				return true;
			}
		);
		Functions\when( 'wp_parse_args' )->alias( static fn ( $args, $defaults ): array => array_merge( (array) $defaults, (array) $args ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( $value ): string => trim( (string) $value ) );
		Functions\when( 'delete_transient' )->justReturn( true );
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	/**
	 * Regression: the classic form reset every setting it did not post —
	 * debug_mode (no control) went back to false on every save.
	 */
	public function test_partial_save_keeps_settings_that_were_not_sent(): void {
		$errors = SettingsStore::save( [ 'keep_exif' => true ] );

		$this->assertSame( [], $errors );
		$this->assertTrue( $this->stored['keep_exif'] );
		$this->assertTrue( $this->stored['debug_mode'] );
		$this->assertSame( [ 'image/jpeg' ], $this->stored['mime_types'] );
		$this->assertSame( 'himg_storedkey12345678', $this->stored['api_key'] );
	}

	public function test_nothing_is_saved_when_any_field_is_invalid(): void {
		$errors = SettingsStore::save(
			[
				'keep_exif'       => true,
				'request_timeout' => '',
			]
		);

		$this->assertArrayHasKey( 'request_timeout', $errors );
		$this->assertSame( 0, $this->writes );
	}

	public function test_legacy_keys_are_not_carried_into_the_saved_row(): void {
		$this->stored['exclusion_patterns'] = [ '*.png' ];

		SettingsStore::save( [ 'keep_exif' => true ] );

		$this->assertArrayNotHasKey( 'exclusion_patterns', $this->stored );
	}

	public function test_current_masks_the_api_key(): void {
		$options = SettingsStore::current();

		$this->assertSame(
			[
				'set'    => true,
				'source' => 'option',
				'hint'   => 'himg_…5678',
			],
			$options['api_key']
		);
		$this->assertStringNotContainsString( 'storedkey', (string) json_encode( $options ) );
	}

	public function test_current_types_every_value(): void {
		$this->stored['max_width']     = '1200';
		$this->stored['pattern_rules'] = [
			[
				'pattern' => '*.png',
				'action'  => 'exclude',
			],
		];

		$options = SettingsStore::current();

		$this->assertSame( 1200, $options['max_width'] );
		$this->assertTrue( $options['debug_mode'] );
		$this->assertSame(
			[
				[
					'pattern' => '*.png',
					'action'  => 'exclude',
					'value'   => '',
				],
			],
			$options['pattern_rules']
		);
	}

	public function test_an_empty_body_writes_nothing(): void {
		$this->assertSame( [], SettingsStore::save( [] ) );
		$this->assertSame( 0, $this->writes );
	}
}
