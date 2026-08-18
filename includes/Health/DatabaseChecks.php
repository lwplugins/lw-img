<?php
/**
 * Database engine and version checks.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Flags MyISAM/Aria core tables: with table-level locking a bulk run's
 * scans and the site's own queries block each other, which can make the
 * whole site unresponsive during optimization.
 */
final class DatabaseChecks {

	private const HOT_TABLES = [ 'postmeta', 'posts' ];

	/**
	 * Check rows.
	 *
	 * @return array<int, array{label: string, status: string, message: string}>
	 */
	public static function rows(): array {
		global $wpdb;

		$rows = [];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- environment info; report is transient-cached by HealthReport.
		$version = (string) $wpdb->get_var( 'SELECT VERSION()' );
		$rows[]  = [
			'label'   => __( 'Database server', 'lw-img' ),
			'status'  => 'info',
			'message' => $version,
		];

		$tables = [ 'posts', 'postmeta', 'options', 'comments', 'commentmeta', 'usermeta', 'termmeta' ];
		$names  = array_map( static fn ( string $table ): string => $wpdb->{$table}, $tables );

		$placeholders = implode( ',', array_fill( 0, count( $names ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- information_schema lookup with %s placeholders only; report is transient-cached.
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT table_name AS name, engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ($placeholders)", $names )
		);
		// phpcs:enable

		$engines = [];
		foreach ( $results as $result ) {
			$engines[ (string) $result->name ] = (string) $result->engine;
		}

		foreach ( $tables as $table ) {
			$engine = $engines[ $wpdb->{$table} ] ?? '';
			$status = self::engine_status( $table, $engine );

			$row = [
				'label'   => $wpdb->{$table},
				'status'  => $status,
				'message' => $engine,
			];

			if ( 'ok' !== $status && 'info' !== $status ) {
				$row['message'] = $engine . ' — ' . __( 'table-level locking can freeze the site during bulk runs', 'lw-img' );
				$row['fix']     = "ALTER TABLE {$wpdb->{$table}} ENGINE=InnoDB";
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Status for a table's storage engine.
	 *
	 * @param string $table  Core table key (e.g. 'postmeta').
	 * @param string $engine Reported engine name.
	 * @return string ok|warning|critical|info
	 */
	public static function engine_status( string $table, string $engine ): string {
		if ( '' === $engine ) {
			return 'info';
		}

		if ( 'innodb' === strtolower( $engine ) ) {
			return 'ok';
		}

		return in_array( $table, self::HOT_TABLES, true ) ? 'critical' : 'warning';
	}
}
