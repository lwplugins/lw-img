<?php
/**
 * WP-Cron and loopback connectivity checks.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Background bulk runs ride on WP-Cron; when the server cannot reach its
 * own site (broken loopback), runs only progress while the Bulk tab is
 * open (the status poll keeps them moving) or via a system cron runner.
 */
final class CronChecks {

	/**
	 * Check rows.
	 *
	 * @return array<int, array{label: string, status: string, message: string}>
	 */
	public static function rows(): array {
		$rows = [];

		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$rows[]   = [
			'label'   => __( 'WP-Cron', 'lw-img' ),
			'status'  => 'info',
			'message' => $disabled
				? __( 'DISABLE_WP_CRON is set — a system cron runner is expected to trigger background work', 'lw-img' )
				: __( 'built-in (triggered by site visits)', 'lw-img' ),
		];

		$response = wp_remote_post(
			site_url( 'wp-cron.php' ),
			[
				'timeout'   => 3,
				'sslverify' => false,
			]
		);

		$ok     = ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) < 400;
		$rows[] = [
			'label'   => __( 'Cron loopback', 'lw-img' ),
			'status'  => $ok ? 'ok' : 'warning',
			'message' => $ok
				? __( 'the server can reach its own site', 'lw-img' )
				: __( 'the server cannot reach its own site — background runs progress only while the Bulk tab is open, via site traffic, or via WP-CLI', 'lw-img' ),
		];

		return $rows;
	}
}
