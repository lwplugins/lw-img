<?php
/**
 * Tests for smart-crop scheduling.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload\SmartCrop;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\SmartCrop\CropScheduler;

/**
 * Scheduling must require ALL of: the file recorded in this request, the
 * feature enabled, sizes selected, auto-convert on. The empty-registry case
 * is the important one — it is what keeps restore and bulk rebuilds from
 * ever scheduling crops.
 */
final class CropSchedulerTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		CropScheduler::reset();

		Functions\when( 'get_option' )->alias(
			static function ( string $name, $fallback = false ) {
				if ( 'lw_img_options' === $name ) {
					return [
						'auto_convert'      => true,
						'smartcrop_enabled' => true,
						'smartcrop_sizes'   => [ 'thumbnail' ],
					];
				}
				return $fallback;
			}
		);
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults ) => array_merge( (array) $defaults, (array) $args )
		);
		Functions\when( 'get_attached_file' )->justReturn( '/uploads/2026/08/photo.webp' );
	}

	protected function tearDown(): void {
		// Options caches per request; clear between tests.
		\LightweightPlugins\Img\Options::clear_cache();
		CropScheduler::reset();
		parent::tearDown();
	}

	public function test_schedules_once_for_a_recorded_upload(): void {
		CropScheduler::record( '/uploads/2026/08/photo.webp' );
		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with( \Mockery::type( 'int' ), CropScheduler::HOOK, [ 42 ] );

		$metadata = [ 'sizes' => [] ];
		$this->assertSame( $metadata, CropScheduler::maybe_schedule( $metadata, 42 ) );
	}

	public function test_an_empty_registry_schedules_nothing(): void {
		// This is the restore / bulk / regenerate path: the metadata filter
		// fires, but no wp_handle_upload ran in this request.
		Functions\expect( 'wp_schedule_single_event' )->never();

		$metadata = [ 'sizes' => [] ];
		$this->assertSame( $metadata, CropScheduler::maybe_schedule( $metadata, 42 ) );
	}

	public function test_disabled_feature_schedules_nothing(): void {
		\LightweightPlugins\Img\Options::clear_cache();
		Functions\when( 'get_option' )->alias(
			static function ( string $name, $fallback = false ) {
				if ( 'lw_img_options' === $name ) {
					return [
						'auto_convert'      => true,
						'smartcrop_enabled' => false,
						'smartcrop_sizes'   => [ 'thumbnail' ],
					];
				}
				return $fallback;
			}
		);
		CropScheduler::record( '/uploads/2026/08/photo.webp' );
		Functions\expect( 'wp_schedule_single_event' )->never();

		$metadata = [ 'sizes' => [] ];
		$this->assertSame( $metadata, CropScheduler::maybe_schedule( $metadata, 42 ) );
	}

	public function test_empty_size_selection_schedules_nothing(): void {
		\LightweightPlugins\Img\Options::clear_cache();
		Functions\when( 'get_option' )->alias(
			static function ( string $name, $fallback = false ) {
				if ( 'lw_img_options' === $name ) {
					return [
						'auto_convert'      => true,
						'smartcrop_enabled' => true,
						'smartcrop_sizes'   => [],
					];
				}
				return $fallback;
			}
		);
		CropScheduler::record( '/uploads/2026/08/photo.webp' );
		Functions\expect( 'wp_schedule_single_event' )->never();

		$metadata = [ 'sizes' => [] ];
		$this->assertSame( $metadata, CropScheduler::maybe_schedule( $metadata, 42 ) );
	}

	public function test_non_array_metadata_passes_through_untouched(): void {
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->assertFalse( CropScheduler::maybe_schedule( false, 42 ) );
	}

	public function test_falls_back_to_original_image_sibling_when_core_rewrote_attached_file(): void {
		// Core rewrites _wp_attached_file between wp_handle_upload and this
		// filter for -scaled/-rotated/converted images; get_attached_file()
		// now returns a sibling path the registry never saw. The metadata's
		// original_image carries the pre-rewrite basename, which IS what was
		// recorded.
		Functions\when( 'get_attached_file' )->justReturn( '/uploads/2026/08/photo-scaled.webp' );
		CropScheduler::record( '/uploads/2026/08/photo.webp' );

		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with( \Mockery::type( 'int' ), CropScheduler::HOOK, [ 42 ] );

		$metadata = [
			'sizes'          => [],
			'original_image' => 'photo.webp',
		];
		$this->assertSame( $metadata, CropScheduler::maybe_schedule( $metadata, 42 ) );
	}

	public function test_auto_convert_disabled_schedules_nothing(): void {
		\LightweightPlugins\Img\Options::clear_cache();
		Functions\when( 'get_option' )->alias(
			static function ( string $name, $fallback = false ) {
				if ( 'lw_img_options' === $name ) {
					return [
						'auto_convert'      => false,
						'smartcrop_enabled' => true,
						'smartcrop_sizes'   => [ 'thumbnail' ],
					];
				}
				return $fallback;
			}
		);
		CropScheduler::record( '/uploads/2026/08/photo.webp' );
		Functions\expect( 'wp_schedule_single_event' )->never();

		$metadata = [ 'sizes' => [] ];
		$this->assertSame( $metadata, CropScheduler::maybe_schedule( $metadata, 42 ) );
	}
}
