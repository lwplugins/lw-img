<?php
/**
 * Tests for the rolling-window budget of the 404 fallback lookup.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Media;

use LightweightPlugins\Img\Media\LookupBudget;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * The window must reset on time and must stop counting past the limit.
 */
final class LookupBudgetTest extends MonkeyTestCase {

	public function test_starts_a_fresh_window_when_nothing_is_stored(): void {
		$state = LookupBudget::window( false, 1000 );

		$this->assertSame( 1060, $state['until'] );
		$this->assertSame( 0, $state['count'] );
	}

	public function test_keeps_counting_inside_an_open_window(): void {
		$state = LookupBudget::window(
			[
				'until' => 1050,
				'count' => 7,
			],
			1000
		);

		$this->assertSame( 1050, $state['until'] );
		$this->assertSame( 7, $state['count'] );
	}

	public function test_starts_over_once_the_window_has_closed(): void {
		$state = LookupBudget::window(
			[
				'until' => 1000,
				'count' => 20,
			],
			1000
		);

		$this->assertSame( 1060, $state['until'] );
		$this->assertSame( 0, $state['count'] );
	}

	public function test_ignores_a_corrupt_stored_value(): void {
		$state = LookupBudget::window( 'not an array', 500 );

		$this->assertSame( 560, $state['until'] );
		$this->assertSame( 0, $state['count'] );
	}

	public function test_negative_count_cannot_buy_extra_lookups(): void {
		$state = LookupBudget::window(
			[
				'until' => 1050,
				'count' => -5,
			],
			1000
		);

		$this->assertSame( 0, $state['count'] );
	}

	/**
	 * @dataProvider provide_counts
	 */
	public function test_has_room_stops_at_the_limit( int $count, bool $expected ): void {
		$this->assertSame(
			$expected,
			LookupBudget::has_room(
				[
					'until' => 1050,
					'count' => $count,
				]
			)
		);
	}

	/**
	 * @return array<string, array{0: int, 1: bool}>
	 */
	public static function provide_counts(): array {
		return [
			'empty window'    => [ 0, true ],
			'one below limit' => [ 19, true ],
			'at the limit'    => [ 20, false ],
			'over the limit'  => [ 99, false ],
		];
	}
}
