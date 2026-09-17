<?php
declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Api;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Api\ApiException;
use LightweightPlugins\Img\Api\JobPoller;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Api\JobPoller
 */
final class JobPollerTest extends MonkeyTestCase {

	/**
	 * Arguments of the last poll request.
	 *
	 * @var array<string, mixed>
	 */
	private array $request = [];

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'home_url' )->justReturn( 'https://beautyssecret.hu' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'esc_html' )->returnArg();
	}

	/**
	 * @dataProvider provide_poll_urls
	 */
	public function test_resolve_url( string $poll_url, ?string $expected ): void {
		$this->assertSame( $expected, JobPoller::resolve_url( $poll_url ) );
	}

	/**
	 * @return array<string, array{string, string|null}>
	 */
	public static function provide_poll_urls(): array {
		return [
			'gateway path'             => [ '/v1/jobs/job_abc', 'https://api.helloimg.io/v1/jobs/job_abc' ],
			'absolute api url'         => [ 'https://api.helloimg.io/v1/jobs/job_abc', 'https://api.helloimg.io/v1/jobs/job_abc' ],
			'foreign host'             => [ 'https://169.254.169.254/v1/jobs/x', null ],
			'plain http'               => [ 'http://api.helloimg.io/v1/jobs/job_abc', null ],
			'protocol-relative'        => [ '//evil.test/v1/jobs/job_abc', null ],
			'api host, not a job path' => [ '/v1/account', null ],
			'userinfo trick'           => [ 'https://api.helloimg.io@evil.test/v1/jobs/x', null ],
		];
	}

	public function test_poll_sends_the_api_key_and_site_header(): void {
		$this->respond( 200, [ 'job_id' => 'job_abc', 'status' => 'completed', 'result' => [ 'image_url' => 'https://cdn.test/a.webp' ] ] );

		$result = $this->poller()->wait_for( '/v1/jobs/job_abc' );

		$this->assertSame( 'completed', $result->status );
		$this->assertSame(
			[
				'Authorization' => 'Bearer himg_live_x',
				'X-HIMG-Site'   => 'beautyssecret.hu',
			],
			$this->request['headers']
		);
	}

	public function test_poll_stops_on_an_auth_rejection_instead_of_waiting_it_out(): void {
		$this->respond( 403, [ 'error' => 'api key is bound to example.com', 'code' => 'site_mismatch' ] );

		try {
			$this->poller()->wait_for( '/v1/jobs/job_abc' );
			$this->fail( 'Expected an ApiException.' );
		} catch ( ApiException $e ) {
			$this->assertSame( 'site_mismatch', $e->get_error_code() );
			$this->assertTrue( $e->is_auth() );
		}
	}

	private function poller(): JobPoller {
		return new JobPoller( 'himg_live_x', static function (): void {} );
	}

	/**
	 * @param int                  $status HTTP status.
	 * @param array<string, mixed> $body   Response body.
	 */
	private function respond( int $status, array $body ): void {
		Functions\when( 'wp_safe_remote_get' )->alias(
			function ( string $url, array $args ): array {
				$this->request = $args;
				return [];
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( $status );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( (string) json_encode( $body ) );
	}
}
