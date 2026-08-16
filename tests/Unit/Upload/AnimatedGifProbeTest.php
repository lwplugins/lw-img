<?php
/**
 * Tests for the animated GIF frame-marker probe.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload;

use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\AnimatedGifProbe;

/**
 * @covers \LightweightPlugins\Img\Upload\AnimatedGifProbe
 */
final class AnimatedGifProbeTest extends MonkeyTestCase {

	private const FIXTURES = __DIR__ . '/../../Fixtures/';

	public function test_detects_multi_frame_gif(): void {
		$this->assertTrue( AnimatedGifProbe::is_animated( self::FIXTURES . 'animated.gif' ) );
	}

	public function test_single_frame_gif_is_not_animated(): void {
		$this->assertFalse( AnimatedGifProbe::is_animated( self::FIXTURES . 'static.gif' ) );
	}

	public function test_missing_file_is_not_animated(): void {
		$this->assertFalse( AnimatedGifProbe::is_animated( self::FIXTURES . 'does-not-exist.gif' ) );
	}
}
