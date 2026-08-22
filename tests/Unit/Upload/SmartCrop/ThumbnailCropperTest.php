<?php
/**
 * Tests for the smart-crop executor's pure logic.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload\SmartCrop;

use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\SmartCrop\ThumbnailCropper;

/**
 * The convert target must match the main file's format exactly, and refuse
 * formats the API's conversion path cannot encode. crop() itself is thin
 * glue over Client/SizeCatalog/filesystem and is exercised on the test
 * site, not unit-mocked (per tests.md: a test needing 5+ stubs signals
 * untestable code, and the decisions all live in SizeCatalog).
 */
final class ThumbnailCropperTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_extensions
	 */
	public function test_convert_target_matches_the_main_files_format( string $file, ?string $expected ): void {
		$this->assertSame( $expected, ThumbnailCropper::convert_target_for( $file ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string|null}>
	 */
	public static function provide_extensions(): array {
		return [
			'webp'          => [ '/u/photo.webp', 'webp' ],
			'avif'          => [ '/u/photo.avif', 'avif' ],
			'jpg'           => [ '/u/photo.jpg', 'jpeg' ],
			'jpeg'          => [ '/u/photo.jpeg', 'jpeg' ],
			'png'           => [ '/u/photo.png', 'png' ],
			'uppercase'     => [ '/u/PHOTO.WEBP', 'webp' ],
			'gif is unsupported by the conversion path' => [ '/u/anim.gif', null ],
			'no extension'  => [ '/u/photo', null ],
		];
	}
}
