<?php
declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Api;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LightweightPlugins\Img\Api\ApiException;
use LightweightPlugins\Img\Api\SiteHost;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Api\SiteHost
 */
final class SiteHostTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
	}

	public function test_current_is_the_lowercase_home_url_host(): void {
		$this->stub_urls( 'https://WWW.CornerArt.hu/blog/', '' );

		$this->assertSame( 'www.cornerart.hu', SiteHost::current() );
	}

	public function test_current_can_be_overridden_by_filter(): void {
		$this->stub_urls( 'https://cornerart.hu', '' );
		Filters\expectApplied( 'lw_img_site_host' )->once()->andReturn( 'shop.cornerart.hu' );

		$this->assertSame( 'shop.cornerart.hu', SiteHost::current() );
	}

	public function test_current_falls_back_to_the_site_url_host(): void {
		$this->stub_urls( '', 'https://beautyssecret.hu/wp' );

		$this->assertSame( 'beautyssecret.hu', SiteHost::current() );
	}

	/**
	 * @dataProvider provide_urls
	 */
	public function test_from_url_extracts_the_host( string $url, string $expected ): void {
		$this->assertSame( $expected, SiteHost::from_url( $url ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_urls(): array {
		return [
			'bedrock site url'       => [ 'https://beautyssecret.hu/wp', 'beautyssecret.hu' ],
			'trailing dot and port'  => [ 'https://Example.com.:8443/', 'example.com' ],
			'scheme-less WP_HOME'    => [ 'example.com', 'example.com' ],
			'protocol-relative'      => [ '//example.com/wp', 'example.com' ],
			'empty'                  => [ '', '' ],
			'bare path'              => [ '/', '' ],
		];
	}

	public function test_header_value_refuses_to_send_an_empty_host(): void {
		// cURL drops a header whose value is empty, so the API would see no
		// X-HIMG-Site at all and answer a bare site_header_required.
		$this->stub_urls( '', '' );

		try {
			SiteHost::header_value();
			$this->fail( 'Expected an ApiException.' );
		} catch ( ApiException $e ) {
			$this->assertSame( 'site_header_required', $e->get_error_code() );
			$this->assertTrue( $e->is_auth() );
		}
	}

	private function stub_urls( string $home, string $site ): void {
		Functions\when( 'home_url' )->justReturn( $home );
		Functions\when( 'site_url' )->justReturn( $site );
	}
}
