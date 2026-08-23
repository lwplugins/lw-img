<?php
/**
 * Tests for the bulk optimizer's run-level guards.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Bulk;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Bulk\AttachmentOptimizer;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Bulk\AttachmentOptimizer
 */
final class AttachmentOptimizerTest extends MonkeyTestCase {

	/**
	 * A missing API key must halt the run like a quota error — without
	 * stamping the attachment, which would silently drain the whole queue
	 * into "skipped". Regression test for the key-rotation bug: emptying
	 * the key mid-run kept the worker going, stamping every image.
	 *
	 * The guard must return before any ImageRepository access; the test
	 * environment has no $wpdb, so reaching the repository fails loudly.
	 */
	public function test_halts_without_stamping_when_api_key_is_missing(): void {
		Functions\when( 'get_option' )->justReturn( [ 'api_key' => '' ] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( array $args, array $defaults ): array => array_merge( $defaults, $args )
		);

		$result = ( new AttachmentOptimizer() )->optimize( 123 );

		$this->assertSame( AttachmentOptimizer::RESULT_FAILED, $result['result'] );
		$this->assertTrue( $result['halt'] ?? false );
	}
}
