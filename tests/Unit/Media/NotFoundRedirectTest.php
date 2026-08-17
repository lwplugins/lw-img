<?php
/**
 * Tests for the 404-redirect request parsing.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Media;

use LightweightPlugins\Img\Media\NotFoundRedirect;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Media\NotFoundRedirect
 */
final class NotFoundRedirectTest extends MonkeyTestCase {

	private const BASE = '/wp-content/uploads';

	public function test_parses_a_main_file_request(): void {
		$this->assertSame(
			[
				'rel'      => '2026/08/hero.jpeg',
				'stem_rel' => '2026/08/hero',
				'width'    => 0,
			],
			NotFoundRedirect::parse_request( self::BASE . '/2026/08/hero.jpeg', self::BASE )
		);
	}

	public function test_extracts_the_width_from_a_sub_size_request(): void {
		$parsed = NotFoundRedirect::parse_request( self::BASE . '/2026/08/hero-300x200.jpg', self::BASE );

		$this->assertNotNull( $parsed );
		$this->assertSame( '2026/08/hero-300x200.jpg', $parsed['rel'] );
		$this->assertSame( '2026/08/hero', $parsed['stem_rel'] );
		$this->assertSame( 300, $parsed['width'] );
	}

	public function test_decodes_url_encoded_names(): void {
		$parsed = NotFoundRedirect::parse_request( self::BASE . '/2026/08/kép%20egy.png', self::BASE );

		$this->assertNotNull( $parsed );
		$this->assertSame( '2026/08/kép egy.png', $parsed['rel'] );
	}

	/**
	 * @dataProvider provide_rejected_paths
	 */
	public function test_rejects_paths_outside_scope( string $path ): void {
		$this->assertNull( NotFoundRedirect::parse_request( $path, self::BASE ) );
	}

	/**
	 * Paths the handler must ignore.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_rejected_paths(): array {
		return [
			'not under uploads'   => [ '/wp-content/plugins/x/a.jpg' ],
			'already webp'        => [ self::BASE . '/2026/08/hero.webp' ],
			'not an image'        => [ self::BASE . '/2026/08/doc.pdf' ],
			'no extension'        => [ self::BASE . '/2026/08/hero' ],
			'path traversal'      => [ self::BASE . '/../wp-config.jpg' ],
			'uploads base itself' => [ self::BASE ],
		];
	}
}
