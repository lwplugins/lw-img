<?php
/**
 * Bulk tab — optimize the existing Media Library in the background.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Bulk\BulkJob;
use LightweightPlugins\Img\Bulk\JobHandlers;
use LightweightPlugins\Img\Bulk\StatusEndpoint;
use LightweightPlugins\Img\Bulk\StatusMeta;
use LightweightPlugins\Img\Bulk\Throttle;
use LightweightPlugins\Img\Bulk\UnoptimizedQuery;
use LightweightPlugins\Img\Options;

/**
 * Bulk tab, three states: a progress dashboard while running (segmented
 * bar, stat tiles, elapsed/speed/ETA, live activity feed — all updated by
 * admin.js from the status endpoint), a summary banner when finished, and
 * a start screen otherwise. The run continues with the tab closed.
 */
final class TabBulk implements TabInterface {

	public function get_slug(): string {
		return 'bulk';
	}

	public function get_label(): string {
		return __( 'Bulk', 'lw-img' );
	}

	public function get_icon(): string {
		return 'dashicons-images-alt2';
	}

	public function render(): void {
		$query     = new UnoptimizedQuery();
		$pending   = $query->count();
		$optimized = $query->optimized_count();
		$counts    = StatusMeta::counts();
		$job       = BulkJob::get();
		$running   = BulkJob::is_running();

		?>
		<h2><?php esc_html_e( 'Bulk optimize', 'lw-img' ); ?></h2>
		<p class="description" style="max-width:70ch;"><?php esc_html_e( 'Runs in the background — you can close this tab. References to converted files in post content and page-builder data are rewritten automatically.', 'lw-img' ); ?></p>

		<div
			id="lw-img-bulk"
			data-nonce="<?php echo esc_attr( wp_create_nonce( StatusEndpoint::NONCE_ACTION ) ); ?>"
			data-running="<?php echo esc_attr( $running ? '1' : '0' ); ?>"
		>
			<?php if ( $running ) : ?>
				<?php $this->render_running( $job ); ?>
			<?php elseif ( BulkJob::STATE_DONE === ( $job['state'] ?? '' ) ) : ?>
				<?php $this->render_done_banner( $job ); ?>
			<?php endif; ?>

			<?php $this->render_tiles( $pending, $optimized, $counts ); ?>
			<?php $this->render_skip_reasons(); ?>
			<?php $this->render_controls( $running, $pending, $counts ); ?>
		</div>

		<p class="description" style="margin-top:18px;">
			<?php esc_html_e( 'WP-Cron only runs while the site receives traffic. For very large libraries the command line is the guaranteed path:', 'lw-img' ); ?>
			<code>wp lw-img optimize --all</code>
		</p>
		<?php
	}

