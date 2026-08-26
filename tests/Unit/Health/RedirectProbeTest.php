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
}
