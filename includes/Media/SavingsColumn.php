<?php
/**
 * "LW Image" column in the Media Library list view.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Compat\CompetitorRegistry;
use LightweightPlugins\Img\Db\ImageRepository;

/**
 * Shows what happened to each image: the saving when we optimized it, the
 * outcome when we deliberately left it alone, and a dash only for images we
 * have not looked at yet.
 *
 * An image we skipped is finished, not pending — leaving its cell blank read
 * as "nothing happened here" and invited people to re-run it forever.
 */
final class SavingsColumn {

	/**
	 * Green that carries a positive result and still clears 4.5:1 on the
	 * admin's white background (the WordPress success green does not).
	 * Shared with the attachment box so both screens agree.
	 */
	public const GREEN = '#008a20';

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

		if ( null !== $record && ImageRepository::STATUS_OPTIMIZED === $record['status'] ) {
			self::render_savings( $record );
			return;
		}

		$owner = CompetitorRegistry::managed_by( $post_id );
		if ( null !== $owner ) {
			echo '<span class="description">' . esc_html( $owner ) . '</span>';
			return;
		}

		if ( null !== $record && ImageRepository::STATUS_PENDING !== $record['status'] ) {
			self::render_outcome( $record );
			return;
		}

		echo '<span aria-hidden="true">—</span>';
	}

	/**
	 * Render the saving of an optimized image.
	 *
	 * @param array<string, string> $record Stored record.
	 * @return void
	 */
	private static function render_savings( array $record ): void {
		$original = (int) $record['orig_size'];
		$new_size = (int) $record['new_size'];
		$percent  = $original > 0 ? ( 1 - $new_size / $original ) * 100 : 0.0;

		$original_label = size_format( $original );
		$new_label      = size_format( $new_size );

		printf(
			'<strong style="color:%1$s;">&minus;%2$s%%</strong><br><span class="description">%3$s &rarr; %4$s</span>',
			esc_attr( self::GREEN ),
			esc_html( number_format_i18n( $percent, 1 ) ),
			esc_html( is_string( $original_label ) ? $original_label : $original . ' B' ),
			esc_html( is_string( $new_label ) ? $new_label : $new_size . ' B' )
		);
	}

	/**
	 * Render the outcome of an image we did not replace.
	 *
	 * @param array<string, string> $record Stored record.
	 * @return void
	 */
	private static function render_outcome( array $record ): void {
		$detail = (string) $record['detail'];

		if ( ImageRepository::STATUS_FAILED === $record['status'] ) {
			printf(
				'<strong style="color:#b32d2e;">%1$s</strong><br><span class="description">%2$s</span>',
				esc_html__( 'Failed', 'lw-img' ),
				esc_html( $detail )
			);
			return;
		}

		// By far the most common skip: the optimized file came back no
		// smaller than the original. That is a finished, successful result —
		// the image is already as small as we can make it — so it gets the
		// same green figure a real saving gets, at zero percent.
		if ( 'result not smaller' === $detail ) {
			printf(
				'<strong style="color:%1$s;">0%%</strong><br><span class="description">%2$s</span>',
				esc_attr( self::GREEN ),
				esc_html__( 'Skipped — already as small as it gets', 'lw-img' )
			);
			return;
		}

		echo '<span class="description">';
		if ( '' !== $detail ) {
			/* translators: %s: reason the image was skipped, e.g. "file missing". */
			printf( esc_html__( 'Skipped — %s', 'lw-img' ), esc_html( $detail ) );
		} else {
			esc_html_e( 'Skipped', 'lw-img' );
		}
		echo '</span>';
	}
}
