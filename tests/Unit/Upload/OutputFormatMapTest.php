<?php
/**
 * Tests for the editor output-format mapping.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\OutputFormatMap;

/**
 * WP 7.1's client-side uploader reads this map from the server, so mapping
 * jpeg/png to the plugin's output format makes the BROWSER generate
 * thumbnails already converted — zero API calls for sub-sizes.
 */
final class OutputFormatMapTest extends MonkeyTestCase {

	/**
	 * Stub the options row.
	 *
	 * @param array<string, mixed> $options Option values.
	 */
	private function options( array $options ): void {
		Options::clear_cache();
		Functions\when( 'get_option' )->alias(
			static function ( string $name, $fallback = false ) use ( $options ) {
				return 'lw_img_options' === $name ? $options : $fallback;
			}
		);
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults ) => array_merge( (array) $defaults, (array) $args )
		);
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_maps_jpeg_and_png_to_webp_when_converting(): void {
		$this->options(
			[
				'auto_convert'  => true,
				'output_format' => 'webp',
			]
		);

		$map = OutputFormatMap::map( [ 'image/heic' => 'image/jpeg' ] );

		$this->assertSame( 'image/webp', $map['image/jpeg'] );
		$this->assertSame( 'image/webp', $map['image/png'] );
	}

	public function test_honours_the_avif_output_format(): void {
		$this->options(
			[
				'auto_convert'  => true,
				'output_format' => 'avif',
			]
		);

		$map = OutputFormatMap::map( [] );

		$this->assertSame( 'image/avif', $map['image/jpeg'] );
	}

	public function test_preserves_core_entries_such_as_heic(): void {
		$this->options(
			[
				'auto_convert'  => true,
				'output_format' => 'webp',
			]
		);

		$map = OutputFormatMap::map( [ 'image/heic' => 'image/jpeg' ] );

		$this->assertSame( 'image/jpeg', $map['image/heic'] );
	}

	public function test_never_maps_gif(): void {
		// A gif mapping would flatten animations client-side.
		$this->options(
			[
				'auto_convert'  => true,
				'output_format' => 'webp',
			]
		);

		$this->assertArrayNotHasKey( 'image/gif', OutputFormatMap::map( [] ) );
	}

	public function test_leaves_the_map_alone_when_auto_convert_is_off(): void {
		$this->options(
			[
				'auto_convert'  => false,
				'output_format' => 'webp',
			]
		);

		$this->assertSame( [ 'image/heic' => 'image/jpeg' ], OutputFormatMap::map( [ 'image/heic' => 'image/jpeg' ] ) );
	}
}
