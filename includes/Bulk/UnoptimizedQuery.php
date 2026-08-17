<?php
/**
 * Finds Media Library images that have not been optimized yet.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

use LightweightPlugins\Img\Options;

/**
 * Queries attachments in the allowed mime list without the optimized meta.
 */
final class UnoptimizedQuery {

	private const CLAIM_TTL = 600;

	private const CLAIM_LOCK = 'lw_img_claim';

	public const CURSOR_OPTION = 'lw_img_bulk_cursor';

	public const COUNT_TRANSIENT = 'lw_img_pending_count';

	/**
	 * IDs of unoptimized image attachments.
	 *
	 * @param int $limit Maximum number of IDs to return.
	 * @return array<int, int>
	 */
	public function ids( int $limit ): array {
		return $this->pending_after( 0, $limit );
	}

	/**
	 * Atomically claim a batch of pending IDs for this process.
	 *
	 * Claimed rows leave the pending queue until their outcome stamp (or the
	 * claim's expiry, should the process die), so several workers — cron
	 * ticks, poll assists, parallel WP-CLI processes — can drain the same
	 * queue without double-processing. A MySQL advisory lock makes the
	 * pick-and-stamp atomic; without lock support this degrades to the
	 * single-process behavior.
	 *
	 * @param int $limit Maximum number of IDs to claim.
	 * @return array<int, int>
	 */
	public function claim( int $limit ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock; no data is read.
		$locked = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', self::CLAIM_LOCK ) );

		// The shared cursor keeps every pick to the unprocessed tail of the
		// ID range; without it each pick re-probes the whole processed
		// prefix, which grows to minutes on ~100k-image libraries.
		$cursor = (int) get_option( self::CURSOR_OPTION, 0 );

		$ids = $this->pending_after( $cursor, $limit );

		if ( [] === $ids && $cursor > 0 ) {
			// End of the range: wrap around once so re-queued items
			// (requeues, expired claims) behind the cursor are found.
			$ids = $this->pending_after( 0, $limit );
		}

		foreach ( $ids as $attachment_id ) {
			update_post_meta( $attachment_id, StatusMeta::META_CLAIM, time() );
		}

		update_option( self::CURSOR_OPTION, [] === $ids ? 0 : max( $ids ), false );

		if ( $locked ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock release; no data is read.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::CLAIM_LOCK ) );
		}

		return $ids;
	}

	/**
	 * Pending IDs above a cursor, straight from SQL.
	 *
	 * The picker deliberately bypasses WP_Query: it runs constantly during
	 * bulk runs and must only probe rows above the cursor.
	 *
	 * @param int $after_id Only consider IDs greater than this.
	 * @param int $limit    Maximum number of IDs.
	 * @return array<int, int>
	 */
	private function pending_after( int $after_id, int $limit ): array {
		global $wpdb;

		[ $from_where, $params ] = $this->pending_from_where( $after_id );
		$params[]                = $limit;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- hot-path queue pick; the fragment consists of %s/%d placeholders only and the whole query is prepared here with a matching parameter list.
		$ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT p.ID {$from_where} ORDER BY p.ID ASC LIMIT %d", $params )
		);
		// phpcs:enable

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Number of unoptimized image attachments.
	 *
	 * The full count probes every attachment row, which is expensive on
	 * huge libraries, so it is cached for a minute unless $fresh is set.
	 * (The old WP_Query version additionally paid SQL_CALC_FOUND_ROWS and
	 * an unconstrained self-join for the claim clause — 9s per call on a
	 * 771k-row postmeta table.)
	 *
	 * @param bool $fresh Bypass and refresh the cached value.
	 * @return int
	 */
	public function count( bool $fresh = false ): int {
		if ( ! $fresh ) {
			$cached = get_transient( self::COUNT_TRANSIENT );
			if ( false !== $cached ) {
				return (int) $cached;
			}
		}

		global $wpdb;

		[ $from_where, $params ] = $this->pending_from_where( 0 );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the fragment consists of %s/%d placeholders only and the whole query is prepared here; cached via transient above.
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from_where}", $params ) );
		// phpcs:enable

		set_transient( self::COUNT_TRANSIENT, $count, MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Number of optimized attachments.
	 *
	 * @return int
	 */
	public function optimized_count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed single-key count; shown on demand only.
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", '_lw_img_optimized' )
		);
	}

	/**
	 * Shared FROM/WHERE fragment of the pending-queue queries.
	 *
	 * @param int $after_id Only consider IDs greater than this.
	 * @return array{0: string, 1: array<int, int|string>} SQL fragment (placeholders only) and its parameters.
	 */
	private function pending_from_where( int $after_id ): array {
		global $wpdb;

		$mimes = array_values( array_filter( (array) Options::get( 'mime_types' ) ) );

		// An empty mime list would render "IN ()" — a SQL syntax error. Match
		// nothing instead (there is nothing to optimize without allowed types).
		if ( [] === $mimes ) {
			return [ 'FROM ' . $wpdb->posts . ' p WHERE 1 = 0', [] ];
		}

		$placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );

		$sql = "FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} s ON p.ID = s.post_id AND s.meta_key = %s
			 LEFT JOIN {$wpdb->postmeta} o ON p.ID = o.post_id AND o.meta_key = %s
			 LEFT JOIN {$wpdb->postmeta} c ON p.ID = c.post_id AND c.meta_key = %s
			 WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
			   AND p.post_mime_type IN ($placeholders)
			   AND p.ID > %d
			   AND s.post_id IS NULL AND o.post_id IS NULL
			   AND ( c.post_id IS NULL OR c.meta_value + 0 < %d )";

		$params = array_merge(
			[ StatusMeta::META_STATUS, '_lw_img_optimized', StatusMeta::META_CLAIM ],
			array_map( 'strval', $mimes ),
			[ $after_id, time() - self::CLAIM_TTL ]
		);

		return [ $sql, $params ];
	}
}
