<?php
/**
 * Tests for the 7.1 sub-size sideload route detection.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload;

use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\SubsizeSideload;

/**
 * The matcher decides which uploads the interceptor must NOT convert: the
 * per-thumbnail sideloads of WordPress 7.1's client-side uploader. A false
 * positive here would stop converting a real primary image; a false negative
 * costs one API call per thumbnail.
 */
final class SubsizeSideloadTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_routes
	 */
	public function test_matches_only_the_subsize_sideload_route( ?string $route, bool $expected ): void {
		$this->assertSame( $expected, SubsizeSideload::is_route( $route ) );
	}

	/**
	 * @return array<string, array{0: string|null, 1: bool}>
	 */
	public static function provide_routes(): array {
		return [
			'subsize sideload'            => [ '/wp/v2/media/375/sideload', true ],
			'trailing slash'              => [ '/wp/v2/media/375/sideload/', true ],
			'media create (url sideload)' => [ '/wp/v2/media', false ],
			'media item'                  => [ '/wp/v2/media/375', false ],
			'finalize'                    => [ '/wp/v2/media/375/finalize', false ],
			'other namespace lookalike'   => [ '/acme/v1/media/375/sideload-x', false ],
			'null (not a REST request)'   => [ null, false ],
			'empty'                       => [ '', false ],
		];
	}
}
