<?php
/**
 * Tests for the RetentionCleaner expiry selection.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Backup;

use LightweightPlugins\Img\Backup\RetentionCleaner;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Backup\RetentionCleaner
 */
final class RetentionCleanerTest extends MonkeyTestCase {

	private const NOW = 1_800_000_000;

	public function test_selects_only_files_older_than_the_retention_window(): void {
		$files = [
			'/b/old.jpg'      => self::NOW - ( 31 * DAY_IN_SECONDS ),
			'/b/fresh.jpg'    => self::NOW - ( 5 * DAY_IN_SECONDS ),
			'/b/ancient.jpg'  => self::NOW - ( 400 * DAY_IN_SECONDS ),
			'/b/boundary.jpg' => self::NOW - ( 30 * DAY_IN_SECONDS ),
		];

		$expired = RetentionCleaner::filter_expired( $files, self::NOW, 30 );

		$this->assertSame( [ '/b/old.jpg', '/b/ancient.jpg' ], $expired );
	}

	/**
	 * @dataProvider provide_disabled_retention
	 */
	public function test_zero_or_negative_retention_keeps_everything( int $days ): void {
		$files = [ '/b/ancient.jpg' => 0 ];

		$this->assertSame( [], RetentionCleaner::filter_expired( $files, self::NOW, $days ) );
	}

	/**
	 * Disabled retention values.
	 *
	 * @return array<string, array{int}>
	 */
	public static function provide_disabled_retention(): array {
		return [
			'zero'     => [ 0 ],
			'negative' => [ -1 ],
		];
	}

	public function test_empty_file_list_yields_empty_result(): void {
		$this->assertSame( [], RetentionCleaner::filter_expired( [], self::NOW, 30 ) );
	}
}