	/**
	 * Progress dashboard for an active run.
	 *
	 * @param array<string, mixed> $job Job record.
	 * @return void
	 */
	private function render_running( array $job ): void {
		$total     = max( 1, (int) ( $job['total'] ?? 0 ) );
		$processed = BulkJob::processed();
		$ok_pct    = 100 * (int) ( $job['optimized'] ?? 0 ) / $total;
		$skip_pct  = 100 * (int) ( $job['skipped'] ?? 0 ) / $total;
		$fail_pct  = 100 * (int) ( $job['failed'] ?? 0 ) / $total;
		?>
		<div class="lw-img-hero">
			<div class="lw-img-hero-top">
				<div class="lw-img-pct"><span id="lw-img-hero-pct"><?php echo esc_html( number_format_i18n( 100 * $processed / $total, 1 ) ); ?></span><small> %</small></div>
				<div class="lw-img-hero-counts">
					<strong id="lw-img-hero-processed"><?php echo esc_html( number_format_i18n( $processed ) ); ?></strong> /
					<span id="lw-img-hero-total"><?php echo esc_html( number_format_i18n( (int) $job['total'] ) ); ?></span> <?php esc_html_e( 'processed', 'lw-img' ); ?>
				</div>
			</div>
			<div class="lw-img-stack">
				<span id="lw-img-seg-ok" class="lw-img-seg-ok" style="width:<?php echo esc_attr( (string) $ok_pct ); ?>%"></span><span id="lw-img-seg-skip" class="lw-img-seg-skip" style="width:<?php echo esc_attr( (string) $skip_pct ); ?>%"></span><span id="lw-img-seg-fail" class="lw-img-seg-fail" style="width:<?php echo esc_attr( (string) $fail_pct ); ?>%"></span>
			</div>
			<div class="lw-img-legend">
				<span><i class="lw-img-dot-ok"></i><?php esc_html_e( 'Optimized', 'lw-img' ); ?></span>
				<span><i class="lw-img-dot-skip"></i><?php esc_html_e( 'Skipped', 'lw-img' ); ?></span>
				<span><i class="lw-img-dot-fail"></i><?php esc_html_e( 'Failed', 'lw-img' ); ?></span>
				<span><i class="lw-img-dot-pending"></i><?php esc_html_e( 'Pending', 'lw-img' ); ?></span>
			</div>
		</div>

		<div class="lw-img-meta">
			<div><span class="lw-img-k"><?php esc_html_e( 'Elapsed', 'lw-img' ); ?></span><span class="lw-img-v" id="lw-img-meta-elapsed">—</span></div>
			<div><span class="lw-img-k"><?php esc_html_e( 'Speed', 'lw-img' ); ?></span><span class="lw-img-v" id="lw-img-meta-speed">—</span></div>
			<div><span class="lw-img-k"><?php esc_html_e( 'Est. remaining', 'lw-img' ); ?></span><span class="lw-img-v" id="lw-img-meta-eta">—</span></div>
			<div><span class="lw-img-k"><?php esc_html_e( 'Saved so far', 'lw-img' ); ?></span><span class="lw-img-v lw-img-v-good" id="lw-img-meta-saved">—</span></div>
		</div>

		<div class="lw-img-now">
			<span class="lw-img-pulse" aria-hidden="true"></span>
			<span class="description"><?php esc_html_e( 'Now processing', 'lw-img' ); ?></span>
			<code id="lw-img-now-item"><?php echo esc_html( (string) ( $job['current'] ?? '…' ) ); ?></code>
		</div>

		<ul class="lw-img-feed" id="lw-img-feed" aria-label="<?php esc_attr_e( 'Recent activity', 'lw-img' ); ?>"></ul>
		<?php
	}

	/**
	 * Summary banner for a finished run.
	 *
	 * @param array<string, mixed> $job Job record.
	 * @return void
	 */
	private function render_done_banner( array $job ): void {
		$duration = max( 0, (int) ( $job['finished_at'] ?? 0 ) - (int) ( $job['started_at'] ?? 0 ) );
		$saved    = (int) ( $job['bytes_saved'] ?? 0 );
		$in       = (int) ( $job['bytes_in'] ?? 0 );

		printf(
			'<div class="lw-img-done"><span class="dashicons dashicons-yes" aria-hidden="true"></span> %s</div>',
			esc_html(
				sprintf(
					/* translators: 1: image count, 2: duration, 3: saved size, 4: percentage. */
					__( 'Run finished — %1$s images optimized in %2$s, %3$s saved (−%4$s%%).', 'lw-img' ),
					number_format_i18n( (int) ( $job['optimized'] ?? 0 ) ),
					human_time_diff( 0, $duration ),
					(string) size_format( $saved, 1 ),
					number_format_i18n( $in > 0 ? 100 * $saved / $in : 0, 1 )
				)
			)
		);
	}

	/**
	 * Library-state tiles (updated live by admin.js during a run).
	 *
	 * @param int                $pending   Pending count.
	 * @param int                $optimized All-time optimized count.
	 * @param array<string, int> $counts Status-meta counts.
	 * @return void
	 */
	private function render_tiles( int $pending, int $optimized, array $counts ): void {
		$tiles = [
			[ 'lw-img-count-pending', __( 'Pending', 'lw-img' ), $pending, '' ],
			[ 'lw-img-count-optimized', __( 'Optimized', 'lw-img' ), $optimized, 'lw-img-tile-ok' ],
			[ 'lw-img-count-skipped', __( 'Skipped', 'lw-img' ), $counts[ StatusMeta::SKIPPED ], 'lw-img-tile-skip' ],
			[ 'lw-img-count-failed', __( 'Failed', 'lw-img' ), $counts[ StatusMeta::FAILED ], 'lw-img-tile-fail' ],
		];

		echo '<div class="lw-img-tiles">';
		foreach ( $tiles as [ $id, $label, $value, $class ] ) {
			printf(
				'<div class="lw-img-tile %1$s"><span class="lw-img-k">%2$s</span><span class="lw-img-tile-v" id="%3$s">%4$s</span></div>',
				esc_attr( $class ),
				esc_html( $label ),
				esc_attr( $id ),
				esc_html( number_format_i18n( $value ) )
			);
		}
		echo '</div>';
	}

