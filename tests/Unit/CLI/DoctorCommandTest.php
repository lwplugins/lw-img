<?php
/**
 * Tests for the doctor command's report flattening.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\CLI;

use LightweightPlugins\Img\CLI\DoctorCommand;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\CLI\DoctorCommand
 */
final class DoctorCommandTest extends MonkeyTestCase {

	private const SECTIONS = [
		'database' => [
			[ 'label' => 'Engine', 'status' => 'ok', 'message' => 'InnoDB' ],
		],
		'redirects' => [
			[
				'label'   => 'Old-image redirects',
				'status'  => 'warning',
				'message' => 'swallowed',
				'fix'     => 'location ...',
			],
		],
	];

	public function test_flatten_produces_one_row_per_check_with_its_section(): void {
		$rows = DoctorCommand::flatten( self::SECTIONS );

		$this->assertCount( 2, $rows );
		$this->assertSame(
			[
				'section' => 'database',
				'check'   => 'Engine',
				'status'  => 'ok',
				'message' => 'InnoDB',
			],
			$rows[0]
		);
		$this->assertSame( 'redirects', $rows[1]['section'] );
		$this->assertSame( 'location ...', $rows[1]['fix'] ?? null );
	}

	public function test_counts_by_status(): void {
		$rows = DoctorCommand::flatten( self::SECTIONS );

		$this->assertSame( [ 'ok' => 1, 'warning' => 1 ], DoctorCommand::status_counts( $rows ) );
	}
}
