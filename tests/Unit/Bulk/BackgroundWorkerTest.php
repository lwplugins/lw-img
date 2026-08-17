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
use Mockery;

// Minimal WP_Query stand-in: WordPress is not loaded in unit tests. The
// posts list a test wants back is injected via $GLOBALS['lw_img_test_posts'].
if ( ! class_exists( '\WP_Query' ) ) {
	class_alias( FakeWpQuery::class, '\WP_Query' );
}

/**
 * Returns the globally injected posts list.
 */
final class FakeWpQuery {

	/**
	 * Post IDs "found" by the query.
	 *
	 * @var array<int, int>
	 */
	public array $posts;

	/**
	 * Ignores the args and serves the injected list.
	 *
	 * @param array<string, mixed> $args Ignored.
	 */
	public function __construct( array $args = [] ) {
		$this->posts = $GLOBALS['lw_img_test_posts'] ?? [];
	}
}

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
		$GLOBALS['lw_img_test_posts'] = [];

		// The claim path asks MySQL for an advisory lock; report "no lock
		// support" so it degrades to the plain single-process pick.
		$GLOBALS['wpdb'] = new class() {
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
		};

		Functions\when( 'wp_parse_args' )->alias(
			static function ( array $args, array $defaults ): array {
				return array_merge( $defaults, $args );
			}
		);
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'set_transient' )->once();
		Functions\expect( 'delete_transient' )->once();
		Functions\expect( 'update_option' )->once()->with(
			BulkJob::OPTION_NAME,
			Mockery::on(
				static function ( array $job ): bool {
					return BulkJob::STATE_DONE === $job['state'];
				}
			),
			false
		);

		BackgroundWorker::assist();

		unset( $GLOBALS['lw_img_test_posts'], $GLOBALS['wpdb'] );
		$this->assertTrue( true );
	}
}
