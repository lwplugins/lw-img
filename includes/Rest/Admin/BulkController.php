<?php
/**
 * Bulk REST controller.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Rest\Admin;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Bulk\BackgroundWorker;
use LightweightPlugins\Img\Bulk\BulkActions;
use LightweightPlugins\Img\Bulk\BulkStatus;
use LightweightPlugins\Img\Bulk\StatusMeta;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /admin/bulk[?assist=1] and POST /admin/bulk/{start,cancel,
 * retry-failed,rescan,speed}. Every route answers with the status object;
 * actions add a notice (and the number of re-queued images).
 */
final class BulkController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		Routes::add( '/admin/bulk', [ WP_REST_Server::READABLE => 'status' ], $this );
		Routes::add( '/admin/bulk/start', [ WP_REST_Server::CREATABLE => 'start' ], $this );
		Routes::add( '/admin/bulk/cancel', [ WP_REST_Server::CREATABLE => 'cancel' ], $this );
		Routes::add( '/admin/bulk/retry-failed', [ WP_REST_Server::CREATABLE => 'retry_failed' ], $this );
		Routes::add( '/admin/bulk/rescan', [ WP_REST_Server::CREATABLE => 'rescan' ], $this );
		Routes::add( '/admin/bulk/speed', [ WP_REST_Server::CREATABLE => 'speed' ], $this );
	}

	/**
	 * Current status. With assist=1 a stalled run is pushed along inline
	 * first (about 8 seconds of work) — the poll keeps a run moving on hosts
	 * whose cron loopback fails.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function status( WP_REST_Request $request ): WP_REST_Response {
		if ( Routes::flag( $request->get_param( 'assist' ) ) ) {
			BackgroundWorker::assist();
		}

		return new WP_REST_Response( BulkStatus::get() );
	}

	/**
	 * Start a run.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function start() {
		return self::respond( BulkActions::start() );
	}

	/**
	 * Cancel the run.
	 *
	 * @return WP_REST_Response
	 */
	public function cancel(): WP_REST_Response {
		return self::respond( BulkActions::cancel() );
	}

	/**
	 * Re-queue failed images.
	 *
	 * @return WP_REST_Response
	 */
	public function retry_failed(): WP_REST_Response {
		return self::respond( BulkActions::requeue( StatusMeta::FAILED ) );
	}

	/**
	 * Re-queue skipped images.
	 *
	 * @return WP_REST_Response
	 */
	public function rescan(): WP_REST_Response {
		return self::respond( BulkActions::requeue( StatusMeta::SKIPPED ) );
	}

	/**
	 * Save the processing speed ({profile}).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function speed( WP_REST_Request $request ) {
		return self::respond( BulkActions::set_speed( $request->get_param( 'profile' ) ) );
	}

	/**
	 * The status object with the action's notice, or the error.
	 *
	 * @param array<string, mixed>|WP_Error $result Action result.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function respond( $result ) {
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$status           = BulkStatus::get();
		$status['notice'] = [
			'type'    => (string) $result['type'],
			'message' => (string) $result['message'],
		];

		if ( isset( $result['queued'] ) ) {
			$status['queued'] = (int) $result['queued'];
		}

		return new WP_REST_Response( $status );
	}
}
