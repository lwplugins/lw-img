<?php
/**
 * Log tab — toggle + recent events table.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Log\ClearHandler;
use LightweightPlugins\Img\Log\EventLog;

/**
 * Log tab: logging toggle and recent events table.
 */
final class TabLog implements TabInterface {

	use FieldRendererTrait;

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
		$this->maybe_render_cleared_notice();
		$this->render_toggle();
		$this->render_clear_button();
		$this->render_table( EventLog::all() );
	}

	private function maybe_render_cleared_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['cleared'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible inline"><p>%s</p></div>',
			esc_html__( 'Log cleared.', 'lw-img' )
		);
	}

	private function render_toggle(): void {
		?>
		<h2><?php esc_html_e( 'Logging', 'lw-img' ); ?></h2>
		<p><?php esc_html_e( 'Records the last 200 upload events: converted, skipped, or failed. Disable to stop recording (existing entries remain until cleared).', 'lw-img' ); ?></p>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Status', 'lw-img' ); ?></th>
				<td>
					<?php
					$this->render_checkbox_field(
						[
							'name'  => 'enable_log',
							'label' => __( 'Enable upload event logging', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_clear_button(): void {
		?>
		<p style="margin: 12px 0 24px;">
			<button type="submit" form="<?php echo esc_attr( ClearHandler::FORM_ID ); ?>" class="button" onclick="return confirm('<?php echo esc_js( __( 'Clear all log entries?', 'lw-img' ) ); ?>');">
				<span class="dashicons dashicons-trash" style="vertical-align: text-bottom;"></span>
				<?php esc_html_e( 'Clear log', 'lw-img' ); ?>
			</button>
		</p>
		<?php
	}

	private const PER_PAGE = 25;

	private function render_table( array $entries ): void {
		?>
		<h3><?php esc_html_e( 'Recent events', 'lw-img' ); ?></h3>

		<?php if ( empty( $entries ) ) : ?>
			<p class="description"><?php esc_html_e( 'No events logged yet. Upload an image to populate this list.', 'lw-img' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<?php
		$total       = count( $entries );
		$per_page    = self::PER_PAGE;
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['lwlog_page'] ) ? (int) $_GET['lwlog_page'] : 1;
		$current = max( 1, min( $total_pages, $current ) );

		$offset       = ( $current - 1 ) * $per_page;
		$page_entries = array_slice( $entries, $offset, $per_page );
		?>

		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width: 150px;"><?php esc_html_e( 'When', 'lw-img' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Status', 'lw-img' ); ?></th>
					<th><?php esc_html_e( 'File', 'lw-img' ); ?></th>
					<th><?php esc_html_e( 'Details', 'lw-img' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $page_entries as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $this->format_time( (int) ( $entry['ts'] ?? 0 ) ) ); ?></td>
						<td><?php $this->render_status_badge( (string) ( $entry['status'] ?? '' ) ); ?></td>
						<td><code><?php echo esc_html( (string) ( $entry['file'] ?? '' ) ); ?></code></td>
						<td><?php echo wp_kses_post( $this->format_details( $entry ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php $this->render_pagination( $total, $current, $total_pages ); ?>
		<?php
	}

	private function render_pagination( int $total, int $current, int $total_pages ): void {
		$range_from = ( ( $current - 1 ) * self::PER_PAGE ) + 1;
		$range_to   = min( $current * self::PER_PAGE, $total );

		$summary = sprintf(
			/* translators: 1: range start, 2: range end, 3: total count */
			__( 'Showing %1$s–%2$s of %3$s', 'lw-img' ),
			number_format_i18n( $range_from ),
			number_format_i18n( $range_to ),
			number_format_i18n( $total )
		);
		?>
		<div class="lw-img-tablenav">
			<span><?php echo esc_html( $summary ); ?></span>
			<?php if ( $total_pages > 1 ) : ?>
				<span class="pagination-links">
					<?php echo wp_kses_post( $this->build_pagination_links( $current, $total_pages ) ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php
	}

	private function build_pagination_links( int $current, int $total_pages ): string {
		$base = remove_query_arg( 'lwlog_page' );
		$base = add_query_arg( 'lwlog_page', '%#%', $base );

		$links = paginate_links(
			[
				'base'         => $base,
				'format'       => '',
				'total'        => $total_pages,
				'current'      => $current,
				'prev_text'    => '&laquo;',
				'next_text'    => '&raquo;',
				'add_fragment' => '#tab-log',
				'type'         => 'plain',
			]
		);

		return is_string( $links ) ? $links : '';
	}

	private function format_time( int $ts ): string {
		if ( 0 === $ts ) {
			return '—';
		}
		return wp_date( 'Y-m-d H:i:s', $ts );
	}

	private function render_status_badge( string $status ): void {
		printf(
			'<span class="lw-img-badge lw-img-badge--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $status )
		);
	}

	private function format_details( array $entry ): string {
		$status = (string) ( $entry['status'] ?? '' );

		if ( EventLog::STATUS_CONVERTED === $status ) {
			$in    = (int) ( $entry['size_in'] ?? 0 );
			$out   = (int) ( $entry['size_out'] ?? 0 );
			$pct   = (float) ( $entry['percent'] ?? 0 );
			$mime  = (string) ( $entry['mime'] ?? '' );
			$mt    = (string) ( $entry['mime_to'] ?? '' );
			$jobid = (string) ( $entry['job_id'] ?? '' );

			$in_label  = size_format( $in );
			$out_label = size_format( $out );

			$details = sprintf(
				'<code>%s</code> &rarr; <code>%s</code> &nbsp; %s &rarr; %s <strong>(%s%%)</strong>',
				esc_html( $mime ),
				esc_html( $mt ),
				esc_html( is_string( $in_label ) ? $in_label : $in . ' B' ),
				esc_html( is_string( $out_label ) ? $out_label : $out . ' B' ),
				esc_html( number_format_i18n( $pct, 1 ) )
			);

			if ( '' !== $jobid ) {
				$details .= sprintf( '<br><span class="description"><code>%s</code></span>', esc_html( $jobid ) );
			}
			return $details;
		}

		if ( EventLog::STATUS_SKIPPED === $status ) {
			return sprintf(
				'<span class="description">%s</span>',
				esc_html( (string) ( $entry['reason'] ?? '' ) )
			);
		}

		if ( EventLog::STATUS_FAILED === $status ) {
			return sprintf(
				'<span style="color:#d63638;">%s</span>',
				esc_html( (string) ( $entry['error'] ?? '' ) )
			);
		}

		return '';
	}
}
