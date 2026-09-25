<?php
/**
 * Stats REST controller.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Rest\Admin;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Stats\SiteStats;
use LightweightPlugins\Img\Stats\StatsCache;
use LightweightPlugins\Img\Stats\StatsView;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /admin/stats[?refresh=1] and POST /admin/stats/leftovers.
 *
 * The savings figures keep their hourly cache (refresh=1 drops it); the
 * leftover scan walks the whole uploads tree, so it only runs on its own
 * route — or once, the very first time the figures are built.
 */
final class StatsController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		Routes::add( '/admin/stats', [ WP_REST_Server::READABLE => 'get_stats' ], $this );
		Routes::add( '/admin/stats/leftovers', [ WP_REST_Server::CREATABLE => 'rescan_leftovers' ], $this );
	}

	/**
	 * Savings figures and leftovers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_stats( WP_REST_Request $request ): WP_REST_Response {
		if ( Routes::flag( $request->get_param( 'refresh' ) ) ) {
			StatsCache::forget();
		}

		return new WP_REST_Response( self::shape() );
	}

	/**
	 * Walk the uploads tree for leftovers again.
	 *
	 * @return WP_REST_Response
	 */
	public function rescan_leftovers(): WP_REST_Response {
		SiteStats::stored_leftovers( true );

		return new WP_REST_Response( self::shape() );
	}

	/**
	 * The stats response.
	 *
	 * @return array<string, mixed>
	 */
	private static function shape(): array {
		return StatsView::build( SiteStats::get(), (int) Options::get( 'backup_retention_days' ) );
	}
}
