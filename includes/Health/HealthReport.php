<?php
/**
 * Collects all environment checks into one cached report.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Health;

defined( 'ABSPATH' ) || exit;

/**
 * The report includes live probes (API, cron loopback), so it is cached
 * for ten minutes; the Tester's refresh (REST ?refresh=1) clears the cache.
 */
final class HealthReport {

	public const CACHE_KEY = 'lw_img_health_report';

	/**
	 * Hook the settings-save invalidation.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'update_option_lw_img_options', [ self::class, 'invalidate' ] );
		add_action( 'add_option_lw_img_options', [ self::class, 'invalidate' ] );
	}

	/**
	 * Drop the cached report when the plugin settings change.
	 *
	 * A report generated before a settings save can contradict the new
	 * settings — the classic case is "API key missing" cached moments
	 * before the key is pasted in, leaving the Tester red for up to ten
	 * minutes. Re-probing on the next Tester view is cheaper than a
	 * stale verdict. The add_option variant covers the very first save,
	 * which creates the options row instead of updating it.
	 *
	 * @return void
	 */
	public static function invalidate(): void {
		delete_transient( self::CACHE_KEY );
		RedirectProbe::forget();
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
				'redirects'   => RedirectChecks::rows(),
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
	 * The most severe status among the given rows.
	 *
	 * @param array<int, array{label: string, status: string, message: string}> $rows Check rows.
	 * @return string critical|warning|ok
	 */
	public static function worst_status( array $rows ): string {
		$worst = 'ok';

		foreach ( $rows as $row ) {
			if ( 'critical' === $row['status'] ) {
				return 'critical';
			}
			if ( 'warning' === $row['status'] ) {
				$worst = 'warning';
			}
		}

		return $worst;
	}

	/**
	 * Every warning/critical row across all sections, criticals first.
	 *
	 * @param array<string, array<int, array<string, string>>> $sections Report sections.
	 * @return array<int, array<string, string>>
	 */
	public static function attention_rows( array $sections ): array {
		$critical = [];
		$warning  = [];

		foreach ( $sections as $rows ) {
			foreach ( $rows as $row ) {
				if ( 'critical' === $row['status'] ) {
					$critical[] = $row;
				} elseif ( 'warning' === $row['status'] ) {
					$warning[] = $row;
				}
			}
		}

		return array_merge( $critical, $warning );
	}
}
