<?php
/**
 * "LW Image" box on the attachment edit screen.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

use LightweightPlugins\Img\Db\ImageRepository;

use LightweightPlugins\Img\Backup\BackupStore;
use LightweightPlugins\Img\Backup\RestoreHandler;
use LightweightPlugins\Img\Bulk\OptimizeHandler;
use LightweightPlugins\Img\Bulk\ReoptimizeHandler;
use LightweightPlugins\Img\Compat\CompetitorRegistry;
use LightweightPlugins\Img\Options;
use WP_Post;

/**
 * Shows the optimization stats and offers per-image actions:
 * Compare, Re-optimize at another level, Restore backup.
 */
final class InfoMetabox {

	/**
	 * Hook the meta box.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'add_meta_boxes_attachment',
			static function (): void {
				add_meta_box( 'lw-img-info', __( 'LW Image', 'lw-img' ), [ self::class, 'render' ], 'attachment', 'side' );
			}
		);
	}

	/**
	 * Render the box.
	 *
	 * @param WP_Post $post Attachment post.
	 * @return void
	 */
	public static function render( WP_Post $post ): void {
		$record = ImageRepository::find( $post->ID );

		if ( null === $record || ImageRepository::STATUS_OPTIMIZED !== $record['status'] ) {
			self::render_unoptimized( $post, $record );
			return;
		}

		$original  = (int) $record['orig_size'];
		$new_size  = (int) $record['new_size'];
		$percent   = $original > 0 ? ( 1 - $new_size / $original ) * 100 : 0.0;
		$level     = (string) $record['level'];
		$keep_exif = (bool) $record['keep_exif'];
		$done_at   = (int) $record['optimized_at'];
		$metadata  = wp_get_attachment_metadata( $post->ID );
		$sizes     = is_array( $metadata ) && ! empty( $metadata['sizes'] ) ? count( (array) $metadata['sizes'] ) : 0;

		$original_label = size_format( $original );
		$new_label      = size_format( $new_size );

		echo '<p><strong style="font-size:1.3em;color:' . esc_attr( SavingsColumn::GREEN ) . ';">&minus;' . esc_html( number_format_i18n( $percent, 1 ) ) . '%</strong>';
		if ( '' !== $level ) {
			echo ' <span class="description">(' . esc_html( $level ) . ')</span>';
		}
		echo '</p>';

		echo '<p class="description">';
		echo esc_html( ( is_string( $original_label ) ? $original_label : $original . ' B' ) . ' → ' . ( is_string( $new_label ) ? $new_label : $new_size . ' B' ) ) . '<br>';
		/* translators: %s: number of thumbnails. */
		echo esc_html( sprintf( __( '%s thumbnails generated from the optimized image', 'lw-img' ), number_format_i18n( $sizes ) ) ) . '<br>';
		echo esc_html( $keep_exif ? __( 'EXIF: kept', 'lw-img' ) : __( 'EXIF: removed', 'lw-img' ) ) . '<br>';
		if ( $done_at > 0 ) {
			/* translators: %s: date and time. */
			echo esc_html( sprintf( __( 'Optimized on %s', 'lw-img' ), wp_date( 'Y-m-d H:i', $done_at ) ) );
		}
		echo '</p>';

		$backup_rel = (string) get_post_meta( $post->ID, BackupStore::META_KEY, true );
		$has_backup = '' !== $backup_rel && ( new BackupStore() )->exists( $backup_rel );

		if ( ! $has_backup ) {
			echo '<p class="description">' . esc_html__( 'No backup available — Compare, Re-optimize, and Restore need one.', 'lw-img' ) . '</p>';
			return;
		}

		echo '<p><a class="button" href="' . esc_url( ComparePage::url( $post->ID ) ) . '">' . esc_html__( 'Compare', 'lw-img' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( RestoreHandler::url( $post->ID ) ) . '">' . esc_html__( 'Restore backup', 'lw-img' ) . '</a></p>';

		echo '<p class="description">' . esc_html__( 'Re-optimize from the backup at another level:', 'lw-img' ) . '</p><p>';
		foreach ( [ 'lossless', 'normal', 'aggressive', 'ultra' ] as $target_level ) {
			if ( $target_level === $level ) {
				continue;
			}
			echo '<a class="button button-small" href="' . esc_url( ReoptimizeHandler::url( $post->ID, $target_level ) ) . '">' . esc_html( ucfirst( $target_level ) ) . '</a> ';
		}
		echo '</p>';
	}

	/**
	 * Render the box for an attachment we did not replace.
	 *
	 * @param WP_Post                    $post   Attachment post.
	 * @param array<string, string>|null $record Stored record, if we have looked at this image.
	 * @return void
	 */
	private static function render_unoptimized( WP_Post $post, ?array $record = null ): void {
		$owner = CompetitorRegistry::managed_by( $post->ID );
		if ( null !== $owner ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					/* translators: %s: competitor plugin name. */
					sprintf( __( 'Already optimized by %s — LW Image leaves this image untouched.', 'lw-img' ), $owner )
				)
			);
			return;
		}

		if ( ! in_array( (string) $post->post_mime_type, (array) Options::get( 'mime_types' ), true ) ) {
			echo '<p class="description">' . esc_html__( 'This file type is not converted by LW Img.', 'lw-img' ) . '</p>';
			return;
		}

		$decided = null !== $record && ImageRepository::STATUS_PENDING !== $record['status'];

		if ( $decided ) {
			self::render_outcome( $record );
		} else {
			echo '<p class="description">' . esc_html__( 'Not optimized yet.', 'lw-img' ) . '</p>';
		}

		printf(
			'<p><a class="button %1$s" href="%2$s">%3$s</a></p>',
			$decided ? 'button-secondary' : 'button-primary',
			esc_url( OptimizeHandler::url( $post->ID ) ),
			$decided ? esc_html__( 'Try again', 'lw-img' ) : esc_html__( 'Optimize now', 'lw-img' )
		);
	}

	/**
	 * Say what happened, so a finished image does not read as an untouched one.
	 *
	 * @param array<string, string> $record Stored record.
	 * @return void
	 */
	private static function render_outcome( array $record ): void {
		$detail = (string) $record['detail'];

		if ( ImageRepository::STATUS_FAILED === $record['status'] ) {
			echo '<p><strong style="color:#b32d2e;">' . esc_html__( 'Optimization failed', 'lw-img' ) . '</strong></p>';
			if ( '' !== $detail ) {
				echo '<p class="description">' . esc_html( $detail ) . '</p>';
			}
			return;
		}

		// "No smaller" is a finished result, not a gap: the image is already
		// as small as the API can make it. Saying so — with the same green a
		// real saving gets — stops people re-running it over and over.
		if ( 'result not smaller' === $detail ) {
			echo '<p><strong style="font-size:1.3em;color:' . esc_attr( SavingsColumn::GREEN ) . ';">0%</strong></p>';
			echo '<p class="description">' . esc_html__( 'Skipped — the optimized version came back no smaller, so the original was kept. This image is already as small as it gets.', 'lw-img' ) . '</p>';
			return;
		}

		echo '<p class="description">';
		if ( '' !== $detail ) {
			/* translators: %s: reason the image was skipped, e.g. "file missing". */
			printf( esc_html__( 'Skipped — %s', 'lw-img' ), esc_html( $detail ) );
		} else {
			esc_html_e( 'Skipped', 'lw-img' );
		}
		echo '</p>';
	}
}
