<?php
/**
 * Log tab — event feed with client-side filtering.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Log\ClearHandler;
use LightweightPlugins\Img\Log\EventLog;

/**
 * A control bar (logging toggle + clear), count-badged filter chips with
 * a filename search, and feed-style rows. All entries (max 200) render
 * once; admin.js filters and paginates them without reloads.
 */
final class TabLog implements TabInterface {

	use ControlRendererTrait;

	public function get_slug(): string {
		return 'log';
	}

	public function get_label(): string {
		return __( 'Log', 'lw-img' );
	}

	public function get_icon(): string {
		return 'dashicons-list-view';
	}

	public function render(): void {
		$entries = EventLog::all();

		echo '<h2>' . esc_html__( 'Log', 'lw-img' ) . '</h2>';
		echo '<p class="lw-img-sub">' . esc_html__( 'The last 200 upload events — what converted, what was skipped, and why.', 'lw-img' ) . '</p>';

		$this->maybe_render_cleared_notice();
		$this->render_bar( [] !== $entries );

		if ( [] === $entries ) {
			$this->render_empty();
			return;
		}

		$this->render_filters( $entries );
		$this->render_feed( $entries );

		echo '<div class="lw-img-log-nav"><span id="lw-img-log-summary"></span><span class="lw-img-log-pages" id="lw-img-log-pages"></span></div>';
	}

