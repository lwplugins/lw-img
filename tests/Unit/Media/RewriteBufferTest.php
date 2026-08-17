<?php
/**
 * Tests for the batched rewrite accumulator.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Media;

use LightweightPlugins\Img\Media\RewriteBuffer;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Media\RewriteBuffer
 */
final class RewriteBufferTest extends MonkeyTestCase {

	/**
	 * Batches handed to the injected applier.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $applied = [];

	private function buffer(): RewriteBuffer {
		return new RewriteBuffer(
			function ( array $pairs ): void {
				$this->applied[] = $pairs;
			}
		);
	}

	public function test_add_accumulates_without_applying(): void {
		$buffer = $this->buffer();

		$buffer->add( [ 'https://ex.com/a.jpg' => 'https://ex.com/a.webp' ] );
		$buffer->add( [ 'https://ex.com/b.jpg' => 'https://ex.com/b.webp' ] );

		$this->assertSame( [], $this->applied );
	}

	public function test_flush_applies_the_merged_batch_once_and_empties_the_buffer(): void {
		$buffer = $this->buffer();

		$buffer->add( [ 'https://ex.com/a.jpg' => 'https://ex.com/a.webp' ] );
		$buffer->add( [ 'https://ex.com/b.jpg' => 'https://ex.com/b.webp' ] );
		$buffer->flush();
		$buffer->flush();

		$this->assertSame(
			[
				[
					'https://ex.com/a.jpg' => 'https://ex.com/a.webp',
					'https://ex.com/b.jpg' => 'https://ex.com/b.webp',
				],
			],
			$this->applied
		);
	}

	public function test_flushes_automatically_when_the_threshold_is_reached(): void {
		$buffer = $this->buffer();

		for ( $i = 1; $i <= 200; $i++ ) {
			$buffer->add( [ "https://ex.com/img-{$i}.jpg" => "https://ex.com/img-{$i}.webp" ] );
		}

		$this->assertCount( 1, $this->applied );
		$this->assertCount( 200, $this->applied[0] );
	}

	public function test_chained_pair_forces_a_flush_so_passes_stay_ordered(): void {
		$buffer = $this->buffer();

		$buffer->add( [ 'https://ex.com/a.jpg' => 'https://ex.com/a.webp' ] );
		// The next pair's old URL is the previous pair's target: it must not
		// be merged into the same pass.
		$buffer->add( [ 'https://ex.com/a.webp' => 'https://ex.com/a.avif' ] );
		$buffer->flush();

		$this->assertSame(
			[
				[ 'https://ex.com/a.jpg' => 'https://ex.com/a.webp' ],
				[ 'https://ex.com/a.webp' => 'https://ex.com/a.avif' ],
			],
			$this->applied
		);
	}
}
