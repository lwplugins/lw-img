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
 * WP 7.1's client-side uploader reads this map from the REST attachment
 * response, so mapping jpeg/png to the plugin's output format makes the
 * BROWSER generate thumbnails already converted — zero API calls for
 * sub-sizes. Every test below runs in a stubbed REST request context;
 * test_ignores_the_map_outside_a_rest_request() locks in the opposite
 * case, which is what protects Restorer, WP-CLI, cron, and the wp-admin
 * image editor from ever seeing the map.
 */
final class OutputFormatMapTest extends MonkeyTestCase {

	/**
	 * Stub the options row and put the request in REST context.
	 *
	 * Also stubs wp_is_client_side_media_processing_enabled(): Brain Monkey's
	 * Functions\when() DEFINES the function, which is what makes
	 * function_exists( 'wp_is_client_side_media_processing_enabled' ) true in
	 * this suite. Without this stub the 7.1-feature-detection gate in map()
	 * would return every test's map untouched regardless of the scenario
	 * under test, since the suite never otherwise defines that symbol.
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
		Functions\when( 'wp_is_serving_rest_request' )->justReturn( true );
		Functions\when( 'wp_is_client_side_media_processing_enabled' )->justReturn( true );
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_maps_jpeg_and_png_to_webp_when_converting(): void {
		$this->options(
			[
				'api_key'       => 'test-key',
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
				'api_key'       => 'test-key',
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
				'api_key'       => 'test-key',
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
				'api_key'       => 'test-key',
				'auto_convert'  => true,
				'output_format' => 'webp',
			]
		);

		$this->assertArrayNotHasKey( 'image/gif', OutputFormatMap::map( [] ) );
	}

	public function test_leaves_the_map_alone_when_auto_convert_is_off(): void {
		$this->options(
			[
				'api_key'       => 'test-key',
				'auto_convert'  => false,
				'output_format' => 'webp',
			]
		);

		$this->assertSame( [ 'image/heic' => 'image/jpeg' ], OutputFormatMap::map( [ 'image/heic' => 'image/jpeg' ] ) );
	}

	public function test_leaves_the_map_alone_when_not_configured(): void {
		// No API key: the plugin converts nothing server-side either, so the
		// browser must not convert anything in its place. This is what keeps
		// a fresh, unconfigured install behaving stock (no log, no backup,
		// no silent core conversion) instead of contradicting the exclusion
		// copy in TabUpload.
		$this->options(
			[
				'api_key'       => '',
				'auto_convert'  => true,
				'output_format' => 'webp',
			]
		);

		$map = OutputFormatMap::map( [ 'image/heic' => 'image/jpeg' ] );

		$this->assertSame( [ 'image/heic' => 'image/jpeg' ], $map );
	}

	public function test_ignores_the_map_outside_a_rest_request(): void {
		// Restorer-protection regression lock: Restorer (admin-post), WP-CLI,
		// cron, and the wp-admin image editor (admin-ajax) all regenerate
		// thumbnails outside a REST request. If this filter mapped the main
		// file there too, core would re-convert a just-restored JPEG straight
		// back to WebP the moment its thumbnails regenerate, orphaning the
		// recovered original on disk.
		$this->options(
			[
				'api_key'       => 'test-key',
				'auto_convert'  => true,
				'output_format' => 'webp',
			]
		);
		Functions\when( 'wp_is_serving_rest_request' )->justReturn( false );

		$map = OutputFormatMap::map( [ 'image/heic' => 'image/jpeg' ] );

		$this->assertSame( [ 'image/heic' => 'image/jpeg' ], $map );
	}
}
