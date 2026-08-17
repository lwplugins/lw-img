<?php
/**
 * Tester tab — environment checks with a verdict-first layout.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Health\HealthReport;

/**
 * A verdict hero with status counts, warnings and criticals lifted into
 * a needs-attention block (with copyable fix commands), and section
 * cards whose headers show each group's worst status.
 */
final class TabTester implements TabInterface {

	private const SECTION_ICONS = [
		'database'    => 'dashicons-database',
		'environment' => 'dashicons-performance',
		'filesystem'  => 'dashicons-open-folder',
		'cron'        => 'dashicons-clock',
		'api'         => 'dashicons-cloud',
	];

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

		echo '<h2>' . esc_html__( 'Tester', 'lw-img' ) . '</h2>';
		echo '<p class="lw-img-sub">' . esc_html__( 'Environment checks — hosting problems surface here before a bulk run trips over them.', 'lw-img' ) . '</p>';

		$this->render_hero( $sections, (int) $report['generated_at'] );
		$this->render_attention( HealthReport::attention_rows( $sections ) );
		$this->render_sections( $sections );
	}

	/**
	 * Verdict hero with counts and the re-run action.
	 *
	 * @param array<string, array<int, array<string, string>>> $sections     Report sections.
	 * @param int                                              $generated_at Report timestamp.
	 * @return void
	 */
	private function render_hero( array $sections, int $generated_at ): void {
		$critical = HealthReport::count_status( $sections, 'critical' );
		$warning  = HealthReport::count_status( $sections, 'warning' );
		$ok       = HealthReport::count_status( $sections, 'ok' );
		$info     = HealthReport::count_status( $sections, 'info' );

		if ( $critical > 0 ) {
			$class = 'critical';
			$icon  = 'dashicons-warning';
			/* translators: 1: critical count, 2: warning count. */
			$text = sprintf( __( '%1$d critical issue(s), %2$d warning(s)', 'lw-img' ), $critical, $warning );
		} elseif ( $warning > 0 ) {
			$class = 'warning';
			$icon  = 'dashicons-info';
			/* translators: %d: warning count. */
			$text = sprintf( __( '%d warning(s) found', 'lw-img' ), $warning );
		} else {
			$class = 'ok';
			$icon  = 'dashicons-yes-alt';
			$text  = __( 'All checks passed — ready for bulk optimization', 'lw-img' );
		}

		echo '<div class="lw-img-ts-hero">';
		printf(
			'<span class="lw-img-ts-verdict lw-img-ts-verdict--%s"><span class="dashicons %s" aria-hidden="true"></span>%s</span>',
			esc_attr( $class ),
			esc_attr( $icon ),
			esc_html( $text )
		);

		echo '<span class="lw-img-ts-counts">';
		$chips = [
			/* translators: %d: count. */
			[ 'critical', $critical, __( '%d critical', 'lw-img' ) ],
			/* translators: %d: count. */
			[ 'warning', $warning, __( '%d warning', 'lw-img' ) ],
			/* translators: %d: count. */
			[ 'ok', $ok, __( '%d OK', 'lw-img' ) ],
			/* translators: %d: count. */
			[ 'info', $info, __( '%d info', 'lw-img' ) ],
		];
		foreach ( $chips as $chip ) {
			if ( 0 === $chip[1] ) {
				continue;
			}
			printf(
				'<span class="lw-img-health-pill lw-img-health-pill--%s">%s</span>',
				esc_attr( $chip[0] ),
				esc_html( sprintf( $chip[2], $chip[1] ) ) // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- templates are literal __() calls above.
			);
		}
		echo '</span>';

		printf(
			'<span class="lw-img-ts-actions"><a href="%s" class="button">%s</a> <span class="description">%s</span></span>',
			esc_url( wp_nonce_url( add_query_arg( 'action', HealthReport::REFRESH_ACTION, admin_url( 'admin-post.php' ) ), HealthReport::REFRESH_ACTION ) ),
			esc_html__( 'Run tests again', 'lw-img' ),
			esc_html(
				sprintf(
					/* translators: %s: human-readable time difference. */
					__( 'last run %s ago · cached for 10 minutes', 'lw-img' ),
					human_time_diff( $generated_at )
				)
			)
		);
		echo '</div>';
	}

	/**
	 * Warnings and criticals lifted above the sections.
	 *
	 * @param array<int, array<string, string>> $rows Attention rows, criticals first.
	 * @return void
	 */
	private function render_attention( array $rows ): void {
		if ( [] === $rows ) {
			return;
		}

		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Needs attention', 'lw-img' ) . '</h3>';
		echo '<ul class="lw-img-ts-attn">';
		foreach ( $rows as $row ) {
			printf(
				'<li class="lw-img-ts-attn--%s"><span class="dashicons %s" aria-hidden="true"></span><div><b>%s</b><p>%s</p>',
				esc_attr( $row['status'] ),
				'critical' === $row['status'] ? 'dashicons-warning' : 'dashicons-info',
				esc_html( $row['label'] ),
				esc_html( $row['message'] )
			);
			if ( '' !== (string) ( $row['fix'] ?? '' ) ) {
				printf(
					'<span class="lw-img-ts-fix"><code>%s</code><button type="button" class="lw-img-ts-copy" data-copied="%s">%s</button></span>',
					esc_html( $row['fix'] ),
					esc_attr__( 'Copied', 'lw-img' ),
					esc_html__( 'Copy', 'lw-img' )
				);
			}
			echo '</div></li>';
		}
		echo '</ul>';
	}

	/**
	 * Section cards with worst-status headers.
	 *
	 * @param array<string, array<int, array<string, string>>> $sections Report sections.
	 * @return void
	 */
	private function render_sections( array $sections ): void {
		$labels = [
			'database'    => __( 'Database', 'lw-img' ),
			'environment' => __( 'PHP & image processing', 'lw-img' ),
			'filesystem'  => __( 'Filesystem', 'lw-img' ),
			'cron'        => __( 'Background processing', 'lw-img' ),
			'api'         => __( 'API & plugins', 'lw-img' ),
		];

		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'All checks', 'lw-img' ) . '</h3>';

		foreach ( $sections as $slug => $rows ) {
			$worst = HealthReport::worst_status( $rows );

			echo '<div class="lw-img-ts-sect">';
			printf(
				'<div class="lw-img-ts-sect-head"><span class="dashicons %s" aria-hidden="true"></span>%s<span class="lw-img-health-pill lw-img-health-pill--%s">%s</span></div>',
				esc_attr( self::SECTION_ICONS[ $slug ] ?? 'dashicons-yes-alt' ),
				esc_html( $labels[ $slug ] ?? (string) $slug ),
				esc_attr( 'ok' === $worst ? 'ok' : $worst ),
				esc_html( $this->worst_label( $worst, $rows ) )
			);
			echo '<ul>';
			foreach ( $rows as $row ) {
				printf(
					'<li><span class="lw-img-health-pill lw-img-health-pill--%s">%s</span><span class="lw-img-ts-lbl">%s</span><span class="lw-img-ts-msg">%s</span></li>',
					esc_attr( $row['status'] ),
					esc_html( $this->status_label( $row['status'] ) ),
					esc_html( $row['label'] ),
					esc_html( $row['message'] )
				);
			}
			echo '</ul></div>';
		}
	}

	/**
	 * Header label for a section's worst status.
	 *
	 * @param string                            $worst Worst status.
	 * @param array<int, array<string, string>> $rows  Section rows.
	 * @return string
	 */
	private function worst_label( string $worst, array $rows ): string {
		if ( 'ok' === $worst ) {
			return __( 'OK', 'lw-img' );
		}

		$count = 0;
		foreach ( $rows as $row ) {
			if ( $row['status'] === $worst ) {
				++$count;
			}
		}

		return 'critical' === $worst
			/* translators: %d: number of issues. */
			? sprintf( __( '%d issue(s)', 'lw-img' ), $count )
			/* translators: %d: number of warnings. */
			: sprintf( __( '%d warning(s)', 'lw-img' ), $count );
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
