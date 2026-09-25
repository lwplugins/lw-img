<?php
/**
 * Tests for the pattern-rule parser.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Settings\Input;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Settings\Input\OptionInput;
use LightweightPlugins\Img\Settings\Input\RuleListParser;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Settings\Input\RuleListParser
 */
final class RuleListParserTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'sanitize_text_field' )->alias( static fn ( $value ): string => trim( (string) $value ) );
	}

	public function test_cleans_valid_rows(): void {
		$result = RuleListParser::parse(
			[
				[
					'pattern' => ' *-logo.png ',
					'action'  => 'exclude',
					'value'   => 'ultra',
				],
				[
					'pattern' => '2026/08/*',
					'action'  => 'level',
					'value'   => 'lossless',
				],
			]
		);

		$this->assertSame(
			[
				[
					'pattern' => '*-logo.png',
					'action'  => 'exclude',
					'value'   => '',
				],
				[
					'pattern' => '2026/08/*',
					'action'  => 'level',
					'value'   => 'lossless',
				],
			],
			$result->value()
		);
	}

	public function test_an_empty_list_is_valid(): void {
		$this->assertSame( [], RuleListParser::parse( [] )->value() );
	}

	public function test_reports_each_bad_row_by_position(): void {
		$result = RuleListParser::parse(
			[
				[
					'pattern' => '*.png',
					'action'  => 'exclude',
				],
				[
					'pattern' => '',
					'action'  => 'nope',
				],
				[
					'pattern' => '*.jpg',
					'action'  => 'level',
					'value'   => 'extreme',
				],
			]
		);

		$this->assertFalse( $result->is_valid() );
		$this->assertSame( [ 'rules.1.pattern', 'rules.1.action', 'rules.2.value' ], array_keys( $result->field_errors() ) );
	}

	public function test_row_errors_reach_the_report_next_to_the_setting(): void {
		$report = OptionInput::parse(
			[
				'pattern_rules' => [
					[
						'pattern' => '   ',
						'action'  => 'exclude',
					],
				],
			],
			false
		);

		$this->assertSame( [ 'pattern_rules', 'rules.0.pattern' ], array_keys( $report->errors() ) );
	}

	/**
	 * @dataProvider provide_malformed_lists
	 */
	public function test_refuses_what_is_not_a_list_of_rules( mixed $raw ): void {
		$this->assertFalse( RuleListParser::parse( $raw )->is_valid() );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function provide_malformed_lists(): array {
		return [
			'string'      => [ '*.png' ],
			'keyed map'   => [ [ 'a' => [ 'pattern' => '*.png', 'action' => 'exclude' ] ] ],
			'row string'  => [ [ '*.png' ] ],
			'too many'    => [ array_fill( 0, RuleListParser::MAX_RULES + 1, [ 'pattern' => '*.png', 'action' => 'exclude' ] ) ],
		];
	}
}
