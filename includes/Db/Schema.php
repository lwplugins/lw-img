<?php
/**
 * Database schema for the plugin's own image table.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Db;

defined( 'ABSPATH' ) || exit;

/**
 * One row per processed attachment, replacing the eleven postmeta rows the
 * plugin used to write per image.
 *
 * Postmeta is an EAV table shared with the whole site: the queue's
 * "images without a result yet" question becomes an anti-join across it,
 * and the savings totals a self-join. On a 95k-image library that meant
 * ~415,000 of our rows inside a 1.2M-row, 591 MB table. A purpose-indexed
 * table answers the same questions against ~50k rows of our own, and drops
 * cleanly on uninstall.
 */
final class Schema {

	public const VERSION_OPTION = 'lw_img_db_version';

	public const VERSION = 1;

	/**
	 * Fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'lw_img_images';
	}

	/**
	 * Create or upgrade the table when the stored version is behind.
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) === self::VERSION ) {
			return;
		}

		self::install();
	}

	/**
	 * Run dbDelta for the table and record the version.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// dbDelta is whitespace- and format-sensitive: two spaces after the
		// key type, one field per line, no backticks on the table name.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			detail varchar(191) NOT NULL DEFAULT '',
			is_transient tinyint(1) NOT NULL DEFAULT 0,
			orig_size bigint(20) unsigned NOT NULL DEFAULT 0,
			new_size bigint(20) unsigned NOT NULL DEFAULT 0,
			level varchar(20) NOT NULL DEFAULT '',
			job_id varchar(64) NOT NULL DEFAULT '',
			keep_exif tinyint(1) NOT NULL DEFAULT 0,
			claimed_at int(10) unsigned NOT NULL DEFAULT 0,
			optimized_at int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY attachment (attachment_id),
			KEY queue (status,claimed_at)
		) {$collate};";

		dbDelta( $sql );

		// Autoloaded on purpose: maybe_install() reads it on every request, so
		// it must not cost a query of its own.
		update_option( self::VERSION_OPTION, self::VERSION, true );
	}

	/**
	 * Whether the table exists (probed once per request).
	 *
	 * @return bool
	 */
	public static function exists(): bool {
		global $wpdb;

		static $exists = null;

		if ( null === $exists ) {
			$table = self::table();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- existence probe for our own table, resolved once per request.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		}

		return $exists;
	}
}
