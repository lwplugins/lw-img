<?php
/**
 * Tests for the `wp lw-img list` row shaping.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\CLI;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\CLI\ListCommand;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\CLI\ListCommand
 */
final class ListCommandTest extends MonkeyTestCase {

	public function test_rows_carry_id_file_and_sizes(): void {
		Functions\when( 'get_attached_file' )->alias( static fn ( int $id ): string => "/up/img-{$id}.jpg" );

		$rows = ListCommand::rows(
			[
				[
					'attachment_id' => '7',
					'status'        => 'skipped',
					'detail'        => 'result not smaller',
					'orig_size'     => '1000',
					'new_size'      => '1100',
				],
			]
		);

		$this->assertSame(
			[
				[
					'id'        => 7,
					'file'      => 'img-7.jpg',
					'status'    => 'skipped',
					'detail'    => 'result not smaller',
					'orig_size' => 1000,
					'new_size'  => 1100,
				],
			],
			$rows
		);
	}

	public function test_status_must_be_known(): void {
		$this->assertTrue( ListCommand::valid_status( 'failed' ) );
		$this->assertFalse( ListCommand::valid_status( 'pending' ) );
		$this->assertFalse( ListCommand::valid_status( '' ) );
	}
}
