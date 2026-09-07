<?php
/**
 * "LW Image" status dropdown on the Media Library list view.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Db\ImageRepository;
use LightweightPlugins\Img\Db\Schema;
use LightweightPlugins\Img\Options;
use WP_Query;

/**
 * Filters upload.php (list mode) by the plugin's own record: optimized,
 * skipped, failed, or not yet processed. Joins the plugin table instead of
 * feeding thousands of IDs through post__in.
 */
final class StatusFilter {

	public const QUERY_VAR = 'lw_img_status';

	public const PENDING = 'pending';

	public const STATUSES = [
		ImageRepository::STATUS_OPTIMIZED,
		ImageRepository::STATUS_SKIPPED,
		ImageRepository::STATUS_FAILED,
		self::PENDING,
	];

	public static function register(): void {
		add_action( 'restrict_manage_posts', [ self::class, 'render_dropdown' ] );
		add_filter( 'posts_join', [ self::class, 'join' ], 10, 2 );
		add_filter( 'posts_where', [ self::class, 'where' ], 10, 2 );
	}

	/**
	 * Media Library list URL pre-filtered to one status.
	 *
	 * @param string $status One of STATUSES.
	 * @return string
	 */
	public static function url( string $status ): string {
		return add_query_arg(
			[
				'mode'          => 'list',
				self::QUERY_VAR => $status,
			],
			admin_url( 'upload.php' )
		);
	}

	/**
	 * The whitelisted status from the request, or null.
	 *
	 * @return string|null
	 */
	public static function requested(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, like WordPress's own Media Library dropdowns.
		$raw = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) ) : '';

		return in_array( $raw, self::STATUSES, true ) ? $raw : null;
	}

	/**
	 * @param string $post_type Screen post type.
	 * @return void
	 */
	public static function render_dropdown( string $post_type ): void {
		if ( 'attachment' !== $post_type ) {
			return;
		}

		$current = self::requested();
		$labels  = [
			''                                => __( 'LW Image: all', 'lw-img' ),
			ImageRepository::STATUS_OPTIMIZED => __( 'LW Image: optimized', 'lw-img' ),
			ImageRepository::STATUS_SKIPPED   => __( 'LW Image: skipped', 'lw-img' ),
			ImageRepository::STATUS_FAILED    => __( 'LW Image: failed', 'lw-img' ),
			self::PENDING                     => __( 'LW Image: not yet processed', 'lw-img' ),
		];

		echo '<label class="screen-reader-text" for="lw-img-status-filter">' . esc_html__( 'Filter by LW Image status', 'lw-img' ) . '</label>';
		echo '<select name="' . esc_attr( self::QUERY_VAR ) . '" id="lw-img-status-filter">';
		foreach ( $labels as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * @param string        $join  Current JOIN SQL.
	 * @param WP_Query|null $query The query, or null when a caller invokes the
	 *                             filter with only the first argument.
	 * @return string
	 */
	public static function join( string $join, ?WP_Query $query = null ): string {
		if ( ! self::applies( $query ) ) {
			return $join;
		}

		global $wpdb;

		return $join . self::join_clause( Schema::table(), $wpdb->posts );
	}

	/**
	 * @param string        $where Current WHERE SQL.
	 * @param WP_Query|null $query The query, or null when a caller invokes the
	 *                             filter with only the first argument.
	 * @return string
	 */
	public static function where( string $where, ?WP_Query $query = null ): string {
		if ( ! self::applies( $query ) ) {
			return $where;
		}

		$status = self::requested();
		if ( null === $status ) {
			return $where;
		}

		$where .= self::where_clause( $status );

		if ( self::PENDING === $status ) {
			global $wpdb;

			$where .= self::mime_clause( array_map( 'strval', (array) Options::get( 'mime_types' ) ), $wpdb->posts );
		}

		return $where;
	}

	public static function join_clause( string $table, string $posts_table ): string {
		return " LEFT JOIN {$table} AS lw_img ON lw_img.attachment_id = {$posts_table}.ID";
	}

	public static function where_clause( string $status ): string {
		if ( self::PENDING === $status ) {
			return ' AND (lw_img.attachment_id IS NULL OR lw_img.status = \'' . esc_sql( ImageRepository::STATUS_PENDING ) . '\')';
		}

		return " AND lw_img.status = '" . esc_sql( $status ) . "'";
	}

	/**
	 * Scopes the "pending" status to the mime types the plugin actually
	 * processes, so the count matches Bulk tab's Pending tile.
	 *
	 * @param array<int, string> $mime_types  Allowed mime types.
	 * @param string             $posts_table The posts table name.
	 * @return string
	 */
	public static function mime_clause( array $mime_types, string $posts_table ): string {
		if ( [] === $mime_types ) {
			return '';
		}

		$escaped = array_map(
			static fn ( string $mime ): string => "'" . esc_sql( $mime ) . "'",
			$mime_types
		);

		return " AND {$posts_table}.post_mime_type IN (" . implode( ', ', $escaped ) . ')';
	}

	private static function applies( ?WP_Query $query ): bool {
		return null !== $query
			&& is_admin()
			&& $query->is_main_query()
			&& 'attachment' === $query->get( 'post_type' )
			&& null !== self::requested();
	}
}
