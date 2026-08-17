<?php
/**
 * Rewrites references to converted/restored files in site content.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

/**
 * Applies URL pairs to post_content (plain text replace), to every meta
 * table (post/comment/term/user), and to options — all serialization-aware:
 * values are unserialized, rewritten recursively (including JSON-escaped
 * variants used by page-builder data) and re-serialized, so PHP-serialized
 * length prefixes stay correct. Transients are intentionally skipped.
 */
final class UrlRewriter {

	private const META_BATCH = 100;

	private const META_TABLES = [
		'postmeta'    => [
			'id_column'    => 'meta_id',
			'owner_column' => 'post_id',
			'cache_group'  => 'post_meta',
		],
		'commentmeta' => [
			'id_column'    => 'meta_id',
			'owner_column' => 'comment_id',
			'cache_group'  => 'comment_meta',
		],
		'termmeta'    => [
			'id_column'    => 'meta_id',
			'owner_column' => 'term_id',
			'cache_group'  => 'term_meta',
		],
		'usermeta'    => [
			'id_column'    => 'umeta_id',
			'owner_column' => 'user_id',
			'cache_group'  => 'user_meta',
		],
	];

	private const PREFIX_MODE_MIN_PAIRS = 30;

	private const MIN_PREFIX_LENGTH = 20;

	/**
	 * Apply the pairs to posts, all meta tables, and options.
	 *
	 * @param array<string, string> $pairs Map of old URL => new URL.
	 * @return void
	 */
	public function rewrite( array $pairs ): void {
		$pairs = array_filter(
			$pairs,
			static fn ( string $new_url, string $old_url ): bool => '' !== $old_url && $new_url !== $old_url,
			ARRAY_FILTER_USE_BOTH
		);

		if ( [] === $pairs ) {
			return;
		}

		$this->rewrite_posts( $pairs );

		foreach ( array_keys( self::META_TABLES ) as $table ) {
			$this->rewrite_meta_table( $table, $pairs );
		}

		$this->rewrite_options( $pairs );
	}

	/**
	 * Longest common prefix of the given strings.
	 *
	 * @param array<int, string> $strings Non-empty list of strings.
	 * @return string
	 */
	public static function common_prefix( array $strings ): string {
		$prefix = (string) array_shift( $strings );

		foreach ( $strings as $candidate ) {
			while ( '' !== $prefix && ! str_starts_with( $candidate, $prefix ) ) {
				$prefix = substr( $prefix, 0, -1 );
			}
		}

		return $prefix;
	}

	/**
	 * Recursively replace URLs in a decoded value.
	 *
	 * Strings are replaced in both raw and JSON-escaped (`\/`) forms; arrays
	 * and plain objects are walked. Other types pass through untouched.
	 *
	 * @param mixed                 $value Decoded value.
	 * @param array<string, string> $pairs Map of old URL => new URL.
	 * @return mixed
	 */
	public static function replace_in_value( mixed $value, array $pairs ): mixed {
		if ( is_string( $value ) ) {
			foreach ( $pairs as $old_url => $new_url ) {
				$value = str_replace(
					[ $old_url, str_replace( '/', '\/', $old_url ) ],
					[ $new_url, str_replace( '/', '\/', $new_url ) ],
					$value
				);
			}
			return $value;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::replace_in_value( $item, $pairs );
			}
			return $value;
		}

		if ( $value instanceof \stdClass ) {
			foreach ( get_object_vars( $value ) as $key => $item ) {
				$value->{$key} = self::replace_in_value( $item, $pairs );
			}
			return $value;
		}

