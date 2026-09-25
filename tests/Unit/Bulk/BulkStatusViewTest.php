<?php
/**
 * Tests for the bulk status object.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Bulk;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Bulk\BulkStatusView;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Bulk\BulkStatusView
 */
final class BulkStatusViewTest extends MonkeyTestCase {

	private const NOW = 1700001000;

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	/**
	 * Context with fixed library totals.
	 *
	 * @return array<string, mixed>
	 */
	private static function context(): array {
		return [
			'library' => [
				'pending'   => 30,
				'optimized' => 900,
				'skipped'   => 40,
				'failed'    => 2,
			],
			'speed'   => 'fast',
		];
	}

	public function test_no_job_is_idle_without_run_counters(): void {
		$status = BulkStatusView::build( [], self::context(), self::NOW );

		$this->assertSame( 'idle', $status['state'] );
		$this->assertNull( $status['run'] );
		$this->assertSame( 900, $status['library']['optimized'] );
	}

	/**
	 * Regression: the classic dashboard overwrote the all-time tiles with
	 * the current run's counters on the first poll.
	 */
	public function test_run_counters_are_separate_from_library_totals(): void {
		$job = [
			'state'      => 'running',
			'total'      => 50,
			'optimized'  => 10,
			'skipped'    => 3,
			'failed'     => 1,
			'started_at' => self::NOW - 100,
			'updated_at' => self::NOW - 2,
		];

		$status = BulkStatusView::build( $job, self::context(), self::NOW );

		$this->assertSame( 10, $status['run']['optimized'] );
		$this->assertSame( 14, $status['run']['processed'] );
		$this->assertSame( 36, $status['run']['pending'] );
		$this->assertSame( 900, $status['library']['optimized'] );
		$this->assertSame( 30, $status['library']['pending'] );
	}

	public function test_running_timing_and_eta(): void {
		$job = [
			'state'      => 'running',
			'total'      => 30,
			'optimized'  => 10,
			'started_at' => self::NOW - 100,
			'updated_at' => self::NOW - 20,
		];

		$status = BulkStatusView::build( $job, self::context(), self::NOW );

		$this->assertSame( 100, $status['run']['elapsed'] );
		$this->assertSame( 200, $status['run']['eta'] );
		$this->assertTrue( $status['lock']['stalled'] );
	}

	/**
	 * Regression: re-queued images could push processed past total and the
	 * hero percentage over 100.
	 */
	public function test_percent_never_exceeds_100(): void {
		$job = [
			'state'     => 'running',
			'total'     => 10,
			'optimized' => 12,
		];

		$status = BulkStatusView::build( $job, self::context(), self::NOW );

		$this->assertSame( 100.0, $status['run']['percent'] );
		$this->assertSame( 0, $status['run']['pending'] );
	}

	public function test_finished_run_measures_its_own_duration(): void {
		$job = [
			'state'       => 'done',
			'total'       => 5,
			'optimized'   => 5,
			'bytes_in'    => 1000,
			'bytes_saved' => 250,
			'started_at'  => self::NOW - 500,
			'finished_at' => self::NOW - 400,
		];

		$status = BulkStatusView::build( $job, self::context(), self::NOW );

		$this->assertSame( 100, $status['run']['elapsed'] );
		$this->assertNull( $status['run']['eta'] );
		$this->assertSame( 25.0, $status['run']['saved_percent'] );
		$this->assertFalse( $status['lock']['stalled'] );
	}

	/**
	 * Regression: a halted run dropped back to the start screen silently.
	 */
	public function test_halted_run_explains_why(): void {
		$job = [
			'state' => 'halted',
			'total' => 5,
			'halt'  => [
				'reason' => 'quota',
				'detail' => 'Insufficient balance',
			],
		];

		$status = BulkStatusView::build( $job, self::context(), self::NOW );

		$this->assertSame( 'halted', $status['state'] );
		$this->assertSame( 'quota', $status['halt']['reason'] );
		$this->assertSame( 'Insufficient balance', $status['halt']['detail'] );
		$this->assertNotSame( '', $status['halt']['message'] );
	}

	public function test_unknown_stored_state_reads_as_idle(): void {
		$this->assertSame( 'idle', BulkStatusView::state( [ 'state' => 'paused' ] ) );
	}

	public function test_recent_feed_is_typed(): void {
		$job = [
			'state'  => 'running',
			'recent' => [
				[
					'ts'     => '5',
					'label'  => 'photo',
					'result' => 'optimized',
				],
				'junk',
			],
		];

		$status = BulkStatusView::build( $job, self::context(), self::NOW );

		$this->assertSame(
			[
				[
					'ts'     => 5,
					'label'  => 'photo',
					'result' => 'optimized',
					'detail' => '',
				],
			],
			$status['recent']
		);
	}
}
