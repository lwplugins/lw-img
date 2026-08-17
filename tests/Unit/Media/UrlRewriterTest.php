<?php
/**
 * Tests for the serialization-aware value rewrite.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Media;

use Brain\Monkey\Functions;
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

	/**
	 * @dataProvider provide_prefix_cases
	 *
	 * @param array<int, string> $strings  Input strings.
	 * @param string             $expected Expected common prefix.
	 */
	public function test_common_prefix( array $strings, string $expected ): void {
		$this->assertSame( $expected, UrlRewriter::common_prefix( $strings ) );
	}

	/**
	 * @return array<string, array{array<int, string>, string}>
	 */
	public static function provide_prefix_cases(): array {
		return [
			'shared uploads base'  => [
				[ 'https://ex.com/up/2026/08/a.jpg', 'https://ex.com/up/2026/07/b.png', 'https://ex.com/up/2010/01/c.gif' ],
				'https://ex.com/up/20',
			],
			'single string'        => [ [ 'https://ex.com/up/a.jpg' ], 'https://ex.com/up/a.jpg' ],
			'no common prefix'     => [ [ 'https://a.com/x.jpg', 'https://b.com/y.jpg' ], 'https://' ],
			'completely different' => [ [ 'abc', 'xyz' ], '' ],
		];
	}

	/**
	 * Stub is_serialized() with a realistic prefix check.
	 *
	 * @return void
	 */
	private function stub_is_serialized(): void {
		Functions\when( 'is_serialized' )->alias(
			static fn ( $data ): bool => is_string( $data ) && ( 'N;' === $data || (bool) preg_match( '/^[aOsibd]:/', $data ) )
		);
	}

	public function test_decode_passes_plain_strings_through_as_rewritable(): void {
		$this->stub_is_serialized();

		$this->assertSame(
			[ 'https://ex.com/up/a.jpg', true ],
			UrlRewriter::decode_for_rewrite( 'https://ex.com/up/a.jpg' )
		);
	}

	public function test_decode_returns_array_for_safe_serialized_data(): void {
		$this->stub_is_serialized();
		$raw = serialize( [ 'url' => 'https://ex.com/up/a.jpg', 'w' => 100 ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture.

		[ $decoded, $rewritable ] = UrlRewriter::decode_for_rewrite( $raw );

		$this->assertTrue( $rewritable );
		$this->assertSame( [ 'url' => 'https://ex.com/up/a.jpg', 'w' => 100 ], $decoded );
	}

	public function test_decode_refuses_serialized_objects_object_injection_guard(): void {
		$this->stub_is_serialized();
		// A serialized stdClass — maybe_unserialize would instantiate it.
		$raw = 'O:8:"stdClass":1:{s:3:"url";s:23:"https://ex.com/up/a.jpg";}';

		$this->assertSame( [ null, false ], UrlRewriter::decode_for_rewrite( $raw ) );
	}

	public function test_decode_refuses_objects_nested_in_arrays(): void {
		$this->stub_is_serialized();
		$raw = 'a:1:{s:2:"pl";O:8:"stdClass":0:{}}';

		$this->assertSame( [ null, false ], UrlRewriter::decode_for_rewrite( $raw ) );
	}

	public function test_decode_refuses_literal_false_and_undecodable(): void {
		$this->stub_is_serialized();

		$this->assertSame( [ null, false ], UrlRewriter::decode_for_rewrite( 'b:0;' ) );
		$this->assertSame( [ null, false ], UrlRewriter::decode_for_rewrite( 'a:1:{s:1:"x"' ) );
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
