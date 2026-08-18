<?php
/**
 * HelloImg API connectivity check.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Health;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\Client;
use LightweightPlugins\Img\Compat\CompetitorRegistry;
use LightweightPlugins\Img\Options;
use Throwable;

/**
 * One reachability probe against the account endpoint, plus a heads-up
 * when another optimizer plugin is active.
 */
final class ApiChecks {

	/**
	 * Check rows.
	 *
	 * @return array<int, array{label: string, status: string, message: string}>
	 */
	public static function rows(): array {
		$rows = [];

		if ( '' === (string) Options::get( 'api_key' ) ) {
			$rows[] = [
				'label'   => __( 'HelloImg API', 'lw-img' ),
				'status'  => 'warning',
				'message' => __( 'no API key configured', 'lw-img' ),
			];
		} else {
			try {
				( new Client() )->get_account();
				$rows[] = [
					'label'   => __( 'HelloImg API', 'lw-img' ),
					'status'  => 'ok',
					'message' => __( 'reachable', 'lw-img' ),
				];
			} catch ( Throwable $e ) {
				$rows[] = [
					'label'   => __( 'HelloImg API', 'lw-img' ),
					'status'  => 'critical',
					'message' => $e->getMessage(),
				];
			}
		}

		$competitors = CompetitorRegistry::active_competitors();
		if ( [] !== $competitors ) {
			$rows[] = [
				'label'   => __( 'Other optimizer plugins', 'lw-img' ),
				'status'  => 'warning',
				'message' => sprintf(
					/* translators: %s: plugin name list. */
					__( 'active: %s — consider keeping only one optimizer enabled', 'lw-img' ),
					implode( ', ', array_values( $competitors ) )
				),
			];
		}

		return $rows;
	}
}
