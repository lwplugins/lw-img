<?php
/**
 * Tests for the Tester response.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Health;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Health\TesterView;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Health\TesterView
 */
final class TesterViewTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	/**
	 * A report with one of each status.
	 *
	 * @return array<string, mixed>
	 */
	private static function report(): array {
		return [
			'generated_at' => 1700000000,
			'sections'     => [
				'api'       => [
					[
						'label'   => 'HelloImg API',
						'status'  => 'warning',
						'message' => 'no API key configured',
					],
				],
				'database'  => [
					[
						'label'   => 'wp_posts',
						'status'  => 'critical',
						'message' => 'MyISAM',
						'fix'     => 'ALTER TABLE wp_posts ENGINE=InnoDB',
					],
					[
						'label'   => 'Database server',
						'status'  => 'info',
						'message' => '8.0',
					],
				],
				'redirects' => [
					[
						'label'   => 'Old-image redirects',
						'status'  => 'ok',
						'message' => 'fine',
					],
				],
			],
		];
	}

	public function test_verdict_counts_every_status(): void {
		$view = TesterView::build( self::report() );

		$this->assertSame(
			[
				'status'   => 'critical',
				'critical' => 1,
				'warning'  => 1,
				'ok'       => 1,
				'info'     => 1,
			],
			$view['verdict']
		);
	}

	public function test_attention_lists_criticals_first_with_their_fix(): void {
		$view = TesterView::build( self::report() );

		$this->assertSame( [ 'database', 'api' ], array_column( $view['attention'], 'section' ) );
		$this->assertSame( 'ALTER TABLE wp_posts ENGINE=InnoDB', $view['attention'][0]['fix'] );
		$this->assertNull( $view['attention'][1]['fix'] );
	}

	public function test_sections_follow_display_order_with_their_worst_status(): void {
		$view = TesterView::build( self::report() );

		$this->assertSame( [ 'database', 'redirects', 'api' ], array_column( $view['sections'], 'id' ) );
		$this->assertSame( [ 'critical', 'ok', 'warning' ], array_column( $view['sections'], 'status' ) );
		$this->assertSame( 1, $view['sections'][0]['critical'] );
	}

	public function test_all_ok_report(): void {
		$view = TesterView::build( [ 'sections' => [] ] );

		$this->assertSame( 'ok', $view['verdict']['status'] );
		$this->assertSame( [], $view['sections'] );
	}
}
