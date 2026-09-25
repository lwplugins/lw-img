<?php
/**
 * Account REST controller.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Rest\Admin;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Account\AccountFetcher;
use LightweightPlugins\Img\Account\AccountSummary;
use LightweightPlugins\Img\Api\SiteHost;
use LightweightPlugins\Img\Db\ImageRepository;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Settings\ApiKeyState;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET lw-img/v1/admin/account[?refresh=1].
 *
 * Served from a 10-minute cache; refresh=1 (the Test connection button)
 * calls the API now.
 */
final class AccountController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		Routes::add( '/admin/account', [ WP_REST_Server::READABLE => 'get_account' ], $this );
	}

	/**
	 * The connection state and account figures.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_account( WP_REST_Request $request ): WP_REST_Response {
		$fetch   = AccountFetcher::fetch( Routes::flag( $request->get_param( 'refresh' ) ) );
		$stored  = (string) ( Options::get_all()['api_key'] ?? '' );
		$totals  = ImageRepository::savings();
		$summary = AccountSummary::build(
			$fetch,
			ApiKeyState::describe( Options::constant_api_key(), $stored ),
			SiteHost::current(),
			[
				'optimized' => $totals['count'],
				'saved'     => max( 0, $totals['original'] - $totals['optimized'] ),
			]
		);

		return new WP_REST_Response( $summary );
	}
}
