<?php
/**
 * Tests for the log query.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Log;

use LightweightPlugins\Img\Log\LogEntryView;
use LightweightPlugins\Img\Log\LogQuery;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Img\Log\LogQuery
 * @covers \LightweightPlugins\Img\Log\LogEntryView
 */
final class LogQueryTest extends TestCase {

	/**
	 * Sample log, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function entries(): array {
		return [
			[ 'status' => 'converted', 'file' => 'Beach.JPG' ],
			[ 'status' => 'skipped', 'file' => 'logo.png' ],
			[ 'status' => 'failed', 'file' => 'beach-2.jpg' ],
			[ 'status' => 'converted', 'file' => 'cat.png' ],
			[ 'status' => 'restored', 'file' => 'dog.jpg' ],
		];
	}

	public function test_filters_by_type(): void {
		$result = LogQuery::run( self::entries(), 1, 25, 'converted', '' );

		$this->assertSame( [ 'Beach.JPG', 'cat.png' ], array_column( $result['entries'], 'file' ) );
		$this->assertSame( 2, $result['total'] );
	}

	public function test_searches_file_names_case_insensitively(): void {
		$result = LogQuery::run( self::entries(), 1, 25, 'all', 'BEACH' );

		$this->assertSame( [ 0, 2 ], array_column( $result['entries'], 'id' ) );
	}

	public function test_counts_cover_the_whole_log(): void {
		$result = LogQuery::run( self::entries(), 1, 25, 'failed', 'nothing-matches' );

		$this->assertSame(
			[
				'all'       => 5,
				'converted' => 2,
				'skipped'   => 1,
				'failed'    => 1,
				'restored'  => 1,
			],
			$result['counts']
		);
	}

	public function test_pages_and_clamps_the_page_number(): void {
		$result = LogQuery::run( self::entries(), 9, 2, '', '' );

		$this->assertSame( 3, $result['pages'] );
		$this->assertSame( 3, $result['page'] );
		$this->assertSame( [ 'dog.jpg' ], array_column( $result['entries'], 'file' ) );
	}

	public function test_per_page_is_bounded(): void {
		$this->assertSame( LogQuery::MAX_PER_PAGE, LogQuery::run( [], 1, 5000, '', '' )['per_page'] );
		$this->assertSame( LogQuery::DEFAULT_PER_PAGE, LogQuery::run( [], 1, 0, '', '' )['per_page'] );
	}

	public function test_an_unknown_type_means_all(): void {
		$this->assertSame( 5, LogQuery::run( self::entries(), 1, 25, 'bogus', '' )['total'] );
	}

	public function test_entry_view_types_every_field(): void {
		$entry = LogEntryView::shape(
			[
				'id'            => 3,
				'ts'            => '100',
				'status'        => 'converted',
				'file'          => 'a.jpg',
				'size_in'       => '2000',
				'size_out'      => 500,
				'percent'       => 75.04,
				'attachment_id' => 12,
			],
			'https://example.com/wp-admin/post.php?post=12&action=edit'
		);

		$this->assertSame( 100, $entry['ts'] );
		$this->assertSame( 2000, $entry['size_in'] );
		$this->assertSame( 75.0, $entry['percent'] );
		$this->assertNull( $entry['reason'] );
		$this->assertSame( 12, $entry['attachment_id'] );
		$this->assertStringContainsString( 'post=12', (string) $entry['edit_url'] );
	}
}
