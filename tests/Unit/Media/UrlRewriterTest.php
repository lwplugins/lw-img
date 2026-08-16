<?php
/**
 * Tests for the serialization-aware value rewrite.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Media;

use LightweightPlugins\Img\Media\UrlRewriter;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Media\UrlRewriter
 */
final class UrlRewriterTest extends MonkeyTestCase {

	private const PAIRS = [
		'https://ex.com/up/2026/08/hero.jpeg' => 'https://ex.com/up/2026/08/hero.webp',
	];

	public function test_replaces_in_plain_strings(): void {
		$this->assertSame(
			'<img src="https://ex.com/up/2026/08/hero.webp">',
			UrlRewriter::replace_in_value( '<img src="https://ex.com/up/2026/08/hero.jpeg">', self::PAIRS )
		);
	}

	public function test_replaces_json_escaped_urls_in_builder_data(): void {
		$elementor_json = '{"url":"https:\/\/ex.com\/up\/2026\/08\/hero.jpeg","id":42}';

		$this->assertSame(
			'{"url":"https:\/\/ex.com\/up\/2026\/08\/hero.webp","id":42}',
			UrlRewriter::replace_in_value( $elementor_json, self::PAIRS )
		);
	}

	public function test_walks_nested_arrays_and_objects(): void {
		$value = [
			'a' => [ 'url' => 'https://ex.com/up/2026/08/hero.jpeg' ],
			'b' => (object) [ 'src' => 'x https://ex.com/up/2026/08/hero.jpeg y' ],
			'c' => 42,
		];

		$result = UrlRewriter::replace_in_value( $value, self::PAIRS );

		$this->assertSame( 'https://ex.com/up/2026/08/hero.webp', $result['a']['url'] );
		$this->assertSame( 'x https://ex.com/up/2026/08/hero.webp y', $result['b']->src );
		$this->assertSame( 42, $result['c'] );
	}

	public function test_serialized_length_prefixes_stay_correct_after_reserialize(): void {
		// .jpeg -> .webp is same-length, so make the change length-affecting
		// via the pair below to prove the unserialize->replace->serialize path.
		$pairs      = [ 'https://ex.com/up/a.jpg' => 'https://ex.com/up/a.webp' ];
		$serialized = serialize( [ 'url' => 'https://ex.com/up/a.jpg' ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- simulating a stored postmeta value.

		$decoded = unserialize( $serialized ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- test fixture round-trip.
		$updated = UrlRewriter::replace_in_value( $decoded, $pairs );
		$stored  = serialize( $updated ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- simulating the re-stored value.

		$this->assertSame( [ 'url' => 'https://ex.com/up/a.webp' ], unserialize( $stored ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- asserting valid serialization.
	}
}
