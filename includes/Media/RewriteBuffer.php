<?php
/**
 * Accumulates URL rewrite pairs so table scans amortize across a batch.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Rewriting content per image costs a full scan of every content table.
 * During bulk runs the pairs of many images are collected here and applied
 * in one pass, so a batch of images costs the same scans as a single one.
 */
final class RewriteBuffer {

	private const FLUSH_THRESHOLD = 200;

	/**
	 * Applies a batch of pairs (defaults to UrlRewriter::rewrite()).
	 *
	 * @var callable(array<string, string>): void
	 */
	private $apply;

	/**
	 * Accumulated map of old URL => new URL.
	 *
	 * @var array<string, string>
	 */
	private array $pairs = [];

	/**
	 * @param callable|null $apply Batch applier override (tests); null uses UrlRewriter.
	 */
	public function __construct( ?callable $apply = null ) {
		$this->apply = $apply ?? static function ( array $pairs ): void {
			( new UrlRewriter() )->rewrite( $pairs );
		};
	}

	/**
	 * Queue one image's pairs; flushes automatically at the threshold.
	 *
	 * @param array<string, string> $pairs Map of old URL => new URL.
	 * @return void
	 */
	public function add( array $pairs ): void {
		foreach ( $pairs as $old_url => $new_url ) {
			// A pair whose old URL is another pair's target must not be
			// merged into the same pass — apply what we have first.
			if ( in_array( $old_url, $this->pairs, true ) ) {
				$this->flush();
			}

			$this->pairs[ $old_url ] = $new_url;
		}

		if ( count( $this->pairs ) >= self::FLUSH_THRESHOLD ) {
			$this->flush();
		}
	}

	/**
	 * Apply everything queued so far.
	 *
	 * @return void
	 */
	public function flush(): void {
		if ( [] === $this->pairs ) {
			return;
		}

		$pairs       = $this->pairs;
		$this->pairs = [];

		( $this->apply )( $pairs );
	}
}
