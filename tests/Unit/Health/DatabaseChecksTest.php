<?php
/**
 * Tests for the database engine rows built from information_schema.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Health;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Health\DatabaseChecks;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Health\DatabaseChecks
 */
final class DatabaseChecksTest extends MonkeyTestCase {

	/**
	 * Previous $wpdb global.
	 *
	 * @var mixed
	 */
	private $previous_wpdb;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		$this->previous_wpdb = $GLOBALS['wpdb'] ?? null;
	}

	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->previous_wpdb;
		parent::tearDown();
	}

	/**
	 * MySQL 8 returns information_schema column labels in upper case unless
	 * the query aliases them, and some hosts turn the resulting warning into
	 * an exception.
	 *
	 * @dataProvider provide_row_shapes
	 *
	 * @param array<string, string> $row One information_schema row as the server labels it.
	 */
	public function test_reads_engine_whatever_case_the_server_labels_it( array $row ): void {
		$GLOBALS['wpdb'] = $this->fake_wpdb( [ (object) $row ] );

		$rows = DatabaseChecks::rows();

		$this->assertSame( 'InnoDB', $this->row_for( $rows, 'wp_posts' )['message'] );
	}

	/**
	 * @return array<string, array{0: array<string, string>}>
	 */
	public static function provide_row_shapes(): array {
		return [
			'aliased lower case'      => [ [ 'name' => 'wp_posts', 'engine' => 'InnoDB' ] ],
			'MySQL 8 upper case'      => [ [ 'TABLE_NAME' => 'wp_posts', 'ENGINE' => 'InnoDB' ] ],
			'mixed (name alias only)' => [ [ 'name' => 'wp_posts', 'ENGINE' => 'InnoDB' ] ],
		];
	}

	/**
	 * Fake $wpdb returning the given information_schema rows.
	 *
	 * @param array<int, object> $results Rows.
	 * @return object
	 */
	private function fake_wpdb( array $results ): object {
		return new class( $results ) {
			public string $posts       = 'wp_posts';
			public string $postmeta    = 'wp_postmeta';
			public string $options     = 'wp_options';
			public string $comments    = 'wp_comments';
			public string $commentmeta = 'wp_commentmeta';
			public string $usermeta    = 'wp_usermeta';
			public string $termmeta    = 'wp_termmeta';

			/**
			 * @param array<int, object> $results Rows.
			 */
			public function __construct( private array $results ) {
			}

			public function get_var( string $query ): string {
				return '8.0.37';
			}

			/**
			 * @param mixed ...$args Ignored.
			 */
			public function prepare( string $query, ...$args ): string {
				return $query;
			}

			/**
			 * @return array<int, object>
			 */
			public function get_results( string $query ): array {
				return $this->results;
			}
		};
	}

	/**
	 * @param array<int, array<string, string>> $rows  Check rows.
	 * @param string                            $label Row label.
	 * @return array<string, string>
	 */
	private function row_for( array $rows, string $label ): array {
		foreach ( $rows as $row ) {
			if ( $label === $row['label'] ) {
				return $row;
			}
		}
		$this->fail( "No row for {$label}" );
	}
}