		return $value;
	}

	/**
	 * Replace in post_content (raw and JSON-escaped forms, e.g. Gutenberg
	 * block attributes) via candidate select + targeted per-row update.
	 *
	 * @param array<string, string> $pairs Map of old URL => new URL.
	 * @return void
	 */
	private function rewrite_posts( array $pairs ): void {
		global $wpdb;

		[ $condition, $params ] = $this->like_condition( 'post_content', $pairs );

		$last_id = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- candidate scan for the rewrite; conditions consist of %s placeholders only and the whole query is prepared here.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM {$wpdb->posts} WHERE ID > %d AND {$condition} ORDER BY ID ASC LIMIT %d", array_merge( [ $last_id ], $params, [ self::META_BATCH ] ) ) );

			$row_count = count( $rows );

			foreach ( $rows as $row ) {
				$last_id = (int) $row->ID;
				$updated = self::replace_in_value( (string) $row->post_content, $pairs );

				if ( $updated !== (string) $row->post_content ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- targeted update by primary key; the post cache is cleaned below.
					$wpdb->update(
						$wpdb->posts,
						[ 'post_content' => $updated ],
						[ 'ID' => $row->ID ]
					);
					clean_post_cache( (int) $row->ID );
				}
			}
		} while ( self::META_BATCH === $row_count );
	}

	/**
	 * Build the "value may contain an old URL" condition (raw + JSON-escaped).
	 *
	 * Large batches match on the pairs' common URL prefix instead of one
	 * LIKE per pair: the scan cost stays flat no matter how many images a
	 * batch carries, and the per-row PHP replace is exact anyway — the SQL
	 * condition only has to be a superset filter.
	 *
	 * @param string                $column Column name to match against.
	 * @param array<string, string> $pairs  Map of old URL => new URL.
	 * @return array{0: string, 1: array<int, string>} Condition SQL (placeholders only) and its parameters.
	 */
	private function like_condition( string $column, array $pairs ): array {
		global $wpdb;

		$targets = array_keys( $pairs );

		if ( count( $pairs ) >= self::PREFIX_MODE_MIN_PAIRS ) {
			$prefix = self::common_prefix( $targets );

			if ( strlen( $prefix ) >= self::MIN_PREFIX_LENGTH ) {
				$targets = [ $prefix ];
			}
		}

		$likes  = [];
		$params = [];

		foreach ( $targets as $old_url ) {
			$likes[]  = $column . ' LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $old_url ) . '%';
			$likes[]  = $column . ' LIKE %s';
			$params[] = '%' . $wpdb->esc_like( str_replace( '/', '\/', $old_url ) ) . '%';
		}

		return [ '(' . implode( ' OR ', $likes ) . ')', $params ];
	}

	/**
	 * Serialization-aware replace in one meta table.
	 *
	 * @param string                $table Key of self::META_TABLES.
	 * @param array<string, string> $pairs Map of old URL => new URL.
	 * @return void
	 */
	private function rewrite_meta_table( string $table, array $pairs ): void {
		global $wpdb;

		$config    = self::META_TABLES[ $table ];
		$id_column = $config['id_column'];
		$owner     = $config['owner_column'];
		$wp_table  = $wpdb->{$table};

		[ $condition, $params ] = $this->like_condition( 'meta_value', $pairs );

		$where   = $condition . " AND meta_key NOT LIKE '\_lw\_img\_%'";
		$last_id = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- candidate scan for the rewrite; table/column names come from a class constant, conditions consist of %s placeholders only, and the whole query is prepared here.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$id_column} AS id, {$owner} AS owner_id, meta_value FROM {$wp_table} WHERE {$id_column} > %d AND {$where} ORDER BY {$id_column} ASC LIMIT %d", array_merge( [ $last_id ], $params, [ self::META_BATCH ] ) ) );

			$row_count = count( $rows );

			foreach ( $rows as $row ) {
				$last_id = (int) $row->id;
				$decoded = maybe_unserialize( (string) $row->meta_value );
				$updated = self::replace_in_value( $decoded, $pairs );

				if ( $updated !== $decoded ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- targeted update by primary key; the owner's meta cache is flushed below.
					$wpdb->update(
						$wp_table,
						[ 'meta_value' => maybe_serialize( $updated ) ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- column update, not a query filter.
						[ $id_column => $row->id ]
					);
					wp_cache_delete( (int) $row->owner_id, $config['cache_group'] );
				}
			}
		} while ( self::META_BATCH === $row_count );
	}

	/**
	 * Serialization-aware replace in options (transients skipped).
	 *
	 * @param array<string, string> $pairs Map of old URL => new URL.
	 * @return void
	 */
	private function rewrite_options( array $pairs ): void {
		global $wpdb;

		[ $condition, $params ] = $this->like_condition( 'option_value', $pairs );

		$where   = $condition . " AND option_name NOT LIKE '\_transient\_%' AND option_name NOT LIKE '\_site\_transient\_%' AND option_name NOT LIKE 'lw\_img\_%'";
		$last_id = 0;
		$touched = false;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- candidate scan for the rewrite; conditions consist of %s placeholders only and the whole query is prepared here.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_id > %d AND {$where} ORDER BY option_id ASC LIMIT %d", array_merge( [ $last_id ], $params, [ self::META_BATCH ] ) ) );

			$row_count = count( $rows );

			foreach ( $rows as $row ) {
				$last_id = (int) $row->option_id;
				$decoded = maybe_unserialize( (string) $row->option_value );
				$updated = self::replace_in_value( $decoded, $pairs );

				if ( $updated !== $decoded ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- targeted update by primary key; option caches are flushed below.
					$wpdb->update(
						$wpdb->options,
						[ 'option_value' => maybe_serialize( $updated ) ],
						[ 'option_id' => $row->option_id ]
					);
					wp_cache_delete( (string) $row->option_name, 'options' );
					$touched = true;
				}
			}
		} while ( self::META_BATCH === $row_count );

		if ( $touched ) {
			wp_cache_delete( 'alloptions', 'options' );
		}
	}
}
