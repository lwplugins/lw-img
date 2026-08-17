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
