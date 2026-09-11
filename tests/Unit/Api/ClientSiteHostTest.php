<?php
declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Api;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Api\Client;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Api\Client
 */
final class ClientSiteHostTest extends MonkeyTestCase {

	public function test_site_host_is_the_lowercase_home_url_host(): void {
		Functions\when( 'home_url' )->justReturn( 'https://WWW.CornerArt.hu/blog/' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$this->assertSame( 'www.cornerart.hu', Client::site_host() );
	}

	public function test_site_host_can_be_overridden_by_filter(): void {
		Functions\when( 'home_url' )->justReturn( 'https://cornerart.hu' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		\Brain\Monkey\Filters\expectApplied( 'lw_img_site_host' )->once()->andReturn( 'shop.cornerart.hu' );

		$this->assertSame( 'shop.cornerart.hu', Client::site_host() );
	}
}
