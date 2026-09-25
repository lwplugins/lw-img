<?php
/**
 * Tests for per-site advisory lock names.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Db;

use LightweightPlugins\Img\Db\LockName;
use PHPUnit\Framework\TestCase;

/**
 * MySQL advisory locks are server-wide: every site and subsite on the same
 * database server shares one namespace, so the names must carry the site.
 *
 * @covers \LightweightPlugins\Img\Db\LockName
 */
final class LockNameTest extends TestCase {

	public function test_name_is_the_base_plus_a_site_suffix(): void {
		$this->assertMatchesRegularExpression( '/^lw_img_claim_[0-9a-f]{12}$/', LockName::build( 'lw_img_claim', 'wordpress', 'wp_' ) );
	}

	public function test_two_prefixes_on_one_database_get_different_names(): void {
		$this->assertNotSame(
			LockName::build( 'lw_img_job', 'wordpress', 'wp_' ),
			LockName::build( 'lw_img_job', 'wordpress', 'wp_2_' )
		);
	}

	public function test_two_databases_with_one_prefix_get_different_names(): void {
		$this->assertNotSame(
			LockName::build( 'lw_img_job', 'site_a', 'wp_' ),
			LockName::build( 'lw_img_job', 'site_b', 'wp_' )
		);
	}

	public function test_name_stays_within_the_mysql_limit(): void {
		$this->assertLessThanOrEqual( 64, strlen( LockName::build( str_repeat( 'x', 80 ), 'db', 'wp_' ) ) );
	}

	public function test_name_is_stable_for_a_site(): void {
		$this->assertSame( LockName::build( 'lw_img_job', 'db', 'wp_' ), LockName::build( 'lw_img_job', 'db', 'wp_' ) );
	}
}
