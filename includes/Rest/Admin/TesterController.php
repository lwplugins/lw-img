<?php
/**
 * Tester REST controller.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Rest\Admin;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Health\HealthReport;
use LightweightPlugins\Img\Health\TesterView;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /admin/tester[?refresh=1]: the environment report (10-minute cache).
 * refresh=1 also drops the redirect-probe verdict, which the bulk start
 * gate reads — a server fix confirmed here unblocks Start right away.
 */
final class TesterController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		Routes::add( '/admin/tester', [ WP_REST_Server::READABLE => 'get_report' ], $this );
	}

	/**
	 * The report.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_report( WP_REST_Request $request ): WP_REST_Response {
		if ( Routes::flag( $request->get_param( 'refresh' ) ) ) {
			HealthReport::invalidate();
		}

		return new WP_REST_Response( TesterView::build( HealthReport::get() ) );
	}
}
