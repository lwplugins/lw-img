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

	/**
	 * IDs of unoptimized image attachments.
	 *
	 * @param int $limit Maximum number of IDs to return.
	 * @return array<int, int>
	 */
	public function ids( int $limit ): array {
		$query = new WP_Query( $this->args( $limit ) );

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

		$ids = $this->ids( $limit );

		foreach ( $ids as $attachment_id ) {
			update_post_meta( $attachment_id, StatusMeta::META_CLAIM, time() );
		}

		if ( $locked ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock release; no data is read.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::CLAIM_LOCK ) );
		}

		return $ids;
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
