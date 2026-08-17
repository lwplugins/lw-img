<?php
/**
 * Finds Media Library images that have not been optimized yet.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

use LightweightPlugins\Img\Options;
use WP_Query;

/**
 * Queries attachments in the allowed mime list without the optimized meta.
 */
final class UnoptimizedQuery {

	private const CLAIM_TTL = 600;

	private const CLAIM_LOCK = 'lw_img_claim';

	public const CURSOR_OPTION = 'lw_img_bulk_cursor';

	/**
	 * IDs of unoptimized image attachments.
	 *
	 * @param int $limit Maximum number of IDs to return.
	 * @return array<int, int>
	 */
	public function ids( int $limit ): array {
		$args = $this->args( $limit );

		// Picking a batch must not pay for a full COUNT of the remaining
		// queue — on ~100k-image libraries that count takes minutes.
		$args['no_found_rows'] = true;

		$query = new WP_Query( $args );

		return array_map( 'intval', $query->posts );
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

		$mimes        = (array) Options::get( 'mime_types' );
		$placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );

		$params = array_merge(
			[ StatusMeta::META_STATUS, '_lw_img_optimized', StatusMeta::META_CLAIM ],
			array_map( 'strval', $mimes ),
			[ $after_id, time() - self::CLAIM_TTL, $limit ]
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- hot-path queue pick; the interpolated fragment consists of %s placeholders only and the whole query is prepared here with a matching parameter list.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} s ON p.ID = s.post_id AND s.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} o ON p.ID = o.post_id AND o.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} c ON p.ID = c.post_id AND c.meta_key = %s
				 WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
				   AND p.post_mime_type IN ($placeholders)
				   AND p.ID > %d
				   AND s.post_id IS NULL AND o.post_id IS NULL
				   AND ( c.post_id IS NULL OR c.meta_value + 0 < %d )
				 ORDER BY p.ID ASC LIMIT %d",
				$params
			)
		);
		// phpcs:enable

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Number of unoptimized image attachments.
	 *
	 * @return int
	 */
	public function count(): int {
		$query = new WP_Query( $this->args( 1 ) );

		return (int) $query->found_posts;
	}

	/**
	 * Number of optimized attachments.
	 *
	 * @return int
	 */
	public function optimized_count(): int {
		$args = $this->args( 1 );

		$args['post_mime_type'] = 'image';
		$args['meta_query']     = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin/CLI-only stats query.
			[
				'key'     => '_lw_img_optimized',
				'compare' => 'EXISTS',
			],
		];

		$query = new WP_Query( $args );

		return (int) $query->found_posts;
	}

	/**
	 * Shared WP_Query arguments.
	 *
	 * @param int $limit Posts per page.
	 * @return array<string, mixed>
	 */
	private function args( int $limit ): array {
		return [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => (array) Options::get( 'mime_types' ),
			'posts_per_page' => $limit,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin/CLI-only listing; NOT EXISTS is required to find unprocessed items.
				'relation' => 'AND',
				[
					'key'     => StatusMeta::META_STATUS,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_lw_img_optimized',
					'compare' => 'NOT EXISTS',
				],
				[
					'relation' => 'OR',
					[
						'key'     => StatusMeta::META_CLAIM,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => StatusMeta::META_CLAIM,
						'value'   => time() - self::CLAIM_TTL,
						'compare' => '<',
						'type'    => 'NUMERIC',
					],
				],
			],
		];
	}
}
