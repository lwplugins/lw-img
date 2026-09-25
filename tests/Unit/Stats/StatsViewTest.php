<?php
/**
 * Tests for the Stats response.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Stats;

use LightweightPlugins\Img\Stats\StatsView;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Img\Stats\StatsView
 */
final class StatsViewTest extends TestCase {

	public function test_figures_and_leftovers(): void {
		$view = StatsView::build(
			[
				'count'         => 4,
				'original'      => 4000,
				'optimized'     => 1000,
				'saved'         => 3000,
				'percent'       => 75.0,
				'library_total' => 8,
				'backup'        => [
					'bytes' => 4000,
					'files' => 4,
				],
				'wins'          => [
					[
						'file'     => 'a.jpg',
						'original' => 2000,
						'new'      => 500,
					],
				],
				'generated_at'  => 1700000000,
				'leftovers'     => [
					'ShortPixel' => [
						'bytes'   => 100,
						'files'   => 2,
						'path'    => 'uploads/ShortpixelBackups',
						'type'    => 'folder',
						'partial' => false,
					],
					'Smush'      => [
						'bytes'   => 50,
						'files'   => 1,
						'path'    => 'uploads/**/*.bak.<ext>',
						'type'    => 'beside',
						'partial' => true,
					],
				],
				'scanned_at'    => 1699990000,
			],
			30
		);

		$this->assertSame( 750, $view['average'] );
		$this->assertSame( 50.0, $view['library_percent'] );
		$this->assertSame( 1500, $view['wins'][0]['saved'] );
		$this->assertSame( 75.0, $view['wins'][0]['percent'] );
		$this->assertSame( 150, $view['leftovers']['total_bytes'] );
		$this->assertTrue( $view['leftovers']['partial'] );
		$this->assertSame( [ 'ShortPixel', 'Smush' ], array_column( $view['leftovers']['sources'], 'name' ) );
		$this->assertSame( 30, $view['retention_days'] );
	}

	public function test_empty_library(): void {
		$view = StatsView::build( [], 0 );

		$this->assertSame( 0, $view['count'] );
		$this->assertSame( 0.0, $view['library_percent'] );
		$this->assertNull( $view['leftovers']['scanned_at'] );
		$this->assertSame( [], $view['leftovers']['sources'] );
	}
}
