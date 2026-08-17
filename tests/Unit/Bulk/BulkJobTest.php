<?php
/**
 * Tests for the bulk job record lifecycle.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Bulk;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Bulk\BulkJob;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Bulk\BulkJob
 */
final class BulkJobTest extends MonkeyTestCase {

	/**
	 * In-memory stand-in for the option row.
	 *
	 * @var mixed
	 */
	private mixed $stored = [];

	protected function setUp(): void {
		parent::setUp();
		$this->stored = [];

		Functions\when( 'get_option' )->alias( fn () => $this->stored );
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->stored = $value;
				return true;
			}
		);
	}

	public function test_start_initializes_counters_and_running_state(): void {
		BulkJob::start( 120 );

		$this->assertTrue( BulkJob::is_running() );
		$this->assertSame( 120, $this->stored['total'] );
		$this->assertSame( 0, BulkJob::processed() );
	}

	public function test_record_increments_the_matching_counter(): void {
		BulkJob::start( 10 );

		BulkJob::record( 'optimized' );
		BulkJob::record( 'optimized' );
		BulkJob::record( 'skipped' );
		BulkJob::record( 'failed' );

		$this->assertSame( 2, $this->stored['optimized'] );
		$this->assertSame( 1, $this->stored['skipped'] );
		$this->assertSame( 1, $this->stored['failed'] );
		$this->assertSame( 4, BulkJob::processed() );
	}

	public function test_record_is_ignored_when_not_running(): void {
		BulkJob::start( 10 );
		BulkJob::finish( BulkJob::STATE_CANCELLED );

		BulkJob::record( 'optimized' );

		$this->assertSame( 0, $this->stored['optimized'] );
		$this->assertFalse( BulkJob::is_running() );
	}

	public function test_finish_sets_terminal_state_and_timestamp(): void {
		BulkJob::start( 10 );
		BulkJob::finish( BulkJob::STATE_DONE );

		$this->assertSame( BulkJob::STATE_DONE, $this->stored['state'] );
		$this->assertGreaterThan( 0, $this->stored['finished_at'] );
	}

	public function test_finish_without_a_job_is_a_noop(): void {
		BulkJob::finish( BulkJob::STATE_DONE );

		$this->assertSame( [], $this->stored );
	}
}
