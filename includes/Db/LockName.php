<?php
/**
 * Per-site names for MySQL advisory locks.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Db;

defined( 'ABSPATH' ) || exit;

/**
 * GET_LOCK names are server-wide: every site and multisite subsite on the
 * same database server shares one namespace. A bare "lw_img_claim" would
 * make unrelated sites wait for each other, so every lock name carries a
 * short hash of the database name and table prefix.
 */
final class LockName {

	/**
	 * MySQL's limit on advisory lock names.
	 */
	private const MAX_LENGTH = 64;

	/**
	 * The scoped lock name for this site.
	 *
	 * @param string $base Lock purpose, e.g. "lw_img_claim".
	 * @return string
	 */
	public static function for_site( string $base ): string {
		global $wpdb;

		$prefix = is_object( $wpdb ) && isset( $wpdb->prefix ) ? (string) $wpdb->prefix : '';

		return self::build( $base, defined( 'DB_NAME' ) ? (string) DB_NAME : '', $prefix );
	}

	/**
	 * Build a scoped name: "{base}_{12 hex}", at most 64 characters.
	 *
	 * @param string $base    Lock purpose.
	 * @param string $db_name Database name.
	 * @param string $prefix  Table prefix.
	 * @return string
	 */
	public static function build( string $base, string $db_name, string $prefix ): string {
		$suffix = '_' . substr( md5( $db_name . '|' . $prefix ), 0, 12 );

		return substr( $base, 0, self::MAX_LENGTH - strlen( $suffix ) ) . $suffix;
	}
}
