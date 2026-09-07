<?php
/**
 * Tests for the Media Library status filter SQL and input whitelist.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Media;

use Brain\Monkey\Functions;
use LightweightPlugins\Img\Media\StatusFilter;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Media\StatusFilter
 */
final class StatusFilterTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_key' )->alias(
			static fn ( $key ): string => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) )
		);
		Functions\when( 'esc_sql' )->returnArg();
	}

	protected function tearDown(): void {
		unset( $_GET[ StatusFilter::QUERY_VAR ] );
		parent::tearDown();
	}

	public function test_join_left_joins_the_plugin_table_on_the_post_id(): void {
		$this->assertSame(
			' LEFT JOIN wp_lw_img_images AS lw_img ON lw_img.attachment_id = wp_posts.ID',
			StatusFilter::join_clause( 'wp_lw_img_images', 'wp_posts' )
		);
	}

	public function test_where_filters_by_status_or_by_absence(): void {
		$this->assertSame( " AND lw_img.status = 'skipped'", StatusFilter::where_clause( 'skipped' ) );
		$this->assertSame( ' AND lw_img.attachment_id IS NULL', StatusFilter::where_clause( 'pending' ) );
	}

	public function test_requested_whitelists_the_query_var(): void {
		$_GET[ StatusFilter::QUERY_VAR ] = 'skipped';
		$this->assertSame( 'skipped', StatusFilter::requested() );

		$_GET[ StatusFilter::QUERY_VAR ] = "skipped' OR 1=1";
		$this->assertNull( StatusFilter::requested() );

		unset( $_GET[ StatusFilter::QUERY_VAR ] );
		$this->assertNull( StatusFilter::requested() );
	}
}
