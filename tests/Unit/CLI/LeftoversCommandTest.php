<?php
/**
 * Tests for `wp lw-img leftovers` output when nothing is left over.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\CLI;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\CLI\Commands;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

if ( ! class_exists( 'WP_CLI', false ) ) {
	require_once dirname( __DIR__, 2 ) . '/Stubs/WpCli.php';
}

/**
 * Regression (#2): with no leftovers, `--format=json` printed the
 * "Success: No leftovers found" prose instead of valid JSON.
 *
 * @covers \LightweightPlugins\Img\CLI\Commands
 */
final class LeftoversCommandTest extends MonkeyTestCase {

	/**
	 * format_items() calls as [ format, items, fields ].
	 *
	 * @var array<int, array{0: string, 1: array<int, mixed>, 2: array<int, string>}>
	 */
	private array $formatted = [];

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::$calls  = [];
		$this->formatted = [];

		Functions\when( 'get_option' )->justReturn(
			[
				'sources'    => [],
				'scanned_at' => 1,
			]
		);
		Functions\when( 'WP_CLI\Utils\get_flag_value' )->alias(
			static fn ( array $assoc, string $flag, $fallback = null ) => $assoc[ $flag ] ?? $fallback
		);
		Functions\when( 'WP_CLI\Utils\format_items' )->alias(
			function ( string $format, array $items, array $fields ): void {
				$this->formatted[] = [ $format, $items, $fields ];
			}
		);
	}

	/**
	 * @dataProvider provide_machine_formats
	 */
	public function test_machine_formats_get_the_empty_structure_and_no_prose( string $format ): void {
		( new Commands() )->leftovers( [], [ 'format' => $format ] );

		$this->assertSame( [ [ $format, [], [ 'source', 'kind', 'location', 'size', 'files' ] ] ], $this->formatted );
		$this->assertSame( [], \WP_CLI::$calls );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_machine_formats(): array {
		return [
			'json'  => [ 'json' ],
			'csv'   => [ 'csv' ],
			'yaml'  => [ 'yaml' ],
			'ids'   => [ 'ids' ],
			'count' => [ 'count' ],
		];
	}

	public function test_table_format_keeps_the_success_message(): void {
		( new Commands() )->leftovers( [], [] );

		$this->assertSame( [], $this->formatted );
		$this->assertSame( 'success', \WP_CLI::$calls[0][0] ?? null );
		$this->assertStringContainsString( 'No leftovers found', \WP_CLI::$calls[0][1] );
	}
}
