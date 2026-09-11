<?php
/**
 * Tests for the account panel's pure formatting logic.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Admin\Settings;

use LightweightPlugins\Img\Admin\Settings\AccountPanel;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Admin\Settings\AccountPanel
 */
final class AccountPanelTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_plan_slugs
	 */
	public function test_plan_label_humanizes_the_api_slug( string $slug, string $expected ): void {
		$this->assertSame( $expected, AccountPanel::plan_label( $slug ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_plan_slugs(): array {
		return [
			'reseller_ai (live shape)' => [ 'reseller_ai', 'Reseller AI' ],
			'single word'              => [ 'free', 'Free' ],
			'two plain words'          => [ 'pro_unlimited', 'Pro Unlimited' ],
			'hyphenated variant'       => [ 'reseller-ai', 'Reseller AI' ],
			'empty plan'               => [ '', '' ],
		];
	}

	/**
	 * @dataProvider provide_hosts
	 */
	public function test_host_matches_mirrors_the_gateway_rule( string $host, string $domain, bool $expected ): void {
		$this->assertSame( $expected, AccountPanel::host_matches( $host, $domain ) );
	}

	public static function provide_hosts(): array {
		return [
			'exact'          => [ 'cornerart.hu', 'cornerart.hu', true ],
			'www stripped'   => [ 'www.cornerart.hu', 'cornerart.hu', true ],
			'subdomain'      => [ 'shop.cornerart.hu', 'cornerart.hu', true ],
			'other site'     => [ 'szonyszalon.hu', 'cornerart.hu', false ],
			'look-alike'     => [ 'notcornerart.hu', 'cornerart.hu', false ],
			'case'           => [ 'CornerArt.hu', 'cornerart.hu', true ],
		];
	}

	public function test_limit_from_reads_the_limit_object(): void {
		$this->assertSame( [ 'monthly_images' => 1000, 'used' => 412 ], AccountPanel::limit_from( [ 'limit' => [ 'monthly_images' => 1000, 'used' => 412 ] ] ) );
		$this->assertNull( AccountPanel::limit_from( [ 'limit' => null ] ) );
		$this->assertNull( AccountPanel::limit_from( [] ) );
	}
}
