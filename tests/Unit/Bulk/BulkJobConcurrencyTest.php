<?php
/**
 * Lost-update protection for the bulk job counters.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Bulk;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Bulk\BulkJob;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * Cron ticks, poll assists and parallel WP-CLI workers all record into the
 * same option row. Each process keeps its own in-request copy of the row
 * (the object cache), so a counter update must re-read the stored row under
 * a lock instead of incrementing a stale copy.
 *
 * The model here: `$db` is the row as stored, `$cache` is this process's
 * in-request copy that get_option() serves once primed.
 */
final class BulkJobConcurrencyTest extends MonkeyTestCase {

	/**
	 * The stored row.
	 *
	 * @var array<string, mixed>
	 */
	private array $db = [];

	/**
	 * This process's cached copy (null = not primed).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Advisory-lock calls seen by the fake $wpdb.
	 *
	 * @var array<int, string>
	 */
	public array $lock_calls = [];

	protected function setUp(): void {
		parent::setUp();

		$this->db    = [
			'state'     => BulkJob::STATE_RUNNING,
			'total'     => 10,
			'optimized' => 0,
			'skipped'   => 0,
			'failed'    => 0,
		];
		$this->cache = null;

		Functions\when( 'get_option' )->alias(
			function ( string $name, $default_value = false ) {
				if ( BulkJob::OPTION_NAME !== $name ) {
					return $default_value;
				}
				if ( null === $this->cache ) {
					$this->cache = $this->db;
				}
				return $this->cache;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $name, $value ): bool {
				if ( BulkJob::OPTION_NAME === $name ) {
					$this->db    = $value;
					$this->cache = $value;
				}
				return true;
			}
		);
		Functions\when( 'wp_cache_delete' )->alias(
			function ( string $key, string $group = '' ): bool {
				if ( BulkJob::OPTION_NAME === $key && 'options' === $group ) {
					$this->cache = null;
				}
				return true;
			}
		);

		$test            = $this;
		$GLOBALS['wpdb'] = new class( $test ) {
			/**
			 * Owning test.
			 *
			 * @var BulkJobConcurrencyTest
			 */
			private BulkJobConcurrencyTest $test;

			/**
			 * @param BulkJobConcurrencyTest $test Owning test.
			 */
			public function __construct( BulkJobConcurrencyTest $test ) {
				$this->test = $test;
			}

			/**
			 * @param string $sql     Query with placeholders.
			 * @param mixed  ...$args Values.
			 */
			public function prepare( string $sql, ...$args ): string {
				return vsprintf( str_replace( '%s', "'%s'", $sql ), $args );
			}

			/**
			 * @param string $sql Query.
			 */
			public function get_var( string $sql ): string {
				$this->test->lock_calls[] = $sql;
				return '1';
			}
		};
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_record_keeps_an_increment_written_by_another_process(): void {
		// This process read the row earlier (e.g. its is_running() check)...
		$this->assertTrue( BulkJob::is_running() );
		// ...then another worker recorded an image in the meantime.
		$this->db['optimized'] = 1;

		BulkJob::record( 'optimized' );

		$this->assertSame( 2, $this->db['optimized'] );
	}

	public function test_record_releases_the_lock_it_took(): void {
		BulkJob::record( 'skipped' );

		$this->assertCount( 2, $this->lock_calls );
		$this->assertStringContainsString( 'GET_LOCK', $this->lock_calls[0] );
		$this->assertStringContainsString( 'RELEASE_LOCK', $this->lock_calls[1] );
	}

	public function test_finish_does_not_roll_back_counters_written_meanwhile(): void {
		$this->assertTrue( BulkJob::is_running() );
		$this->db['failed'] = 3;

		BulkJob::finish( BulkJob::STATE_DONE );

		$this->assertSame( 3, $this->db['failed'] );
		$this->assertSame( BulkJob::STATE_DONE, $this->db['state'] );
	}

	/**
	 * A worker that saw a running job, then drained the queue, must not
	 * turn a run the user cancelled meanwhile into "done".
	 */
	public function test_finish_done_does_not_overwrite_a_cancel_made_meanwhile(): void {
		$this->assertTrue( BulkJob::is_running() );
		$this->db['state'] = BulkJob::STATE_CANCELLED;

		BulkJob::finish( BulkJob::STATE_DONE );

		$this->assertSame( BulkJob::STATE_CANCELLED, $this->db['state'] );
	}

	public function test_finish_done_keeps_a_halt_recorded_meanwhile(): void {
		$this->assertTrue( BulkJob::is_running() );
		$this->db['state'] = BulkJob::STATE_HALTED;
		$this->db['halt']  = [
			'reason' => 'quota',
			'detail' => 'Insufficient balance',
		];

		BulkJob::finish( BulkJob::STATE_DONE );

		$this->assertSame( BulkJob::STATE_HALTED, $this->db['state'] );
		$this->assertSame( 'quota', $this->db['halt']['reason'] );
	}

	public function test_mark_retried_leaves_an_ended_run_alone(): void {
		$this->assertTrue( BulkJob::is_running() );
		$this->db['state']  = BulkJob::STATE_CANCELLED;
		$this->db['failed'] = 4;

		BulkJob::mark_retried( 3 );

		$this->assertSame( 4, $this->db['failed'] );
		$this->assertArrayNotHasKey( 'retried', $this->db );
	}
}
