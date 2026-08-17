<?php
/**
 * Collects all environment checks into one cached report.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Health;

/**
 * The report includes live probes (API, cron loopback), so it is cached
 * for ten minutes; the Tester tab's re-run button clears the cache.
 */
final class HealthReport {

	public const CACHE_KEY = 'lw_img_health_report';

	public const REFRESH_ACTION = 'lw_img_health_refresh';

	/**
	 * Hook the re-run action.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::REFRESH_ACTION, [ self::class, 'refresh' ] );
	}

	/**
	 * The full report, section slug => check rows.
	 *
	 * @param bool $fresh Bypass and refresh the cache.
	 * @return array{sections: array<string, array<int, array{label: string, status: string, message: string}>>, generated_at: int}
	 */
	public static function get( bool $fresh = false ): array {
		if ( ! $fresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$report = [
			'sections'     => [
				'database'    => DatabaseChecks::rows(),
				'environment' => EnvironmentChecks::rows(),
				'filesystem'  => FilesystemChecks::rows(),
				'cron'        => CronChecks::rows(),
				'api'         => ApiChecks::rows(),
			],
			'generated_at' => time(),
		];

		set_transient( self::CACHE_KEY, $report, 10 * MINUTE_IN_SECONDS );

		return $report;
	}

	/**
	 * Number of rows with the given status across all sections.
	 *
	 * @param array<string, array<int, array{label: string, status: string, message: string}>> $sections Report sections.
	 * @param string                                                                           $status   Status to count.
	 * @return int
	 */
	public static function count_status( array $sections, string $status ): int {
		$count = 0;

		foreach ( $sections as $rows ) {
			foreach ( $rows as $row ) {
				if ( $row['status'] === $status ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Clear the cache and go back to the Tester tab.
	 *
	 * @return void
	 */
	public static function refresh(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'lw-img' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( self::REFRESH_ACTION );

		delete_transient( self::CACHE_KEY );

		wp_safe_redirect( admin_url( 'admin.php?page=lw-img#tester' ) );
		exit;
	}
}
