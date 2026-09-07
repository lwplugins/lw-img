<?php
/**
 * Tests for the loopback redirect probe.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Health;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Health\RedirectProbe;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Health\RedirectProbe
 */
final class RedirectProbeTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_get_upload_dir' )->justReturn( [ 'baseurl' => 'https://example.test/wp-content/uploads' ] );
		Functions\when( 'wp_rand' )->justReturn( 42 );
	}

	public function test_marker_header_means_redirects_reach_wordpress(): void {
		Functions\when( 'wp_safe_remote_get' )->justReturn( [ 'headers' => [] ] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_header' )->justReturn( 'ok' );

		$this->assertTrue( RedirectProbe::works() );
	}

	public function test_plain_404_without_marker_means_swallowed(): void {
		Functions\when( 'wp_safe_remote_get' )->justReturn( [ 'headers' => [] ] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_header' )->justReturn( '' );

		$this->assertFalse( RedirectProbe::works() );
	}

	public function test_loopback_failure_means_unknown(): void {
		Functions\when( 'wp_safe_remote_get' )->justReturn( new \stdClass() );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$this->assertNull( RedirectProbe::works() );
	}
	public function test_cached_returns_the_stored_verdict_without_a_request(): void {
		Functions\when( 'get_transient' )->justReturn( 'no' );
		Functions\expect( 'wp_safe_remote_get' )->never();

		$this->assertFalse( RedirectProbe::cached() );
	}

	public function test_cached_probes_and_stores_a_definite_verdict(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_safe_remote_get' )->justReturn( [ 'headers' => [] ] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_header' )->justReturn( 'ok' );
		Functions\expect( 'set_transient' )->once()->with( RedirectProbe::CACHE_KEY, 'yes', 600 )->andReturn( true );

		$this->assertTrue( RedirectProbe::cached() );
	}

	public function test_cached_does_not_store_an_unknown_verdict(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_safe_remote_get' )->justReturn( new \stdClass() );
		Functions\when( 'is_wp_error' )->justReturn( true );
		Functions\expect( 'set_transient' )->never();

		$this->assertNull( RedirectProbe::cached() );
	}

	public function test_forget_deletes_the_cached_verdict(): void {
		Functions\expect( 'delete_transient' )->once()->with( RedirectProbe::CACHE_KEY )->andReturn( true );

		RedirectProbe::forget();
	}
}
