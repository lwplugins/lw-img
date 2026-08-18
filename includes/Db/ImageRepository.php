<?php
/**
 * Reads and writes the plugin's per-image records.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Db;

defined( 'ABSPATH' ) || exit;

/**
 * The single place that touches the image table. Every question the plugin
 * used to ask postmeta — is this optimized, what is pending, how much was
 * saved, why was that skipped — is answered here against one indexed table.
 */
final class ImageRepository {

	public const STATUS_PENDING   = 'pending';
	public const STATUS_OPTIMIZED = 'optimized';
	public const STATUS_SKIPPED   = 'skipped';
	public const STATUS_FAILED    = 'failed';

	/**
	 * Every column save() is allowed to write.
	 *
	 * The save() method builds its column list from the caller's array keys and
	 * interpolates it into the statement, so the list has to be a fact rather
	 * than a convention: anything not named here is dropped before it can
	 * reach the SQL.
	 *
	 * @var array<int, string>
	 */
	private const COLUMNS = [
		'attachment_id',
		'status',
		'detail',
		'is_transient',
		'orig_size',
		'new_size',
		'level',
		'job_id',
		'keep_exif',
		'claimed_at',
		'optimized_at',
	];

	/**
	 * Per-request row cache, keyed by attachment ID.
	 *
	 * @var array<int, array<string, mixed>|null>
	 */
	private static array $cache = [];

	/**
	 * The record for one attachment, or null when it has none.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array<string, mixed>|null
	 */
	public static function find( int $attachment_id ): ?array {
		if ( array_key_exists( $attachment_id, self::$cache ) ) {
			return self::$cache[ $attachment_id ];
		}

		global $wpdb;

		$table = Schema::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table name from $wpdb->prefix; cached per request in self::$cache.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE attachment_id = %d", $attachment_id ), ARRAY_A );

		self::$cache[ $attachment_id ] = is_array( $row ) ? $row : null;

		return self::$cache[ $attachment_id ];
	}

	/**
	 * Whether this attachment has been optimized by us.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool
	 */
	public static function is_optimized( int $attachment_id ): bool {
		$row = self::find( $attachment_id );

		return null !== $row && self::STATUS_OPTIMIZED === $row['status'];
	}

	/**
	 * Insert or update the record for an attachment.
	 *
	 * @param int                  $attachment_id Attachment post ID.
	 * @param array<string, mixed> $fields        Column => value.
	 * @return void
	 */
	public static function save( int $attachment_id, array $fields ): void {
		global $wpdb;

		$table = Schema::table();
		$data  = array_intersect_key(
			array_merge( [ 'attachment_id' => $attachment_id ], $fields ),
			array_flip( self::COLUMNS )
		);

		$columns      = array_keys( $data );
		$placeholders = implode( ', ', array_fill( 0, count( $data ), '%s' ) );
		$assignments  = [];

		foreach ( $columns as $column ) {
			$assignments[] = "{$column} = VALUES({$column})";
		}

		$sql = 'INSERT INTO ' . $table . ' (' . implode( ', ', $columns ) . ") VALUES ({$placeholders})"
			. ' ON DUPLICATE KEY UPDATE ' . implode( ', ', $assignments );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- own table and column names built from a fixed set; every value is a %s placeholder prepared here. Upsert keeps the unique attachment key authoritative.
		$wpdb->query( $wpdb->prepare( $sql, array_values( $data ) ) );

		unset( self::$cache[ $attachment_id ] );
	}

	/**
	 * Remove the record for an attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public static function forget( int $attachment_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- targeted delete on our own table by its unique key.
		$wpdb->delete( Schema::table(), [ 'attachment_id' => $attachment_id ], [ '%d' ] );

		unset( self::$cache[ $attachment_id ] );
	}

	/**
	 * Number of records with a status.
	 *
	 * @param string $status Status constant.
	 * @return int
	 */
	public static function count_by_status( string $status ): int {
		global $wpdb;

		$table = Schema::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table name; status is a placeholder. Counts are shown on demand only.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
	}

	/**
	 * Counts for every status in one query.
	 *
	 * @return array<string, int>
	 */
	public static function counts(): array {
		global $wpdb;

		$table = Schema::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- aggregate over our own table, no user input in the statement.
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );

		$counts = [
			self::STATUS_OPTIMIZED => 0,
			self::STATUS_SKIPPED   => 0,
			self::STATUS_FAILED    => 0,
		];

		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Savings totals across optimized images.
	 *
	 * The query that used to be a self-join over the site's postmeta table.
	 *
	 * @return array{count: int, original: int, optimized: int}
	 */
	public static function savings(): array {
		global $wpdb;

		$table = Schema::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- aggregate over our own table; status is a placeholder. Result is transient-cached by SiteStats.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS cnt, SUM(orig_size) AS orig, SUM(new_size) AS new_total FROM {$table} WHERE status = %s", self::STATUS_OPTIMIZED ), ARRAY_A );

		return [
			'count'     => (int) ( $row['cnt'] ?? 0 ),
			'original'  => (int) ( $row['orig'] ?? 0 ),
			'optimized' => (int) ( $row['new_total'] ?? 0 ),
		];
	}

	/**
	 * The images with the largest absolute savings.
	 *
	 * @param int $limit Number of rows.
	 * @return array<int, array{attachment_id: int, orig_size: int, new_size: int}>
	 */
	public static function biggest_wins( int $limit = 5 ): array {
		global $wpdb;

		$table = Schema::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- top-N over our own table; values are placeholders. Transient-cached by SiteStats.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, orig_size, new_size FROM {$table} WHERE status = %s ORDER BY (orig_size - new_size) DESC LIMIT %d", self::STATUS_OPTIMIZED, $limit ), ARRAY_A );

		return array_map(
			static fn ( array $row ): array => [
				'attachment_id' => (int) $row['attachment_id'],
				'orig_size'     => (int) $row['orig_size'],
				'new_size'      => (int) $row['new_size'],
			],
			(array) $rows
		);
	}

	/**
	 * Skip reasons, most frequent first.
	 *
	 * @param int $limit Maximum number of reasons.
	 * @return array<string, int>
	 */
	public static function skip_reasons( int $limit = 5 ): array {
		global $wpdb;

		$table = Schema::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- aggregate over our own table; values are placeholders. Shown on demand only.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT detail, COUNT(*) AS total FROM {$table} WHERE status = %s GROUP BY detail ORDER BY total DESC LIMIT %d", self::STATUS_SKIPPED, $limit ), ARRAY_A );

		$reasons = [];
		foreach ( (array) $rows as $row ) {
			$reasons[ (string) $row['detail'] ] = (int) $row['total'];
		}

		return $reasons;
	}

	/**
	 * Delete every record with a status, putting those images back in the queue.
	 *
	 * @param string $status Status constant.
	 * @return int Rows removed.
	 */
	public static function requeue_by_status( string $status ): int {
		global $wpdb;

		$table = Schema::table();

		self::$cache = [];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- bulk requeue on our own table; status is a placeholder.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status = %s", $status ) );
	}

	/**
	 * Delete every record whose failure looked transient.
	 *
	 * @return int Rows removed.
	 */
	public static function requeue_transient(): int {
		global $wpdb;

		$table = Schema::table();

		self::$cache = [];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- bulk requeue on our own table; status is a placeholder.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status = %s AND is_transient = 1", self::STATUS_FAILED ) );
	}

	/**
	 * Forget the per-request row cache (long CLI runs).
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = [];
	}
}
