<?php
/**
 * Rewrites references to converted/restored files in site content.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

/**
 * Applies URL pairs to post_content (plain text replace) and to postmeta
 * (serialization-aware: values are unserialized, rewritten recursively —
 * including JSON-escaped variants used by page-builder data — and
 * re-serialized, so PHP-serialized length prefixes stay correct).
 * Widgets/options are not covered.
 */
final class UrlRewriter {

	private const META_BATCH = 100;

	/**
	 * Apply the pairs to posts and postmeta.
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
		$this->rewrite_postmeta( $pairs );
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
	 * Plain text replace in post_content.
	 *
	 * @param array<string, string> $pairs Map of old URL => new URL.
	 * @return void
	 */
	private function rewrite_posts( array $pairs ): void {
		global $wpdb;

		$expression = 'post_content';
		$params     = [];
		$likes      = [];

		foreach ( $pairs as $old_url => $new_url ) {
			$expression = 'REPLACE(' . $expression . ', %s, %s)';
			$params[]   = $old_url;
			$params[]   = $new_url;
			$likes[]    = 'post_content LIKE %s';
		}

		foreach ( array_keys( $pairs ) as $old_url ) {
			$params[] = '%' . $wpdb->esc_like( $old_url ) . '%';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- bulk content rewrite; the interpolated expression and conditions consist of %s placeholders only and the whole query is prepared here.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = {$expression} WHERE " . implode( ' OR ', $likes ), $params ) );

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'posts' );
		}
	}

	/**
	 * Serialization-aware replace in postmeta.
	 *
	 * @param array<string, string> $pairs Map of old URL => new URL.
	 * @return void
	 */
	private function rewrite_postmeta( array $pairs ): void {
		global $wpdb;

		$likes  = [];
		$params = [];

		foreach ( array_keys( $pairs ) as $old_url ) {
			$likes[]  = 'meta_value LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $old_url ) . '%';
			$likes[]  = 'meta_value LIKE %s';
			$params[] = '%' . $wpdb->esc_like( str_replace( '/', '\/', $old_url ) ) . '%';
		}

		$where   = '(' . implode( ' OR ', $likes ) . ") AND meta_key NOT LIKE '\_lw\_img\_%'";
		$last_id = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- candidate scan for the rewrite; the interpolated condition consists of %s placeholders only and the whole query is prepared here.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_id > %d AND {$where} ORDER BY meta_id ASC LIMIT %d", array_merge( [ $last_id ], $params, [ self::META_BATCH ] ) ) );

			$row_count = count( $rows );

			foreach ( $rows as $row ) {
				$last_id = (int) $row->meta_id;
				$decoded = maybe_unserialize( (string) $row->meta_value );
				$updated = self::replace_in_value( $decoded, $pairs );

				if ( $updated !== $decoded ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- targeted update by meta_id; the post's meta cache is flushed below.
					$wpdb->update(
						$wpdb->postmeta,
						[ 'meta_value' => maybe_serialize( $updated ) ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- column update, not a query filter.
						[ 'meta_id' => $row->meta_id ]
					);
					wp_cache_delete( (int) $row->post_id, 'post_meta' );
				}
			}
		} while ( self::META_BATCH === $row_count );
	}
}
