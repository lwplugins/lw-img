<?php
/**
 * Tests for the exclusion_patterns → pattern_rules migration.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Upload\Rules;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Img\Upload\Rules\RuleMigration;

/**
 * @covers \LightweightPlugins\Img\Upload\Rules\RuleMigration
 */
final class RuleMigrationTest extends MonkeyTestCase {

	public function test_legacy_patterns_become_exclude_rules_ahead_of_existing_rules(): void {
		$migrated = RuleMigration::migrate(
			[
				'level'              => 'normal',
				'exclusion_patterns' => [ '*-logo.png', '', '2026/08/*' ],
				'pattern_rules'      => [ [ 'pattern' => 'x.jpg', 'action' => 'keep_size', 'value' => '' ] ],
			]
		);

		$this->assertNotNull( $migrated );
		$this->assertArrayNotHasKey( 'exclusion_patterns', $migrated );
		$this->assertSame( 'normal', $migrated['level'] );
		$this->assertSame(
			[
				[ 'pattern' => '*-logo.png', 'action' => 'exclude', 'value' => '' ],
				[ 'pattern' => '2026/08/*', 'action' => 'exclude', 'value' => '' ],
				[ 'pattern' => 'x.jpg', 'action' => 'keep_size', 'value' => '' ],
			],
			$migrated['pattern_rules']
		);
	}

	public function test_nothing_to_migrate_returns_null(): void {
		$this->assertNull( RuleMigration::migrate( [ 'level' => 'normal' ] ) );
		$this->assertNull( RuleMigration::migrate( [ 'pattern_rules' => [] ] ) );
	}

	public function test_empty_legacy_list_still_removes_the_key(): void {
		$migrated = RuleMigration::migrate( [ 'exclusion_patterns' => [] ] );

		$this->assertSame( [ 'pattern_rules' => [] ], $migrated );
	}

	public function test_run_saves_once_and_is_idempotent(): void {
		$saved = [ 'exclusion_patterns' => [ 'a.jpg' ] ];
		Functions\when( 'get_option' )->alias(
			static function () use ( &$saved ) {
				return $saved;
			}
		);
		Functions\expect( 'update_option' )->once()->andReturnUsing(
			static function ( string $name, array $value ) use ( &$saved ): bool {
				$saved = $value;
				return true;
			}
		);

		RuleMigration::run();
		RuleMigration::run();

		$this->assertSame( [ 'pattern_rules' => [ [ 'pattern' => 'a.jpg', 'action' => 'exclude', 'value' => '' ] ] ], $saved );
		Options::clear_cache();
	}
}