	/**
	 * Skip-reason chips.
	 *
	 * @return void
	 */
	private function render_skip_reasons(): void {
		$reasons = StatusMeta::skip_reasons();
		if ( [] === $reasons ) {
			return;
		}

		echo '<div class="lw-img-chips"><span class="lw-img-k">' . esc_html__( 'Skip reasons', 'lw-img' ) . '</span>';
		foreach ( $reasons as $reason => $total ) {
			printf(
				'<span class="lw-img-chip lw-img-chip-skip">%s · %s</span>',
				esc_html( (string) $reason ),
				esc_html( number_format_i18n( $total ) )
			);
		}
		echo '</div>';
	}

	/**
	 * Action buttons.
	 *
	 * @param bool               $running Whether a run is active.
	 * @param int                $pending Pending count.
	 * @param array<string, int> $counts  Status-meta counts.
	 * @return void
	 */
	private function render_controls( bool $running, int $pending, array $counts ): void {
		echo '<div class="lw-img-controls">';

		if ( $running ) {
			echo '<a href="' . esc_url( JobHandlers::url( JobHandlers::ACTION_CANCEL ) ) . '" class="button">' . esc_html__( 'Cancel run', 'lw-img' ) . '</a>';
		} else {
			printf(
				'<a href="%s" class="button button-primary%s">%s</a>',
				esc_url( JobHandlers::url( JobHandlers::ACTION_START ) ),
				0 === $pending ? ' disabled' : '',
				esc_html__( 'Optimize all in background', 'lw-img' )
			);
		}

		if ( $counts[ StatusMeta::FAILED ] > 0 ) {
			printf(
				'<a href="%s" class="button">%s</a>',
				esc_url( JobHandlers::url( JobHandlers::ACTION_RETRY ) ),
				esc_html(
					/* translators: %s: number of failed images. */
					sprintf( __( 'Retry %s failed', 'lw-img' ), number_format_i18n( $counts[ StatusMeta::FAILED ] ) )
				)
			);
		}

		if ( $counts[ StatusMeta::SKIPPED ] > 0 ) {
			printf(
				'<a href="%s" class="button">%s</a>',
				esc_url( JobHandlers::url( JobHandlers::ACTION_RESCAN ) ),
				esc_html(
					/* translators: %s: number of skipped images. */
					sprintf( __( 'Re-scan %s skipped', 'lw-img' ), number_format_i18n( $counts[ StatusMeta::SKIPPED ] ) )
				)
			);
		}

		$this->render_speed_form();

		echo '</div>';
	}

	/**
	 * Processing-speed selector (gentle / normal / fast).
	 *
	 * The controls belong to the hidden auxiliary form (HTML5 form=""
	 * attribute) rendered by JobHandlers outside the settings form — a
	 * nested form would be dropped and the submit would hit options.php.
	 *
	 * @return void
	 */
	private function render_speed_form(): void {
		$current = (string) Options::get( 'bulk_speed', 'normal' );
		$labels  = [
			'gentle' => __( 'Gentle — lightest server load', 'lw-img' ),
			'normal' => __( 'Normal', 'lw-img' ),
			'fast'   => __( 'Fast — highest server load', 'lw-img' ),
		];

		echo '<span class="lw-img-speed">';
		echo '<label for="lw-img-bulk-speed">' . esc_html__( 'Processing speed:', 'lw-img' ) . '</label> ';
		echo '<select name="bulk_speed" id="lw-img-bulk-speed" form="' . esc_attr( JobHandlers::SPEED_FORM_ID ) . '">';
		foreach ( Throttle::SPEEDS as $speed ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $speed ),
				selected( $current, $speed, false ),
				esc_html( $labels[ $speed ] )
			);
		}
		echo '</select> ';
		echo '<button type="submit" class="button" form="' . esc_attr( JobHandlers::SPEED_FORM_ID ) . '">' . esc_html__( 'Apply', 'lw-img' ) . '</button>';
		echo '</span>';
	}
}
