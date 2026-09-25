<?php
/**
 * Tests for the uninstall data policy.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Uninstall;

use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Uninstall\DataPolicy;

/**
 * @covers \LightweightPlugins\Img\Uninstall\DataPolicy
 */
final class DataPolicyTest extends MonkeyTestCase {

	public function test_default_keeps_data(): void {
		$this->assertFalse( DataPolicy::should_wipe( [] ) );
		$this->assertFalse( DataPolicy::should_wipe( false ) );
		$this->assertFalse( DataPolicy::should_wipe( [ 'api_key' => 'himg_x' ] ) );
	}

	public function test_opt_in_wipes(): void {
		$this->assertTrue( DataPolicy::should_wipe( [ 'delete_on_uninstall' => true ] ) );
		$this->assertTrue( DataPolicy::should_wipe( [ 'delete_on_uninstall' => 1 ] ) );
		$this->assertTrue( DataPolicy::should_wipe( [ 'delete_on_uninstall' => '1' ] ) );
	}

	public function test_explicit_off_keeps(): void {
		$this->assertFalse( DataPolicy::should_wipe( [ 'delete_on_uninstall' => false ] ) );
		$this->assertFalse( DataPolicy::should_wipe( [ 'delete_on_uninstall' => '0' ] ) );
		$this->assertFalse( DataPolicy::should_wipe( [ 'delete_on_uninstall' => '' ] ) );
	}

	public function test_option_lists_are_split_by_policy(): void {
		$this->assertContains( 'lw_img_bulk_job', DataPolicy::VOLATILE_OPTIONS );
		$this->assertContains( 'lw_img_options', DataPolicy::PERSISTENT_OPTIONS );
		$this->assertContains( 'lw_img_log', DataPolicy::PERSISTENT_OPTIONS );
		$this->assertSame( [], array_intersect( DataPolicy::VOLATILE_OPTIONS, DataPolicy::PERSISTENT_OPTIONS ) );
	}

	/**
	 * @dataProvider provide_volatile_transients
	 */
	public function test_every_cache_is_dropped_on_uninstall( string $transient ): void {
		$this->assertContains( $transient, DataPolicy::VOLATILE_TRANSIENTS );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provide_volatile_transients(): array {
		return [
			'account cache' => [ 'lw_img_account' ],
			'stats cache'   => [ 'lw_img_stats' ],
			'pending count' => [ 'lw_img_pending_count' ],
			'probe verdict' => [ 'lw_img_redirect_probe' ],
			'tester report' => [ 'lw_img_health_report' ],
		];
	}
}
