<?php
/**
 * WP-CLI command: the Tester tab's environment checks from the terminal.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\CLI;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Health\HealthReport;
use WP_CLI;

/**
 * Runs every health check fresh and prints them as a table, so hosting
 * problems (missing WebP support, swallowed uploads 404s, MyISAM
 * tables) can be diagnosed over SSH or watched from monitoring. Exits
 * non-zero when any check is critical.
 */
final class DoctorCommand {

	/**
	 * Run the environment checks.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table (default), csv, json, yaml, or count.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-img doctor
	 *     wp lw-img doctor --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$report = HealthReport::get( true );
		$rows   = self::flatten( $report['sections'] );

		WP_CLI\Utils\format_items(
			isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table',
			$rows,
			[ 'section', 'check', 'status', 'message' ]
		);

		foreach ( $rows as $row ) {
			if ( '' !== (string) ( $row['fix'] ?? '' ) ) {
				WP_CLI::log( sprintf( 'fix (%s): %s', $row['check'], $row['fix'] ) );
			}
		}

		$counts = self::status_counts( $rows );

		if ( ( $counts['critical'] ?? 0 ) > 0 ) {
			WP_CLI::error( sprintf( '%d critical check(s) failed.', $counts['critical'] ) );
		}

		WP_CLI::success(
			sprintf(
				'%d checks — %s.',
				count( $rows ),
				implode( ', ', array_map( static fn ( string $s, int $n ): string => "$n $s", array_keys( $counts ), $counts ) )
			)
		);
	}

	/**
	 * Flatten the report's section map into one row per check.
	 *
	 * @param array<string, array<int, array<string, string>>> $sections Report sections.
	 * @return array<int, array<string, string>>
	 */
	public static function flatten( array $sections ): array {
		$rows = [];

		foreach ( $sections as $section => $checks ) {
			foreach ( $checks as $check ) {
				$row = [
					'section' => (string) $section,
					'check'   => (string) ( $check['label'] ?? '' ),
					'status'  => (string) ( $check['status'] ?? '' ),
					'message' => (string) ( $check['message'] ?? '' ),
				];
				if ( '' !== (string) ( $check['fix'] ?? '' ) ) {
					$row['fix'] = (string) $check['fix'];
				}
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Count rows per status, in first-seen order.
	 *
	 * @param array<int, array<string, string>> $rows Flattened rows.
	 * @return array<string, int>
	 */
	public static function status_counts( array $rows ): array {
		$counts = [];

		foreach ( $rows as $row ) {
			$status            = (string) ( $row['status'] ?? '' );
			$counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;
		}

		return $counts;
	}
}
