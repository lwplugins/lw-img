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
			],
		];
	}
}
