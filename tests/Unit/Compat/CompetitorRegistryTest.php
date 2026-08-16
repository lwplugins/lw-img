<?php
/**
 * Tests for the competitor optimizer detection.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Compat;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Compat\CompetitorRegistry;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Compat\CompetitorRegistry
 */
final class CompetitorRegistryTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_competitor_metas
	 */
	public function test_managed_by_recognizes_competitor_meta( string $meta_key, string $expected_name ): void {
		Functions\when( 'metadata_exists' )->alias(
			static fn ( string $type, int $id, string $key ): bool => $key === $meta_key
		);

		$this->assertSame( $expected_name, CompetitorRegistry::managed_by( 42 ) );
	}

	/**
	 * Verified meta keys per competitor.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_competitor_metas(): array {
		return [
			'shortpixel current' => [ '_shortpixel_optimized', 'ShortPixel Image Optimizer' ],
			'shortpixel legacy'  => [ '_shortpixel_meta', 'ShortPixel Image Optimizer' ],
			'tinypng current'    => [ '_tiny_compress_images', 'TinyPNG (Tinify)' ],
			'tinypng legacy'     => [ 'tiny_compress_images', 'TinyPNG (Tinify)' ],
			'imagify data'       => [ '_imagify_data', 'Imagify' ],
			'imagify status'     => [ '_imagify_status', 'Imagify' ],
		];
	}

	public function test_managed_by_returns_null_without_competitor_meta(): void {
		Functions\when( 'metadata_exists' )->justReturn( false );

		$this->assertNull( CompetitorRegistry::managed_by( 42 ) );
	}

	public function test_active_competitors_matches_active_plugin_paths(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'imagify/imagify.php',
				'wp-smushit/wp-smush.php',
				'akismet/akismet.php',
			]
		);

		$this->assertSame(
			[
				'imagify' => 'Imagify',
				'smush'   => 'Smush',
			],
			CompetitorRegistry::active_competitors()
		);
	}

	public function test_active_competitors_empty_when_none_active(): void {
		Functions\when( 'get_option' )->justReturn( [ 'akismet/akismet.php' ] );

		$this->assertSame( [], CompetitorRegistry::active_competitors() );
	}
}
