<?php
/**
 * Tests for the health report's pure classification logic.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Health;

use LightweightPlugins\Img\Health\DatabaseChecks;
use LightweightPlugins\Img\Health\HealthReport;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Health\DatabaseChecks
 * @covers \LightweightPlugins\Img\Health\HealthReport
 */
final class HealthReportTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_engines
	 */
	public function test_engine_status_classification( string $table, string $engine, string $expected ): void {
		$this->assertSame( $expected, DatabaseChecks::engine_status( $table, $engine ) );
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public static function provide_engines(): array {
		return [
			'innodb postmeta is ok'      => [ 'postmeta', 'InnoDB', 'ok' ],
			'innodb lowercase is ok'     => [ 'options', 'innodb', 'ok' ],
			'myisam postmeta critical'   => [ 'postmeta', 'MyISAM', 'critical' ],
			'myisam posts critical'      => [ 'posts', 'MyISAM', 'critical' ],
			'myisam commentmeta warning' => [ 'commentmeta', 'MyISAM', 'warning' ],
			'aria termmeta warning'      => [ 'termmeta', 'Aria', 'warning' ],
			'unknown table info'         => [ 'usermeta', '', 'info' ],
		];
	}

	/**
	 * @dataProvider provide_worst_cases
	 *
	 * @param array<int, string> $statuses Row statuses.
	 * @param string             $expected Expected worst status.
	 */
	public function test_worst_status( array $statuses, string $expected ): void {
		$rows = array_map(
			static fn ( string $status ): array => [
				'label'   => 'x',
				'status'  => $status,
				'message' => '',
			],
			$statuses
		);

		$this->assertSame( $expected, HealthReport::worst_status( $rows ) );
	}

	/**
	 * @return array<string, array{array<int, string>, string}>
	 */
	public static function provide_worst_cases(): array {
		return [
			'critical beats warning' => [ [ 'ok', 'warning', 'critical' ], 'critical' ],
			'warning beats ok'       => [ [ 'ok', 'info', 'warning' ], 'warning' ],
			'info counts as ok'      => [ [ 'ok', 'info' ], 'ok' ],
			'empty is ok'            => [ [], 'ok' ],
		];
	}

	public function test_attention_rows_lifts_criticals_first(): void {
		$sections = [
			'a' => [
				[
					'label'   => 'warn-1',
					'status'  => 'warning',
					'message' => '',
				],
				[
					'label'   => 'fine',
					'status'  => 'ok',
					'message' => '',
				],
			],
			'b' => [
				[
					'label'   => 'crit-1',
					'status'  => 'critical',
					'message' => '',
				],
			],
		];

		$rows = HealthReport::attention_rows( $sections );

		$this->assertSame( [ 'crit-1', 'warn-1' ], array_column( $rows, 'label' ) );
	}

	public function test_count_status_sums_across_sections(): void {
		$sections = [
			'a' => [
				[
					'label'   => 'x',
					'status'  => 'critical',
					'message' => '',
				],
				[
					'label'   => 'y',
					'status'  => 'ok',
					'message' => '',
				],
			],
			'b' => [
				[
					'label'   => 'z',
					'status'  => 'critical',
					'message' => '',
				],
			],
		];

		$this->assertSame( 2, HealthReport::count_status( $sections, 'critical' ) );
		$this->assertSame( 1, HealthReport::count_status( $sections, 'ok' ) );
		$this->assertSame( 0, HealthReport::count_status( $sections, 'warning' ) );
	}
}
