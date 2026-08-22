<?php
/**
 * Tests for the smartcrop CLI command's sizes-override window.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\CLI;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LightweightPlugins\Img\CLI\SmartCropCommand;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;
use RuntimeException;

/**
 * override_sizes()/restore_sizes() are the only place --sizes reaches
 * ThumbnailCropper: a request-scoped `option_{name}` filter, never a
 * database write. These tests guard the two things that matter — the
 * window opens and closes around the option filter, and it closes even
 * when something throws mid-run, so a future exception can never leave
 * the override attached (which would otherwise risk a later
 * Options::set()/save() persisting it into the real saved settings).
 */
final class SmartCropCommandTest extends MonkeyTestCase {

	private const HOOK = 'option_' . Options::OPTION_NAME;

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_override_sizes_adds_the_filter_and_restore_sizes_removes_it(): void {
		$this->assertFalse( Filters\has( self::HOOK ) );

		$callback = SmartCropCommand::override_sizes( [ 'thumbnail' ] );

		$this->assertNotFalse( Filters\has( self::HOOK ) );

		SmartCropCommand::restore_sizes( $callback );

		$this->assertFalse( Filters\has( self::HOOK ) );
	}

	public function test_override_window_closes_and_never_persists_when_an_exception_interrupts_the_run(): void {
		Functions\expect( 'update_option' )->never();

		$callback = SmartCropCommand::override_sizes( [ 'thumbnail' ] );

		try {
			try {
				throw new RuntimeException( 'simulated failure mid-run' );
			} finally {
				SmartCropCommand::restore_sizes( $callback );
			}
			$this->fail( 'The simulated exception did not propagate.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'simulated failure mid-run', $e->getMessage() );
		}

		$this->assertFalse( Filters\has( self::HOOK ) );
	}
}
