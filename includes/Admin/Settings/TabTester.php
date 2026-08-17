<?php
/**
 * Tester tab — environment and configuration checks.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Health\HealthReport;

/**
 * Renders the health report as status tables so hosting problems (MyISAM
 * tables, missing WebP support, broken cron loopback) surface before a
 * bulk run trips over them.
 */
final class TabTester implements TabInterface {

	public function get_slug(): string {
		return 'tester';
	}

	public function get_label(): string {
		return __( 'Tester', 'lw-img' );
	}

	public function get_icon(): string {
		return 'dashicons-yes-alt';
	}

	public function render(): void {
		$report   = HealthReport::get();
		$sections = $report['sections'];

		$labels = [
			'database'    => __( 'Database', 'lw-img' ),
			'environment' => __( 'PHP & image processing', 'lw-img' ),
			'filesystem'  => __( 'Filesystem', 'lw-img' ),
			'cron'        => __( 'Background processing', 'lw-img' ),
			'api'         => __( 'API & plugins', 'lw-img' ),
		];

		$this->render_summary( $sections, (int) $report['generated_at'] );

		foreach ( $sections as $slug => $rows ) {
			echo '<h3>' . esc_html( $labels[ $slug ] ?? $slug ) . '</h3>';
			echo '<table class="widefat striped lw-img-health"><tbody>';
			foreach ( $rows as $row ) {
				printf(
					'<tr><td class="lw-img-health-cell"><span class="lw-img-health-pill lw-img-health-pill--%1$s">%2$s</span></td><th scope="row">%3$s</th><td>%4$s</td></tr>',
					esc_attr( $row['status'] ),
					esc_html( $this->status_label( $row['status'] ) ),
					esc_html( $row['label'] ),
					esc_html( $row['message'] )
				);
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * Summary line and the re-run button.
	 *
	 * @param array<string, array<int, array{label: string, status: string, message: string}>> $sections     Report sections.
	 * @param int                                                                              $generated_at Report timestamp.
	 * @return void
	 */
	private function render_summary( array $sections, int $generated_at ): void {
		$critical = HealthReport::count_status( $sections, 'critical' );
		$warning  = HealthReport::count_status( $sections, 'warning' );

		echo '<div class="lw-img-health-summary">';

		if ( 0 === $critical && 0 === $warning ) {
			echo '<p>' . esc_html__( 'All checks passed — the environment is ready for bulk optimization.', 'lw-img' ) . '</p>';
		} else {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: number of critical issues, 2: number of warnings. */
						__( '%1$d critical issue(s) and %2$d warning(s) found — see the details below.', 'lw-img' ),
						$critical,
						$warning
					)
				)
			);
		}

		printf(
			'<p><a href="%s" class="button">%s</a> <span class="description">%s</span></p>',
			esc_url( wp_nonce_url( add_query_arg( 'action', HealthReport::REFRESH_ACTION, admin_url( 'admin-post.php' ) ), HealthReport::REFRESH_ACTION ) ),
			esc_html__( 'Run tests again', 'lw-img' ),
			esc_html(
				sprintf(
					/* translators: %s: human-readable time difference. */
					__( 'last run %s ago', 'lw-img' ),
					human_time_diff( $generated_at )
				)
			)
		);

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
			'ok'       => __( 'OK', 'lw-img' ),
			'warning'  => __( 'Warning', 'lw-img' ),
			'critical' => __( 'Critical', 'lw-img' ),
			'info'     => __( 'Info', 'lw-img' ),
		];

		return $labels[ $status ] ?? $status;
	}
}
