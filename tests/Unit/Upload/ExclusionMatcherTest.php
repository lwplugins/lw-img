<?php
/**
 * Tests for the ExclusionMatcher wildcard rules.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload;

use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\ExclusionMatcher;

/**
 * @covers \LightweightPlugins\Img\Upload\ExclusionMatcher
 */
final class ExclusionMatcherTest extends MonkeyTestCase {

	private const FILE = '/srv/uploads/2026/08/Holiday-Original.JPG';

	/**
	 * @dataProvider provide_patterns
	 */
	public function test_wildcard_matching( string $pattern, bool $expected ): void {
		$this->assertSame( $expected, ( new ExclusionMatcher() )->matches( self::FILE, [ $pattern ] ) );
	}

	/**
	 * Pattern cases against /srv/uploads/2026/08/Holiday-Original.JPG.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function provide_patterns(): array {
		return [
			'filename wildcard'          => [ '*-original.jpg', true ],
			'filename exact, case-insensitive' => [ 'holiday-original.jpg', true ],
			'filename question mark'     => [ 'Holiday-Origina?.jpg', true ],
			'filename non-match'         => [ '*.png', false ],
			'filename must match fully'  => [ 'Holiday', false ],
			'path substring'             => [ '2026/08/*', true ],
			'path non-match'             => [ '2025/12/*', false ],
			'path with wildcard middle'  => [ 'uploads/*/08', true ],
		];
	}

	public function test_empty_and_blank_patterns_never_match(): void {
		$matcher = new ExclusionMatcher();

		$this->assertFalse( $matcher->matches( self::FILE, [] ) );
		$this->assertFalse( $matcher->matches( self::FILE, [ '', '   ' ] ) );
	}

	public function test_regex_special_characters_are_literal(): void {
		$matcher = new ExclusionMatcher();

		$this->assertFalse( $matcher->matches( '/up/aXb.jpg', [ 'a.b.jpg' ] ) );
		$this->assertTrue( $matcher->matches( '/up/a.b.jpg', [ 'a.b.jpg' ] ) );
	}
}
