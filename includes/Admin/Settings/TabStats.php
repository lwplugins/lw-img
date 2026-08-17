<?php
/**
 * Stats tab — savings dashboard.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Stats\SiteStats;

/**
 * Savings hero with a before/after bar, stat tiles, the biggest wins,
 * a leftover-backup warning, and an empty state pointing to Bulk.
 */
final class TabStats implements TabInterface {

	public function get_slug(): string {
		return 'stats';
	}

	public function get_label(): string {
		return __( 'Stats', 'lw-img' );
	}

	public function get_icon(): string {
		return 'dashicons-chart-bar';
	}

	public function render(): void {
		$stats = SiteStats::get();

		echo '<h2>' . esc_html__( 'Stats', 'lw-img' ) . '</h2>';
		echo '<p class="lw-img-sub">' . esc_html__( 'What optimization has saved on this site so far.', 'lw-img' ) . '</p>';

		if ( 0 === (int) $stats['count'] ) {
			$this->render_empty();
		} else {
			$this->render_hero( $stats );
			$this->render_tiles( $stats );
			$this->render_wins( (array) $stats['wins'] );
			$this->render_leftovers( (array) $stats['leftovers'] );
		}

		$this->render_refresh( (int) $stats['generated_at'] );
	}

	/**
	 * Savings hero with the before/after bar.
	 *
	 * @param array<string, mixed> $stats Site stats.
	 * @return void
	 */
	private function render_hero( array $stats ): void {
		$original  = (int) $stats['original'];
		$optimized = (int) $stats['optimized'];
		$after_pct = $original > 0 ? max( 1, min( 100, 100 * $optimized / $original ) ) : 0;

		echo '<div class="lw-img-stats-hero">';
		echo '<div class="lw-img-stats-top">';
		echo '<span class="lw-img-stats-big">' . esc_html( $this->format_bytes( (int) $stats['saved'] ) ) . ' <small>' . esc_html__( 'saved', 'lw-img' ) . '</small></span>';
		echo '<span class="lw-img-stats-pct">&minus;' . esc_html( number_format_i18n( (float) $stats['percent'], 1 ) ) . '%</span>';
		echo '</div>';
		echo '<div class="lw-img-stats-bar"><span style="width:' . esc_attr( number_format( $after_pct, 1, '.', '' ) ) . '%"></span></div>';
		echo '<div class="lw-img-stats-cap">';
		echo '<span><strong>' . esc_html__( 'After:', 'lw-img' ) . '</strong> ' . esc_html( $this->format_bytes( $optimized ) ) . '</span>';
		echo '<span><strong>' . esc_html__( 'Before:', 'lw-img' ) . '</strong> ' . esc_html( $this->format_bytes( $original ) ) . '</span>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Stat tiles.
	 *
	 * @param array<string, mixed> $stats Site stats.
	 * @return void
	 */
	private function render_tiles( array $stats ): void {
		$count   = (int) $stats['count'];
		$total   = (int) ( $stats['library_total'] ?? 0 );
		$backup  = (array) $stats['backup'];
		$average = $count > 0 ? (int) ( (int) $stats['saved'] / $count ) : 0;

		$retention = (int) Options::get( 'backup_retention_days' );
		$keep      = 0 === $retention
			? __( 'kept forever', 'lw-img' )
			/* translators: %d: number of days. */
			: sprintf( __( '%d-day retention', 'lw-img' ), $retention );

		echo '<div class="lw-img-tiles lw-img-gen-tiles">';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'Optimized images', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-v">' . esc_html( number_format_i18n( $count ) ) . '</span>';
		echo '<span class="lw-img-tile-d">';
		if ( $total > 0 ) {
			/* translators: %s: percentage of the Media Library. */
			echo esc_html( sprintf( __( '%s%% of the Media Library', 'lw-img' ), number_format_i18n( 100 * $count / $total, 1 ) ) ) . ' · ';
		}
		echo '<a href="#bulk" class="lw-img-goto">' . esc_html__( 'optimize the rest', 'lw-img' ) . '</a></span>';
		echo '</div>';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'Average saving', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-v">' . esc_html( $this->format_bytes( $average ) ) . ' <small>/ ' . esc_html__( 'image', 'lw-img' ) . '</small></span>';
		echo '<span class="lw-img-tile-d">' . esc_html__( 'across all optimized images', 'lw-img' ) . '</span>';
		echo '</div>';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'Backup folder', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-v">' . esc_html( $this->format_bytes( (int) $backup['bytes'] ) ) . '</span>';
		echo '<span class="lw-img-tile-d">' . esc_html(
			sprintf(
				/* translators: 1: number of files, 2: retention description. */
				__( '%1$s files · %2$s', 'lw-img' ),
				number_format_i18n( (int) $backup['files'] ),
				$keep
			)
		) . ' · <a href="#backup" class="lw-img-goto">' . esc_html__( 'Backup', 'lw-img' ) . '</a></span>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * The images with the largest savings.
	 *
	 * @param array<int, array{file: string, original: int, new: int}> $wins Win rows.
	 * @return void
	 */
	private function render_wins( array $wins ): void {
		if ( [] === $wins ) {
			return;
		}

		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Biggest wins', 'lw-img' ) . '</h3>';
		echo '<ul class="lw-img-wins">';
		foreach ( $wins as $win ) {
			$saved_pct = $win['original'] > 0 ? 100 * ( $win['original'] - $win['new'] ) / $win['original'] : 0;
			printf(
				'<li><span class="lw-img-wins-f">%1$s</span><span class="lw-img-wins-sizes"><strong>%2$s</strong> &rarr; %3$s</span><span class="lw-img-chip lw-img-chip-ok">&minus;%4$s%%</span></li>',
				esc_html( $win['file'] ),
				esc_html( $this->format_bytes( $win['original'] ) ),
				esc_html( $this->format_bytes( $win['new'] ) ),
				esc_html( number_format_i18n( $saved_pct, 1 ) )
			);
		}
		echo '</ul>';
	}

	/**
	 * Warning card about other optimizers' leftover backup folders.
	 *
	 * @param array<string, array{bytes: int, files: int}> $leftovers Leftover folders by plugin name.
	 * @return void
	 */
	private function render_leftovers( array $leftovers ): void {
		if ( [] === $leftovers ) {
			return;
		}

		$total = 0;
		foreach ( $leftovers as $dir ) {
			$total += (int) $dir['bytes'];
		}

		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Leftovers from other optimizers', 'lw-img' ) . '</h3>';
		echo '<div class="lw-img-leftover">';
		echo '<span class="dashicons dashicons-warning" aria-hidden="true"></span>';
		echo '<div>';
		echo '<strong>' . esc_html(
			sprintf(
				/* translators: %s: human-readable size. */
				__( '%s of old backups found', 'lw-img' ),
				$this->format_bytes( $total )
			)
		) . '</strong>';
		echo '<p>' . esc_html__( 'A previously installed optimizer left its backup folder behind — these files stay on disk even after the plugin is uninstalled. LW Image does not manage or delete them; remove the folder manually if you no longer need those backups.', 'lw-img' ) . '</p>';
		foreach ( $leftovers as $name => $dir ) {
			printf(
				'<span class="lw-img-leftover-row"><code>%1$s</code> %2$s · %3$s</span>',
				esc_html( (string) $name ),
				esc_html( $this->format_bytes( (int) $dir['bytes'] ) ),
				esc_html(
					sprintf(
						/* translators: %s: number of files. */
						__( '%s files', 'lw-img' ),
						number_format_i18n( (int) $dir['files'] )
					)
				)
			);
		}
		echo '</div></div>';
	}

	/**
	 * Empty state pointing to the Bulk tab.
	 *
	 * @return void
	 */
	private function render_empty(): void {
		echo '<div class="lw-img-stats-empty">';
		echo '<span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>';
		echo '<h4>' . esc_html__( 'No optimized images yet', 'lw-img' ) . '</h4>';
		echo '<p>' . esc_html__( 'Stats appear here after the first conversion. New uploads convert automatically — for everything already in the Media Library, run a bulk optimization.', 'lw-img' ) . '</p>';
		echo '<a href="#bulk" class="button button-primary lw-img-goto">' . esc_html__( 'Open the Bulk tab', 'lw-img' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Refresh button and cache age.
	 *
	 * @param int $generated_at Report timestamp.
	 * @return void
	 */
	private function render_refresh( int $generated_at ): void {
		$refresh_url = wp_nonce_url(
			add_query_arg( 'action', SiteStats::REFRESH_ACTION, admin_url( 'admin-post.php' ) ),
			SiteStats::REFRESH_ACTION
		);

		echo '<p class="lw-img-stats-refresh">';
		echo '<a class="button" href="' . esc_url( $refresh_url ) . '">' . esc_html__( 'Refresh stats', 'lw-img' ) . '</a> ';
		echo '<span class="description">' . esc_html(
			sprintf(
				/* translators: %s: human-readable time difference. */
				__( 'Cached — last updated %s ago. Directory sizes are cached for an hour.', 'lw-img' ),
				human_time_diff( $generated_at )
			)
		) . '</span>';
		echo '</p>';
	}

	/**
	 * Human-readable byte count that tolerates zero.
	 *
	 * @param int $bytes Byte count.
	 * @return string
	 */
	private function format_bytes( int $bytes ): string {
		if ( $bytes <= 0 ) {
			return '0 B';
		}

		return (string) size_format( $bytes, 1 );
	}
}
