<?php
/**
 * Tests for the bulk pacing profiles and load guard math.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Bulk;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Bulk\Throttle;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Bulk\Throttle
 */
final class ThrottleTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( array $args, array $defaults ): array => array_merge( $defaults, $args )
		);
	}

	protected function tearDown(): void {
		Throttle::force( null );
		parent::tearDown();
	}

	public function test_profiles_slow_down_from_fast_to_gentle(): void {
		$this->assertLessThan( Throttle::batch_size( 'fast' ), Throttle::batch_size( 'gentle' ) );
		$this->assertGreaterThan( Throttle::pause_ms( 'fast' ), Throttle::pause_ms( 'gentle' ) );
		$this->assertGreaterThan( Throttle::tick_gap( 'fast' ), Throttle::tick_gap( 'gentle' ) );
	}

	public function test_force_overrides_the_saved_speed(): void {
		Throttle::force( 'gentle' );

		$this->assertSame( 'gentle', Throttle::speed() );
		$this->assertSame( Throttle::batch_size( 'gentle' ), Throttle::batch_size() );
	}

	public function test_force_rejects_unknown_speeds(): void {
		Throttle::force( 'ludicrous' );
		Throttle::force( 'gentle' );
		Throttle::force( 'ludicrous' );

		// An invalid value clears the override instead of sticking.
		$this->assertNotSame( 'ludicrous', Throttle::speed() );
	}

	public function test_load_limit_scales_with_cores_and_speed(): void {
		$this->assertSame( 8.0, Throttle::load_limit( 8, 'normal' ) );
		$this->assertSame( 12.0, Throttle::load_limit( 8, 'fast' ) );
		$this->assertEqualsWithDelta( 5.6, Throttle::load_limit( 8, 'gentle' ), 0.001 );
	}

	public function test_load_limit_never_drops_below_two(): void {
		$this->assertSame( 2.0, Throttle::load_limit( 1, 'gentle' ) );
	}

	public function test_is_overloaded_compares_against_the_limit(): void {
		Throttle::force( 'normal' );
		$limit = Throttle::load_limit();

		$this->assertTrue( Throttle::is_overloaded( $limit + 1 ) );
		$this->assertFalse( Throttle::is_overloaded( $limit - 1 ) );
	}
}
