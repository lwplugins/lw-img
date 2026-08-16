<?php
/**
 * Tests for the Options class (defaults, backfill, caching).
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Options;

/**
 * @covers \LightweightPlugins\Img\Options
 */
final class OptionsTest extends MonkeyTestCase {

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

	public function test_get_falls_back_to_default_when_nothing_saved(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$this->assertSame( 'normal', Options::get( 'level' ) );
		$this->assertTrue( (bool) Options::get( 'auto_convert' ) );
		$this->assertSame( 10, Options::get( 'max_filesize_mb' ) );
	}

	public function test_get_all_backfills_keys_missing_from_older_saved_data(): void {
		Functions\when( 'get_option' )->justReturn( [ 'level' => 'ultra' ] );

		$options = Options::get_all();

		$this->assertSame( 'ultra', $options['level'] );
		$this->assertSame( '', $options['api_key'] );
		$this->assertTrue( (bool) $options['skip_already_webp'] );
	}

	public function test_get_returns_null_for_unknown_key(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$this->assertNull( Options::get( 'nonexistent_key' ) );
	}

	public function test_set_merges_value_and_persists(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$captured = null;
		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing(
				static function ( $name, $value ) use ( &$captured ) {
					$captured = $value;
					return true;
				}
			);

		$this->assertTrue( Options::set( 'level', 'aggressive' ) );
		$this->assertIsArray( $captured );
		$this->assertSame( 'aggressive', $captured['level'] );
		$this->assertSame( 'aggressive', Options::get( 'level' ) );
	}
}
