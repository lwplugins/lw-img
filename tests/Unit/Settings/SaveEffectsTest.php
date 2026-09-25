<?php
/**
 * Tests for the settings save side effects.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Settings\SaveEffects;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Settings\SaveEffects
 */
final class SaveEffectsTest extends MonkeyTestCase {

	/**
	 * Transients deleted during the test.
	 *
	 * @var array<int, string>
	 */
	private array $deleted = [];

	protected function setUp(): void {
		parent::setUp();
		$this->deleted = [];
		Functions\when( 'delete_transient' )->alias(
			function ( string $name ): bool {
				$this->deleted[] = $name;
				return true;
			}
		);
	}

	public function test_changed_keys_lists_only_differences(): void {
		$this->assertSame(
			[ 'level', 'keep_exif' ],
			SaveEffects::changed_keys(
				[
					'level'     => 'normal',
					'max_width' => 0,
					'keep_exif' => false,
				],
				[
					'level'     => 'ultra',
					'max_width' => 0,
					'keep_exif' => true,
				]
			)
		);
	}

	public function test_an_unchanged_save_has_no_effects(): void {
		SaveEffects::apply( [ 'level' => 'normal' ], [ 'level' => 'normal' ] );

		$this->assertSame( [], $this->deleted );
	}

	public function test_any_change_drops_the_tester_report_and_probe_verdict(): void {
		SaveEffects::apply( [ 'level' => 'normal' ], [ 'level' => 'ultra' ] );

		$this->assertSame( [ 'lw_img_health_report', 'lw_img_redirect_probe' ], $this->deleted );
	}

	public function test_a_new_key_drops_the_cached_account(): void {
		SaveEffects::apply( [ 'api_key' => 'himg_a' ], [ 'api_key' => 'himg_b' ] );

		$this->assertContains( 'lw_img_account', $this->deleted );
	}

	public function test_a_new_mime_list_drops_the_pending_count(): void {
		SaveEffects::apply( [ 'mime_types' => [ 'image/jpeg' ] ], [ 'mime_types' => [ 'image/png' ] ] );

		$this->assertContains( 'lw_img_pending_count', $this->deleted );
	}
}
