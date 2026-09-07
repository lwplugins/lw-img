<?php
/**
 * Tests for RuleSet resolution semantics.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload\Rules;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\Rules\RuleSet;

/**
 * @covers \LightweightPlugins\Img\Upload\Rules\RuleSet
 * @covers \LightweightPlugins\Img\Upload\Rules\RuleMatch
 */
final class RuleSetTest extends MonkeyTestCase {

	private const FILE = '/srv/uploads/2026/09/hero-full.jpg';

	public function test_no_rules_matches_nothing(): void {
		$match = ( new RuleSet( [] ) )->resolve( self::FILE );

		$this->assertFalse( $match->excluded );
		$this->assertFalse( $match->keep_size );
		$this->assertNull( $match->level );
		$this->assertFalse( $match->keep_exif );
		$this->assertSame( 0, $match->matched );
	}

	public function test_each_action_sets_its_flag(): void {
		$rules = new RuleSet(
			[
				[ 'pattern' => '*-full.jpg', 'action' => 'keep_size', 'value' => '' ],
				[ 'pattern' => '2026/09/*', 'action' => 'level', 'value' => 'ultra' ],
				[ 'pattern' => 'hero-*', 'action' => 'keep_exif', 'value' => '' ],
			]
		);

		$match = $rules->resolve( self::FILE );

		$this->assertFalse( $match->excluded );
		$this->assertTrue( $match->keep_size );
		$this->assertSame( 'ultra', $match->level );
		$this->assertTrue( $match->keep_exif );
		$this->assertSame( 3, $match->matched );
	}

	public function test_exclude_wins_over_everything(): void {
		$rules = new RuleSet(
			[
				[ 'pattern' => '*.jpg', 'action' => 'level', 'value' => 'ultra' ],
				[ 'pattern' => 'hero-*', 'action' => 'exclude', 'value' => '' ],
			]
		);

		$this->assertTrue( $rules->resolve( self::FILE )->excluded );
	}

	public function test_first_matching_level_rule_wins(): void {
		$rules = new RuleSet(
			[
				[ 'pattern' => '*.jpg', 'action' => 'level', 'value' => 'lossless' ],
				[ 'pattern' => 'hero-*', 'action' => 'level', 'value' => 'ultra' ],
			]
		);

		$this->assertSame( 'lossless', $rules->resolve( self::FILE )->level );
	}

	public function test_invalid_level_value_is_ignored(): void {
		$rules = new RuleSet( [ [ 'pattern' => '*.jpg', 'action' => 'level', 'value' => 'bogus' ] ] );

		$match = $rules->resolve( self::FILE );

		$this->assertNull( $match->level );
		$this->assertSame( 1, $match->matched );
	}

	public function test_non_matching_and_malformed_rules_are_skipped(): void {
		$rules = new RuleSet(
			[
				[ 'pattern' => '*.png', 'action' => 'exclude', 'value' => '' ],
				[ 'pattern' => '', 'action' => 'exclude', 'value' => '' ],
				[ 'pattern' => '*.jpg', 'action' => 'teleport', 'value' => '' ],
				'not-an-array',
			]
		);

		$match = $rules->resolve( self::FILE );

		$this->assertFalse( $match->excluded );
		$this->assertSame( 0, $match->matched );
		$this->assertSame( 4, $rules->count() );
	}

	public function test_from_options_reads_the_pattern_rules_option(): void {
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
		Functions\when( 'get_option' )->justReturn(
			[ 'pattern_rules' => [ [ 'pattern' => 'hero-*', 'action' => 'keep_size', 'value' => '' ] ] ]
		);
		Options::clear_cache();

		$this->assertTrue( RuleSet::from_options()->resolve( self::FILE )->keep_size );

		Options::clear_cache();
	}
}
