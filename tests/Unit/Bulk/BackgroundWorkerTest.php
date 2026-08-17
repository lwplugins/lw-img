<?php
/**
 * Tests for the poll-driven assist gating in BackgroundWorker.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Bulk;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Bulk\BackgroundWorker;
use LightweightPlugins\Img\Bulk\BulkJob;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * assist() must only take over a run that is genuinely stalled.
 */
final class BackgroundWorkerTest extends MonkeyTestCase {

	/**
	 * Stub get_option to serve the given bulk job record.
	 *
	 * @param array<string, mixed> $job Job record.
	 */
	private function stub_job( array $job ): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $name, $default_value = false ) use ( $job ) {
				return BulkJob::OPTION_NAME === $name ? $job : $default_value;
			}
		);
	}

	public function test_assist_does_nothing_when_no_run_is_active(): void {
		$this->stub_job( [] );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )->never();

		BackgroundWorker::assist();

		$this->assertTrue( true );
	}

	public function test_assist_does_nothing_when_run_progressed_recently(): void {
		$this->stub_job(
			[
				'state'      => BulkJob::STATE_RUNNING,
				'updated_at' => time(),
			]
		);
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )->never();

		BackgroundWorker::assist();

		$this->assertTrue( true );
	}

	public function test_assist_does_nothing_when_lock_is_held(): void {
		$this->stub_job(
			[
				'state'      => BulkJob::STATE_RUNNING,
				'updated_at' => time() - 120,
			]
		);
		Functions\when( 'get_transient' )->justReturn( 1 );
		Functions\expect( 'set_transient' )->never();

		BackgroundWorker::assist();

		$this->assertTrue( true );
	}

	public function test_assist_processes_a_stalled_run_and_finishes_when_drained(): void {
		$this->stub_job(
			[
				'state'      => BulkJob::STATE_RUNNING,
				'updated_at' => time() - 120,
				'retried'    => 1,
			]
		);
		// The claim path asks MySQL for an advisory lock and picks pending
		// IDs via direct SQL; report "no lock support" and an empty queue.
		$GLOBALS['wpdb'] = new class() {
			/**
			 * Table name properties referenced by the picker SQL.
			 *
			 * @var string
			 */
			public string $posts    = 'wp_posts';
			public string $postmeta = 'wp_postmeta'; // phpcs:ignore Squiz.Commenting.VariableComment.Missing -- same role as $posts above.

			/**
			 * @param string $sql  Query with placeholders.
			 * @param mixed  ...$args Values (ignored).
			 */
			public function prepare( string $sql, ...$args ): string {
				return $sql;
			}

			/**
			 * @param string $sql Query (ignored).
			 */
			public function get_var( string $sql ): string {
				return '0';
			}

			/**
			 * @param string $sql Query (ignored).
			 * @return array<int, string>
			 */
			public function get_col( string $sql ): array {
				return [];
			}
		};

		Functions\when( 'wp_parse_args' )->alias(
			static function ( array $args, array $defaults ): array {
				return array_merge( $defaults, $args );
			}
		);
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )->once();
		Functions\expect( 'delete_transient' )->once();

		$job_updates = [];
		Functions\when( 'update_option' )->alias(
			static function ( string $name, $value ) use ( &$job_updates ): bool {
				if ( BulkJob::OPTION_NAME === $name ) {
					$job_updates[] = $value;
				}
				return true;
			}
		);

		BackgroundWorker::assist();

		$this->assertCount( 1, $job_updates );
		$this->assertSame( BulkJob::STATE_DONE, $job_updates[0]['state'] );

		unset( $GLOBALS['wpdb'] );
		$this->assertTrue( true );
	}
}