	/**
	 * Success notice after a clear.
	 *
	 * @return void
	 */
	private function maybe_render_cleared_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag set by the nonce-checked clear handler.
		if ( ! isset( $_GET['cleared'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible inline"><p>%s</p></div>',
			esc_html__( 'Log cleared.', 'lw-img' )
		);
	}

	/**
	 * Control bar: logging toggle and the clear button.
	 *
	 * @param bool $has_entries Whether the clear button makes sense.
	 * @return void
	 */
	private function render_bar( bool $has_entries ): void {
		echo '<div class="lw-img-log-bar">';
		$this->render_switch(
			[
				'name'  => 'enable_log',
				'label' => __( 'Logging enabled', 'lw-img' ),
			]
		);
		echo '<span class="description">' . esc_html__( 'existing entries remain until cleared', 'lw-img' ) . '</span>';

		if ( $has_entries ) {
			printf(
				'<button type="submit" form="%s" class="button lw-img-log-clear" onclick="return confirm(\'%s\');"><span class="dashicons dashicons-trash" aria-hidden="true"></span> %s</button>',
				esc_attr( ClearHandler::FORM_ID ),
				esc_js( __( 'Clear all log entries?', 'lw-img' ) ),
				esc_html__( 'Clear log', 'lw-img' )
			);
		}
		echo '</div>';
	}

	/**
	 * Count-badged filter chips and the filename search.
	 *
	 * @param array<int, array<string, mixed>> $entries Log entries.
	 * @return void
	 */
	private function render_filters( array $entries ): void {
		$counts = [
			EventLog::STATUS_CONVERTED => 0,
			EventLog::STATUS_SKIPPED   => 0,
			EventLog::STATUS_FAILED    => 0,
			EventLog::STATUS_RESTORED  => 0,
		];
		foreach ( $entries as $entry ) {
			$status = (string) ( $entry['status'] ?? '' );
			if ( isset( $counts[ $status ] ) ) {
				++$counts[ $status ];
			}
		}

		$chips = array_merge(
			[ [ 'all', __( 'All', 'lw-img' ), count( $entries ) ] ],
			array_map(
				fn ( string $status ): array => [ $status, $this->status_label( $status ), $counts[ $status ] ],
				array_keys( $counts )
			)
		);

		echo '<div class="lw-img-log-filters">';
		foreach ( $chips as $chip ) {
			if ( 'all' !== $chip[0] && 0 === $chip[2] ) {
				continue;
			}
			printf(
				'<button type="button" class="lw-img-log-fchip%s" data-status="%s">%s <i>%s</i></button>',
				'all' === $chip[0] ? ' lw-img-log-fchip-on' : '',
				esc_attr( $chip[0] ),
				esc_html( $chip[1] ),
				esc_html( number_format_i18n( $chip[2] ) )
			);
		}
		printf(
			'<input type="search" class="lw-img-log-search" id="lw-img-log-search" placeholder="%1$s" aria-label="%1$s" />',
			esc_attr__( 'Filter by filename…', 'lw-img' )
		);
		echo '</div>';
	}

	/**
	 * The full event feed (admin.js shows 25 rows at a time).
	 *
	 * @param array<int, array<string, mixed>> $entries Log entries.
	 * @return void
	 */
	private function render_feed( array $entries ): void {
		echo '<ul class="lw-img-log-feed" id="lw-img-log-feed">';
		foreach ( $entries as $entry ) {
			$status = (string) ( $entry['status'] ?? '' );
			$file   = basename( (string) ( $entry['file'] ?? '' ) );
			$ts     = (int) ( $entry['ts'] ?? 0 );

			printf(
				'<li data-status="%s" data-file="%s"><span class="lw-img-log-t">%s</span><span class="lw-img-chip lw-img-log-chip--%s">%s</span><span class="lw-img-log-f">%s</span>%s</li>',
				esc_attr( $status ),
				esc_attr( strtolower( $file ) ),
				esc_html( $ts > 0 ? (string) wp_date( 'Y-m-d H:i', $ts ) : '—' ),
				esc_attr( $status ),
				esc_html( $this->status_label( $status ) ),
				esc_html( $file ),
				wp_kses_post( $this->details_html( $entry, $status ) )
			);
		}
		echo '</ul>';
	}

	/**
	 * Compact details span for one entry.
	 *
	 * @param array<string, mixed> $entry  Log entry.
	 * @param string               $status Entry status.
	 * @return string
	 */
	private function details_html( array $entry, string $status ): string {
		if ( EventLog::STATUS_CONVERTED === $status ) {
			$size_in  = (int) ( $entry['size_in'] ?? 0 );
			$size_out = (int) ( $entry['size_out'] ?? 0 );
			$title    = trim( (string) ( $entry['mime'] ?? '' ) . ' → ' . (string) ( $entry['mime_to'] ?? '' ) . ' ' . (string) ( $entry['job_id'] ?? '' ) );

			return sprintf(
				'<span class="lw-img-log-d" title="%s">%s → %s <strong>&minus;%s%%</strong></span>',
				esc_attr( $title ),
				esc_html( (string) size_format( max( 0, $size_in ) ) ),
				esc_html( (string) size_format( max( 0, $size_out ) ) ),
				esc_html( number_format_i18n( (float) ( $entry['percent'] ?? 0 ), 1 ) )
			);
		}

		if ( EventLog::STATUS_FAILED === $status ) {
			return '<span class="lw-img-log-d lw-img-log-d-err">' . esc_html( (string) ( $entry['error'] ?? '' ) ) . '</span>';
		}

		if ( EventLog::STATUS_RESTORED === $status ) {
			return '<span class="lw-img-log-d">' . esc_html__( 'original restored from backup', 'lw-img' ) . '</span>';
		}

		return '<span class="lw-img-log-d">' . esc_html( (string) ( $entry['reason'] ?? '' ) ) . '</span>';
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	private function render_empty(): void {
		echo '<div class="lw-img-stats-empty">';
		echo '<span class="dashicons dashicons-media-text" aria-hidden="true"></span>';
		echo '<h4>' . esc_html__( 'No events yet', 'lw-img' ) . '</h4>';
		echo '<p>' . esc_html__( 'Upload an image or run a bulk optimization — every conversion, skip, and failure lands here with its reason.', 'lw-img' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Human label for a status.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	private function status_label( string $status ): string {
		$labels = [
			EventLog::STATUS_CONVERTED => __( 'Converted', 'lw-img' ),
			EventLog::STATUS_SKIPPED   => __( 'Skipped', 'lw-img' ),
			EventLog::STATUS_FAILED    => __( 'Failed', 'lw-img' ),
			EventLog::STATUS_RESTORED  => __( 'Restored', 'lw-img' ),
		];

		return $labels[ $status ] ?? $status;
	}
}
