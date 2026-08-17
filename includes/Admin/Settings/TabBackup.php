<?php
/**
 * Backup tab — original image backup, retention, and restore guide.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Backup\BackupStore;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Stats\SiteStats;

/**
 * A master toggle with a lifecycle strip, live storage tiles from the
 * cached site stats, retention presets, and a how-to-restore guide.
 * Retention and storage stay active with backups off — the daily cleanup
 * keeps applying to existing backup files either way.
 */
final class TabBackup implements TabInterface {

	use FieldRendererTrait;
	use ControlRendererTrait;

	private const PRESETS = [ 7, 30, 90, 365, 0 ];

	public function get_slug(): string {
		return 'backup';
	}

	public function get_label(): string {
		return __( 'Backup', 'lw-img' );
	}

	public function get_icon(): string {
		return 'dashicons-backup';
	}

	public function render(): void {
		$enabled = (bool) Options::get( 'backup_enabled' );

		echo '<h2>' . esc_html__( 'Backup', 'lw-img' ) . '</h2>';
		echo '<p class="lw-img-sub">' . esc_html__( 'Every conversion keeps the original, so nothing is ever lost.', 'lw-img' ) . '</p>';

		$this->render_master( $enabled );
		$this->render_danger( $enabled );
		$this->render_storage();
		$this->render_retention();
		$this->render_restore();
	}

