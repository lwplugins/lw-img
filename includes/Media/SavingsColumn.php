<?php
/**
 * "LW Image" column in the Media Library list view.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

use LightweightPlugins\Img\Db\ImageRepository;

/**
 * Shows the savings for optimized images and a dash for the rest.
 */
final class SavingsColumn {

	/**
	 * Hook the column filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'manage_media_columns', [ self::class, 'add_column' ] );
		add_action( 'manage_media_custom_column', [ self::class, 'render_column' ], 10, 2 );
	}

	/**
	 * Register the column.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function add_column( array $columns ): array {
		$columns['lw_img'] = __( 'LW Image', 'lw-img' );

		return $columns;
	}

	/**
	 * Render the column value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Attachment post ID.
	 * @return void
	 */
	public static function render_column( string $column, int $post_id ): void {
		if ( 'lw_img' !== $column ) {
			return;
		}

		$record = ImageRepository::find( $post_id );

		if ( null === $record || ImageRepository::STATUS_OPTIMIZED !== $record['status'] ) {
			$owner = \LightweightPlugins\Img\Compat\CompetitorRegistry::managed_by( $post_id );
			if ( null !== $owner ) {
				echo '<span class="description">' . esc_html( $owner ) . '</span>';
				return;
			}
			echo '<span aria-hidden="true">—</span>';
			return;
		}

		$original = (int) $record['orig_size'];
		$new_size = (int) $record['new_size'];
		$percent  = $original > 0 ? ( 1 - $new_size / $original ) * 100 : 0.0;

		$original_label = size_format( $original );
		$new_label      = size_format( $new_size );

		printf(
			'<strong style="color:#00a32a;">&minus;%1$s%%</strong><br><span class="description">%2$s &rarr; %3$s</span>',
			esc_html( number_format_i18n( $percent, 1 ) ),
			esc_html( is_string( $original_label ) ? $original_label : $original . ' B' ),
			esc_html( is_string( $new_label ) ? $new_label : $new_size . ' B' )
		);
	}
}
