<?php
/**
 * Tests for the account response shaping.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Account;

use LightweightPlugins\Img\Account\AccountSummary;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Account\AccountSummary
 */
final class AccountSummaryTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_plan_slugs
	 */
	public function test_plan_label_humanizes_the_api_slug( string $slug, string $expected ): void {
		$this->assertSame( $expected, AccountSummary::plan_label( $slug ) );
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
		$this->assertSame( $expected, AccountSummary::host_matches( $host, $domain ) );
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
		$this->assertSame( [ 'monthly_images' => 1000, 'used' => 412 ], AccountSummary::limit_from( [ 'limit' => [ 'monthly_images' => 1000, 'used' => 412 ] ] ) );
		$this->assertNull( AccountSummary::limit_from( [ 'limit' => null ] ) );
		$this->assertNull( AccountSummary::limit_from( [] ) );
	}

	/**
	 * Key state of a set, stored key.
	 *
	 * @return array{set: bool, source: string, hint: string}
	 */
	private static function key_set(): array {
		return [
			'set'    => true,
			'source' => 'option',
			'hint'   => 'himg_…abcd',
		];
	}

	public function test_build_connected_limited_plan(): void {
		$fetch = [
			'ok'         => true,
			'account'    => [
				'plan'    => 'reseller_ai',
				'month'   => '2026-09',
				'website' => [
					'domain'      => 'example.com',
					'images'      => 40,
					'bytes_saved' => 1000,
				],
				'limit'   => [
					'monthly_images' => 1000,
					'used'           => 250,
				],
			],
			'checked_at' => 1700000000,
			'cached'     => true,
		];

		$summary = AccountSummary::build( $fetch, self::key_set(), 'www.example.com', [ 'optimized' => 7, 'saved' => 99 ] );

		$this->assertTrue( $summary['connected'] );
		$this->assertSame( [ 'slug' => 'reseller_ai', 'label' => 'Reseller AI' ], $summary['plan'] );
		$this->assertSame( [ 'limited' => true, 'monthly_limit' => 1000, 'used' => 250, 'percent' => 25.0 ], $summary['usage'] );
		$this->assertFalse( $summary['site_mismatch'] );
		$this->assertSame( [ 'optimized' => 7, 'saved' => 99 ], $summary['site'] );
		$this->assertNull( $summary['error'] );
		$this->assertTrue( $summary['cached'] );
	}

	public function test_build_flags_a_key_issued_for_another_site(): void {
		$fetch = [
			'ok'      => true,
			'account' => [
				'plan'    => 'free',
				'website' => [ 'domain' => 'other.org' ],
				'limit'   => null,
			],
		];

		$summary = AccountSummary::build( $fetch, self::key_set(), 'example.com', [] );

		$this->assertTrue( $summary['site_mismatch'] );
		$this->assertFalse( $summary['usage']['limited'] );
	}

	public function test_build_reports_the_api_error(): void {
		$fetch = [
			'ok'          => false,
			'account'     => null,
			'error'       => 'Invalid API key',
			'error_code'  => 'unauthorized',
			'http_status' => 401,
		];

		$summary = AccountSummary::build( $fetch, self::key_set(), 'example.com', [] );

		$this->assertFalse( $summary['connected'] );
		$this->assertSame( [ 'code' => 'unauthorized', 'message' => 'Invalid API key', 'status' => 401 ], $summary['error'] );
		$this->assertNull( $summary['plan'] );
	}

	public function test_build_without_a_key_is_not_an_error(): void {
		$summary = AccountSummary::build( [ 'ok' => false ], [ 'set' => false, 'source' => 'none', 'hint' => '' ], 'example.com', [] );

		$this->assertFalse( $summary['configured'] );
		$this->assertNull( $summary['error'] );
	}
}
