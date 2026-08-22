<?php
/**
 * Tests for the smart-crop size selection rules.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload\SmartCrop;

use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\SmartCrop\SizeCatalog;

/**
 * Every rule that decides which sizes get an API call lives here, pure, so
 * the expensive part of the feature is the tested part.
 */
final class SizeCatalogTest extends MonkeyTestCase {

	/**
	 * A registered size map in wp_get_registered_image_subsizes() shape.
	 *
	 * @return array<string, array{width: int, height: int, crop: bool}>
	 */
	private function registered(): array {
		return [
			'thumbnail'    => [
				'width'  => 150,
				'height' => 150,
				'crop'   => true,
			],
			'shop_catalog' => [
				'width'  => 450,
				'height' => 450,
				'crop'   => true,
			],
			'medium'       => [
				'width'  => 300,
				'height' => 300,
				'crop'   => false,
			],
		];
	}

	/**
	 * Attachment metadata in wp_get_attachment_metadata() shape: a wide
	 * 2000x1000 original whose crops WP generated at their registered dims.
	 *
	 * @return array<string, mixed>
	 */
	private function metadata(): array {
		return [
			'width'  => 2000,
			'height' => 1000,
			'sizes'  => [
				'thumbnail'    => [
					'file'   => 'photo-150x150.webp',
					'width'  => 150,
					'height' => 150,
				],
				'shop_catalog' => [
					'file'   => 'photo-450x450.webp',
					'width'  => 450,
					'height' => 450,
				],
				'medium'       => [
					'file'   => 'photo-300x150.webp',
					'width'  => 300,
					'height' => 150,
				],
			],
		];
	}

	public function test_returns_only_selected_hard_crop_sizes(): void {
		$jobs = SizeCatalog::jobs( [ 'shop_catalog' ], $this->registered(), $this->metadata() );

		$this->assertSame(
			[
				[
					'name'   => 'shop_catalog',
					'width'  => 450,
					'height' => 450,
					'file'   => 'photo-450x450.webp',
				],
			],
			$jobs
		);
	}

	public function test_a_scale_size_never_becomes_a_job_even_when_selected(): void {
		// 'medium' is crop=false: nothing is cut off it, an API call is waste.
		$this->assertSame( [], SizeCatalog::jobs( [ 'medium' ], $this->registered(), $this->metadata() ) );
	}

	public function test_a_size_wp_did_not_generate_is_skipped(): void {
		$metadata = $this->metadata();
		unset( $metadata['sizes']['shop_catalog'] );

		$this->assertSame( [], SizeCatalog::jobs( [ 'shop_catalog' ], $this->registered(), $metadata ) );
	}

	public function test_uses_the_generated_dimensions_not_the_registered_ones(): void {
		// WP scales a crop down when the source is smaller than the registered
		// size; cropping at registered dims would corrupt srcset.
		$metadata                            = $this->metadata();
		$metadata['sizes']['shop_catalog'] = [
			'file'   => 'photo-400x400.webp',
			'width'  => 400,
			'height' => 400,
		];

		$jobs = SizeCatalog::jobs( [ 'shop_catalog' ], $this->registered(), $metadata );

		$this->assertSame( 400, $jobs[0]['width'] );
		$this->assertSame( 400, $jobs[0]['height'] );
	}

	public function test_skips_a_size_whose_ratio_equals_the_originals(): void {
		// 2000x1000 original, 300x150 crop: same 2.0 ratio — cropping removes
		// nothing, the WP thumbnail is already correct.
		$registered              = $this->registered();
		$registered['banner'] = [
			'width'  => 300,
			'height' => 150,
			'crop'   => true,
		];
		$metadata                     = $this->metadata();
		$metadata['sizes']['banner'] = [
			'file'   => 'photo-300x150.webp',
			'width'  => 300,
			'height' => 150,
		];

		$this->assertSame( [], SizeCatalog::jobs( [ 'banner' ], $registered, $metadata ) );
	}

	public function test_ratio_comparison_rounds_to_two_decimals(): void {
		// 1998x1000 (1.998 → 2.00) vs a 300x150 (2.00) crop: equal after
		// rounding, still a skip — this is the boundary the rule names.
		$registered              = $this->registered();
		$registered['banner'] = [
			'width'  => 300,
			'height' => 150,
			'crop'   => true,
		];
		$metadata                     = $this->metadata();
		$metadata['width']            = 1998;
		$metadata['sizes']['banner'] = [
			'file'   => 'photo-300x150.webp',
			'width'  => 300,
			'height' => 150,
		];

		$this->assertSame( [], SizeCatalog::jobs( [ 'banner' ], $registered, $metadata ) );
	}

	public function test_handles_degenerate_metadata_without_dividing_by_zero(): void {
		$metadata           = $this->metadata();
		$metadata['height'] = 0;

		$this->assertSame( [], SizeCatalog::jobs( [ 'thumbnail' ], $this->registered(), $metadata ) );
	}

	public function test_empty_selection_yields_no_jobs(): void {
		$this->assertSame( [], SizeCatalog::jobs( [], $this->registered(), $this->metadata() ) );
	}
}
