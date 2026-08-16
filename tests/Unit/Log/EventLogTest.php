<?php
/**
 * Tests for the EventLog ring buffer.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Log;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Log\EventLog;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Log\EventLog
 */
final class EventLogTest extends MonkeyTestCase {

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
	 * Stub get_option() for both the plugin options and the log option.
	 *
	 * @param array<int, array<string, mixed>> $existing_log Entries already in the log.
	 * @param bool                             $enabled      Whether logging is enabled.
	 * @return void
	 */
	private function stub_options( array $existing_log, bool $enabled = true ): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $name, $default_value = false ) use ( $existing_log, $enabled ) {
				if ( EventLog::OPTION_NAME === $name ) {
					return $existing_log;
				}
				if ( Options::OPTION_NAME === $name ) {
					return [ 'enable_log' => $enabled ];
				}
				return $default_value;
			}
		);
		Options::clear_cache();
	}

	public function test_on_skipped_prepends_a_skipped_entry(): void {
		$this->stub_options( [ [ 'status' => 'converted' ] ] );

		$captured = null;
		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing(
				static function ( $name, $value ) use ( &$captured ) {
					$captured = $value;
					return true;
				}
			);

		EventLog::on_skipped( '/uploads/photo.jpg', 'no API key' );

		$this->assertIsArray( $captured );
		$this->assertCount( 2, $captured );
		$this->assertSame( EventLog::STATUS_SKIPPED, $captured[0]['status'] );
		$this->assertSame( 'photo.jpg', $captured[0]['file'] );
		$this->assertSame( 'no API key', $captured[0]['reason'] );
		$this->assertSame( 'image/jpeg', $captured[0]['mime'] );
	}

	public function test_ring_buffer_is_capped_at_max_entries(): void {
		$full_log = array_fill( 0, EventLog::MAX_ENTRIES, [ 'status' => 'skipped' ] );
		$this->stub_options( $full_log );

		$captured = null;
		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing(
				static function ( $name, $value ) use ( &$captured ) {
					$captured = $value;
					return true;
				}
			);

		EventLog::on_failed( '/uploads/big.png', 'HTTP 500' );

		$this->assertIsArray( $captured );
		$this->assertCount( EventLog::MAX_ENTRIES, $captured );
		$this->assertSame( EventLog::STATUS_FAILED, $captured[0]['status'] );
	}

	public function test_records_nothing_when_logging_is_disabled(): void {
		$this->stub_options( [], false );

		Functions\expect( 'update_option' )->never();
		Functions\expect( 'add_option' )->never();

		EventLog::on_skipped( '/uploads/photo.jpg', 'auto_convert disabled' );

		$this->assertTrue( true );
	}

	public function test_on_restored_prepends_a_restored_entry(): void {
		$this->stub_options( [] );

		$captured = null;
		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing(
				static function ( $name, $value ) use ( &$captured ) {
					$captured = $value;
					return true;
				}
			);

		EventLog::on_restored( 42, '/uploads/2026/08/photo.jpg' );

		$this->assertIsArray( $captured );
		$this->assertSame( EventLog::STATUS_RESTORED, $captured[0]['status'] );
		$this->assertSame( 'photo.jpg', $captured[0]['file'] );
		$this->assertSame( 'image/jpeg', $captured[0]['mime'] );
	}

	public function test_all_returns_empty_array_for_corrupt_option(): void {
		Functions\when( 'get_option' )->justReturn( 'corrupt-string' );

		$this->assertSame( [], EventLog::all() );
	}
}
