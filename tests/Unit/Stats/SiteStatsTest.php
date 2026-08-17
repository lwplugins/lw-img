<?php
/**
 * Tests for the stats math and directory measurement.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Stats;

use LightweightPlugins\Img\Stats\SiteStats;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Stats\SiteStats
 */
final class SiteStatsTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_savings
	 */
	public function test_savings_percent( int $original, int $optimized, float $expected ): void {
		$this->assertEqualsWithDelta( $expected, SiteStats::savings_percent( $original, $optimized ), 0.01 );
	}

	/**
	 * Savings cases.
	 *
	 * @return array<string, array{int, int, float}>
	 */
	public static function provide_savings(): array {
		return [
			'60% saved'          => [ 1000, 400, 60.0 ],
			'nothing optimized'  => [ 0, 0, 0.0 ],
			'no savings'         => [ 500, 500, 0.0 ],
			'inflated stays 0'   => [ 400, 500, 0.0 ],
		];
	}

	public function test_dir_stats_of_missing_directory_is_empty(): void {
		$this->assertSame(
			[
				'bytes' => 0,
				'files' => 0,
			],
			SiteStats::dir_stats( __DIR__ . '/does-not-exist' )
		);
	}

	public function test_dir_stats_counts_fixture_files(): void {
		$stats = SiteStats::dir_stats( __DIR__ . '/../../Fixtures' );

		$this->assertGreaterThanOrEqual( 2, $stats['files'] );
		$this->assertGreaterThan( 0, $stats['bytes'] );
	}
}
