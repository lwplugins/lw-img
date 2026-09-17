<?php
/**
 * Tests for the redirect-probe classification.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Health;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Health\RedirectChecks;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Health\RedirectChecks
 */
final class RedirectChecksTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	public function test_working_probe_is_ok(): void {
		$row = RedirectChecks::classify( true );

		$this->assertSame( 'ok', $row['status'] );
	}

	public function test_swallowed_probe_warns_with_the_nginx_fix(): void {
		$row = RedirectChecks::classify( false );

		$this->assertSame( 'warning', $row['status'] );
		$fix = (string) $row['fix'];
		// A prefix location: regex locations from earlier includes (typical
		// static-asset blocks) would otherwise win and swallow the 404.
		$this->assertStringStartsWith( 'location ^~ /wp-content/uploads/', $fix );
		$this->assertStringContainsString( 'try_files $uri /index.php', $fix );
		$this->assertStringContainsString( 'deny all', $fix );
		$this->assertStringContainsString( 'expires', $fix );
	}

	/**
	 * @dataProvider provide_uploads_paths
	 */
	public function test_swallowed_probe_fix_targets_the_sites_uploads_path( string $uploads_path, string $expected_location ): void {
		$row = RedirectChecks::classify( false, $uploads_path );

		$this->assertStringStartsWith( $expected_location, (string) $row['fix'] );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_uploads_paths(): array {
		return [
			'classic wordpress' => [ '/wp-content/uploads', 'location ^~ /wp-content/uploads/ {' ],
			'bedrock'           => [ '/app/uploads', 'location ^~ /app/uploads/ {' ],
		];
	}

	public function test_rows_report_the_verdict_the_bulk_gate_reads(): void {
		// The Bulk tab renders before the Tester and reads the cached probe
		// verdict; a live re-probe here made the two tabs contradict each
		// other ("all checks passed" next to a blocked bulk start).
		$this->stub_uploads_baseurl( 'https://example.test/app/uploads' );
		Functions\when( 'get_transient' )->justReturn( 'no' );
		Functions\when( 'wp_safe_remote_get' )->justReturn( [ 'headers' => [] ] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_header' )->justReturn( 'ok' );

		$row = RedirectChecks::rows()[0];

		$this->assertSame( 'warning', $row['status'] );
	}

	public function test_rows_build_the_fix_from_the_uploads_baseurl(): void {
		$this->stub_uploads_baseurl( 'https://example.test/app/uploads' );
		Functions\when( 'get_transient' )->justReturn( 'no' );

		$row = RedirectChecks::rows()[0];

		$this->assertStringStartsWith( 'location ^~ /app/uploads/ {', (string) $row['fix'] );
	}

	private function stub_uploads_baseurl( string $baseurl ): void {
		Functions\when( 'wp_get_upload_dir' )->justReturn( [ 'baseurl' => $baseurl ] );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
	}

	public function test_unverifiable_probe_is_info_without_a_fix(): void {
		$row = RedirectChecks::classify( null );

		$this->assertSame( 'info', $row['status'] );
		$this->assertArrayNotHasKey( 'fix', $row );
	}
}