	/**
	 * Master toggle card with the lifecycle strip.
	 *
	 * @param bool $enabled Whether backups are on.
	 * @return void
	 */
	private function render_master( bool $enabled ): void {
		$retention = (int) Options::get( 'backup_retention_days' );
		$last_step = 0 === $retention
			? __( 'Kept forever', 'lw-img' )
			/* translators: %d: number of days. */
			: sprintf( __( 'Cleaned up after %d days', 'lw-img' ), $retention );

		echo '<div class="lw-img-up-hero">';
		echo '<div class="lw-img-up-master">';
		$this->render_switch(
			[
				'name'  => 'backup_enabled',
				'label' => __( 'Back up originals', 'lw-img' ),
			]
		);
		printf(
			'<span class="lw-img-up-state%s" id="lw-img-bk-state" data-on="%s" data-off="%s">%s</span>',
			$enabled ? '' : ' lw-img-bk-state-off',
			esc_attr__( 'On — every conversion is reversible', 'lw-img' ),
			esc_attr__( 'Off — conversions cannot be undone', 'lw-img' ),
			esc_html( $enabled ? __( 'On — every conversion is reversible', 'lw-img' ) : __( 'Off — conversions cannot be undone', 'lw-img' ) )
		);
		echo '</div>';

		$steps = [
			[ 'M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2zM17 21v-8H7v8M7 3v5h8', __( 'Original saved', 'lw-img' ) ],
			[ 'M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z', 'uploads/' . BackupStore::BACKUP_DIR . '/' ],
			[ 'M1 4v6h6M3.51 15a9 9 0 1 0 2.13-9.36L1 10', __( 'Restorable any time', 'lw-img' ) ],
			[ 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM12 6v6l4 2', $last_step ],
		];

		echo '<div class="lw-img-up-pipeline">';
		foreach ( $steps as $index => $step ) {
			if ( $index > 0 ) {
				echo '<span class="lw-img-up-arrow" aria-hidden="true">&rarr;</span>';
			}
			printf(
				'<span class="lw-img-up-step"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%s"/></svg>%s</span>',
				esc_attr( $step[0] ),
				esc_html( $step[1] )
			);
		}
		echo '</div></div>';
	}

	/**
	 * Warning card shown while backups are off.
	 *
	 * @param bool $enabled Whether backups are on.
	 * @return void
	 */
	private function render_danger( bool $enabled ): void {
		printf( '<div class="lw-img-bk-danger%s" id="lw-img-bk-danger">', esc_attr( $enabled ? ' lw-img-hidden' : '' ) );
		echo '<span class="dashicons dashicons-warning" aria-hidden="true"></span>';
		echo '<div><strong>' . esc_html__( 'Originals are deleted after conversion', 'lw-img' ) . '</strong>';
		echo '<p>' . esc_html__( 'With backups off there is no way to restore an image once it has been converted — Restore original disappears from the Media Library, and re-optimizing at another level becomes impossible. Existing backups are kept and still restorable.', 'lw-img' ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * Live storage tiles from the cached site stats.
	 *
	 * @return void
	 */
	private function render_storage(): void {
		$backup    = (array) SiteStats::get()['backup'];
		$retention = (int) Options::get( 'backup_retention_days' );
		$bytes     = (int) $backup['bytes'];

		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Storage', 'lw-img' ) . '</h3>';
		echo '<div class="lw-img-tiles lw-img-gen-tiles">';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'Backup folder', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-v">' . esc_html( $bytes > 0 ? (string) size_format( $bytes, 1 ) : '0 B' ) . '</span>';
		echo '<span class="lw-img-tile-d"><code>uploads/' . esc_html( BackupStore::BACKUP_DIR ) . '/</code></span>';
		echo '</div>';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'Backed-up originals', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-v">' . esc_html( number_format_i18n( (int) $backup['files'] ) ) . ' <small>' . esc_html__( 'files', 'lw-img' ) . '</small></span>';
		echo '<span class="lw-img-tile-d">' . esc_html__( 'one per optimized image', 'lw-img' ) . '</span>';
		echo '</div>';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'Cleanup', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-v">' . esc_html( 0 === $retention ? __( 'Never', 'lw-img' ) : __( 'Daily', 'lw-img' ) ) . '</span>';
		echo '<span class="lw-img-tile-d">' . esc_html(
			0 === $retention
				? __( 'backups are kept forever', 'lw-img' )
				/* translators: %d: number of days. */
				: sprintf( __( 'removes backups older than %d days', 'lw-img' ), $retention )
		) . '</span>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Retention presets plus the custom day field.
	 *
	 * @return void
	 */
	private function render_retention(): void {
		$current = (int) Options::get( 'backup_retention_days' );
		$labels  = [
			7   => __( '7 days', 'lw-img' ),
			30  => __( '30 days', 'lw-img' ),
			90  => __( '90 days', 'lw-img' ),
			365 => __( '1 year', 'lw-img' ),
			0   => __( 'Forever', 'lw-img' ),
		];

		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Retention', 'lw-img' ) . '</h3>';
		echo '<div class="lw-img-up-row"><span class="lw-img-up-label">' . esc_html__( 'Keep backups for', 'lw-img' ) . '</span><div class="lw-img-up-ctl">';

		echo '<div class="lw-img-seg-ctl lw-img-bk-presets" role="group">';
		foreach ( self::PRESETS as $days ) {
			printf(
				'<button type="button" class="lw-img-bk-preset%s" data-days="%d">%s</button>',
				esc_attr( $current === $days ? ' lw-img-bk-preset-on' : '' ),
				(int) $days,
				esc_html( $labels[ $days ] )
			);
		}
		echo '</div>';

		echo '<span class="lw-img-bk-custom">' . esc_html__( 'or', 'lw-img' ) . ' ';
		$this->render_number_field(
			[
				'name' => 'backup_retention_days',
				'min'  => '0',
				'max'  => '3650',
			]
		);
		echo ' ' . esc_html__( 'days', 'lw-img' ) . '</span>';

		echo '<p class="description">' . esc_html__( 'Backups older than this are deleted by a daily cleanup task. Forever keeps everything — watch the folder size on large sites.', 'lw-img' ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * How-to-restore guide.
	 *
	 * @return void
	 */
	private function render_restore(): void {
		$rows = [
			[
				'M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM10 8.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zM21 15l-5-5L5 21',
				__( 'Media Library', 'lw-img' ),
				__( 'Hover an optimized image and click "Restore original" — the original comes back and thumbnails are regenerated. A side-by-side Compare view is also available.', 'lw-img' ),
			],
			[
				'M4 17l6-6-6-6M12 19h8',
				__( 'WP-CLI', 'lw-img' ),
				__( 'wp lw-img restore 123 456 — restore any number of attachments from the command line.', 'lw-img' ),
			],
			[
				'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
				__( 'Kept on uninstall', 'lw-img' ),
				__( 'Backups are never deleted when the plugin is removed — your originals stay safe in the uploads folder.', 'lw-img' ),
			],
		];

		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'How to restore', 'lw-img' ) . '</h3>';
		echo '<ul class="lw-img-bk-restore">';
		foreach ( $rows as $row ) {
			printf(
				'<li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%s"/></svg><div><b>%s</b><p>%s</p></div></li>',
				esc_attr( $row[0] ),
				esc_html( $row[1] ),
				esc_html( $row[2] )
			);
		}
		echo '</ul>';
	}
}
