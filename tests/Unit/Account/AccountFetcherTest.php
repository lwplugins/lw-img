<?php
/**
 * Tests for the cached account fetch.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Account;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Account\AccountCache;
use LightweightPlugins\Img\Account\AccountFetcher;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the General tab called /v1/account on every settings-page
 * view. The account now comes from a server-side cache unless a refresh is
 * asked for.
 *
 * @covers \LightweightPlugins\Img\Account\AccountFetcher
 * @covers \LightweightPlugins\Img\Account\AccountCache
 */
final class AccountFetcherTest extends MonkeyTestCase {

	/**
	 * The transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	/**
	 * HTTP calls made.
	 *
	 * @var int
	 */
	private int $calls = 0;

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		$this->transients = [];
		$this->calls      = 0;

		Functions\when( 'get_option' )->justReturn(
			[
				'api_key'         => 'himg_key_one_12345',
				'request_timeout' => 30,
			]
		);
		Functions\when( 'wp_parse_args' )->alias( static fn ( $args, $defaults ): array => array_merge( (array) $defaults, (array) $args ) );
		Functions\when( 'get_transient' )->alias( fn ( $name ) => $this->transients[ $name ] ?? false );
		Functions\when( 'set_transient' )->alias(
			function ( $name, $value ): bool {
				$this->transients[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'wp_parse_url' )->alias( static fn ( $url, $part = -1 ) => parse_url( $url, $part ) );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_remote_get' )->alias(
			function (): array {
				++$this->calls;
				return [];
			}
		);
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"plan":"free","limit":null}' );
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_second_view_is_served_from_the_cache(): void {
		AccountFetcher::fetch();
		$second = AccountFetcher::fetch();

		$this->assertSame( 1, $this->calls );
		$this->assertTrue( $second['cached'] );
		$this->assertSame( 'free', $second['account']['plan'] );
	}

	public function test_refresh_bypasses_the_cache(): void {
		AccountFetcher::fetch();
		$fresh = AccountFetcher::fetch( true );

		$this->assertSame( 2, $this->calls );
		$this->assertFalse( $fresh['cached'] );
	}

	public function test_a_cached_answer_for_another_key_is_not_served(): void {
		AccountCache::put( AccountCache::fingerprint( 'himg_other_key_999' ), [ 'ok' => true, 'account' => [ 'plan' => 'pro' ] ] );

		$result = AccountFetcher::fetch();

		$this->assertSame( 1, $this->calls );
		$this->assertSame( 'free', $result['account']['plan'] );
	}

	public function test_the_cache_never_stores_the_key(): void {
		AccountFetcher::fetch();

		$this->assertStringNotContainsString( 'himg_key_one', (string) json_encode( $this->transients ) );
	}
}
