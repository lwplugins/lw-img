<?php
/**
 * Tests for the URL pair builder.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Media;

use LightweightPlugins\Img\Media\UrlPairs;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Media\UrlPairs
 */
final class UrlPairsTest extends MonkeyTestCase {

	private const OLD = 'https://ex.com/wp-content/uploads/2026/08/hero.jpeg';
	private const NEW = 'https://ex.com/wp-content/uploads/2026/08/hero.webp';

	public function test_maps_main_and_sizes_by_size_name(): void {
		$pairs = UrlPairs::build(
			self::OLD,
			[
				'thumbnail' => 'hero-150x150.jpeg',
				'medium'    => 'hero-300x200.jpeg',
			],
			self::NEW,
			[
				'thumbnail' => 'hero-150x150.webp',
				'medium'    => 'hero-300x225.webp',
			]
		);

		$this->assertSame(
			[
				self::OLD => self::NEW,
				'https://ex.com/wp-content/uploads/2026/08/hero-150x150.jpeg' => 'https://ex.com/wp-content/uploads/2026/08/hero-150x150.webp',
				'https://ex.com/wp-content/uploads/2026/08/hero-300x200.jpeg' => 'https://ex.com/wp-content/uploads/2026/08/hero-300x225.webp',
			],
			$pairs
		);
	}

	public function test_missing_new_size_falls_back_to_the_new_main_url(): void {
		$pairs = UrlPairs::build(
			self::OLD,
			[ 'gone_size' => 'hero-99x99.jpeg' ],
			self::NEW,
			[]
		);

		$this->assertSame( self::NEW, $pairs['https://ex.com/wp-content/uploads/2026/08/hero-99x99.jpeg'] );
	}

	public function test_identical_urls_are_dropped(): void {
		$pairs = UrlPairs::build( self::OLD, [ 'thumb' => 'a.jpeg' ], self::OLD, [ 'thumb' => 'a.jpeg' ] );

		$this->assertSame( [], $pairs );
	}

	public function test_empty_inputs_yield_no_pairs(): void {
		$this->assertSame( [], UrlPairs::build( '', [], self::NEW, [] ) );
		$this->assertSame( [], UrlPairs::build( self::OLD, [], '', [] ) );
	}

	public function test_sizes_map_extracts_files_from_metadata(): void {
		$map = UrlPairs::sizes_map(
			[
				'sizes' => [
					'thumbnail' => [ 'file' => 'a-150x150.jpg' ],
					'broken'    => [ 'width' => 10 ],
				],
			]
		);

		$this->assertSame( [ 'thumbnail' => 'a-150x150.jpg' ], $map );
		$this->assertSame( [], UrlPairs::sizes_map( false ) );
	}
}
