<?php
/**
 * Stats tab — total savings and backup disk usage.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Stats\SiteStats;

/**
 * Stats tab: optimized totals, savings ratio, backup folder size, and any
 * leftover backup folders from other optimizers (e.g. a previously
 * installed ShortPixel) the user may not know about.
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

		$refresh_url = wp_nonce_url(
			add_query_arg( 'action', SiteStats::REFRESH_ACTION, admin_url( 'admin-post.php' ) ),
			SiteStats::REFRESH_ACTION
		);

		?>
		<h2><?php esc_html_e( 'Optimization statistics', 'lw-img' ); ?></h2>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Optimized images', 'lw-img' ); ?></th>
				<td><strong><?php echo esc_html( number_format_i18n( (int) $stats['count'] ) ); ?></strong></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Storage saved', 'lw-img' ); ?></th>
				<td>
					<strong style="color:#00a32a;"><?php echo esc_html( $this->format_bytes( (int) $stats['saved'] ) ); ?></strong>
					<span class="description">
						(<?php echo esc_html( $this->format_bytes( (int) $stats['original'] ) ); ?> &rarr; <?php echo esc_html( $this->format_bytes( (int) $stats['optimized'] ) ); ?>,
						&minus;<?php echo esc_html( number_format_i18n( (float) $stats['percent'], 1 ) ); ?>%)
					</span>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'LW Img backups', 'lw-img' ); ?></th>
				<td>
					<strong><?php echo esc_html( $this->format_bytes( (int) $stats['backup']['bytes'] ) ); ?></strong>
					<span class="description">
						<?php
						/* translators: %s: number of files. */
						echo esc_html( sprintf( __( '(%s files in uploads/lw-img-backups — cleaned up by the retention setting)', 'lw-img' ), number_format_i18n( (int) $stats['backup']['files'] ) ) );
						?>
					</span>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Other optimizers\' backups', 'lw-img' ); ?></th>
				<td>
					<?php if ( [] === $stats['leftovers'] ) : ?>
						<span class="description"><?php esc_html_e( 'No leftover backup folders from other optimizers were found.', 'lw-img' ); ?></span>
					<?php else : ?>
						<?php foreach ( $stats['leftovers'] as $name => $dir ) : ?>
							<p style="margin-top:0;">
								<strong><?php echo esc_html( (string) $name ); ?>:</strong>
								<strong><?php echo esc_html( $this->format_bytes( (int) $dir['bytes'] ) ); ?></strong>
								<span class="description">
									<?php
									/* translators: %s: number of files. */
									echo esc_html( sprintf( __( '(%s files)', 'lw-img' ), number_format_i18n( (int) $dir['files'] ) ) );
									?>
								</span>
							</p>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'These folders were most likely left behind by a previously installed optimizer. LW Img does not manage or delete them — if you no longer need those backups, remove the folder via that plugin or manually to free up disk space.', 'lw-img' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p>
			<a class="button" href="<?php echo esc_url( $refresh_url ); ?>"><?php esc_html_e( 'Refresh stats', 'lw-img' ); ?></a>
			<span class="description" style="margin-left:8px;">
				<?php
				/* translators: %s: human-readable time difference. */
				echo esc_html( sprintf( __( 'Updated %s ago. Directory sizes are cached for an hour.', 'lw-img' ), human_time_diff( (int) $stats['generated_at'] ) ) );
				?>
			</span>
		</p>
		<?php
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
