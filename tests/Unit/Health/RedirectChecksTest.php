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
		$this->assertStringContainsString( 'try_files', (string) $row['fix'] );
	}

	public function test_unverifiable_probe_is_info_without_a_fix(): void {
		$row = RedirectChecks::classify( null );

		$this->assertSame( 'info', $row['status'] );
		$this->assertArrayNotHasKey( 'fix', $row );
	}
}
