<?php
/**
 * Log REST controller.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Rest\Admin;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Log\EventLog;
use LightweightPlugins\Img\Log\LogEntryView;
use LightweightPlugins\Img\Log\LogQuery;
use LightweightPlugins\Img\Options;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /admin/log?page&per_page&type&search and DELETE /admin/log.
 */
final class LogController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		Routes::add(
			'/admin/log',
			[
				WP_REST_Server::READABLE  => 'list_entries',
				WP_REST_Server::DELETABLE => 'clear',
			],
			$this
		);
	}

	/**
	 * A filtered, paged slice of the log.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_entries( WP_REST_Request $request ): WP_REST_Response {
		$result = LogQuery::run(
			EventLog::all(),
			absint( $request->get_param( 'page' ) ?? 1 ),
			absint( $request->get_param( 'per_page' ) ?? LogQuery::DEFAULT_PER_PAGE ),
			sanitize_key( (string) $request->get_param( 'type' ) ),
			sanitize_text_field( (string) $request->get_param( 'search' ) )
		);

		$result['entries'] = array_map(
			static function ( array $entry ): array {
				$attachment = (int) ( $entry['attachment_id'] ?? 0 );
				$edit_url   = $attachment > 0 ? get_edit_post_link( $attachment, 'raw' ) : null;

				return LogEntryView::shape( $entry, is_string( $edit_url ) ? $edit_url : null );
			},
			$result['entries']
		);

		$result['enabled']     = (bool) Options::get( 'enable_log' );
		$result['max_entries'] = EventLog::MAX_ENTRIES;

		return new WP_REST_Response( $result );
	}

	/**
	 * Delete every log entry.
	 *
	 * @return WP_REST_Response
	 */
	public function clear(): WP_REST_Response {
		EventLog::clear();

		return new WP_REST_Response(
			[
				'cleared' => true,
				'message' => __( 'Log cleared.', 'lw-img' ),
			]
		);
	}
}
