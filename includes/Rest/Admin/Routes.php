<?php
/**
 * Admin REST routes bootstrap.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Rest\Admin;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Registers the lw-img/v1/admin/* routes used by the React admin.
 *
 * Every route requires manage_options; REST cookie auth supplies the nonce.
 */
final class Routes {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'lw-img/v1';

	/**
	 * Hook the route registration.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register every admin route.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		( new SettingsController() )->register_routes();
		( new AccountController() )->register_routes();
		( new BulkController() )->register_routes();
		( new StatsController() )->register_routes();
		( new BackupController() )->register_routes();
		( new TesterController() )->register_routes();
		( new LogController() )->register_routes();
	}

	/**
	 * Permission callback shared by all admin routes.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register one route with a handler per HTTP method.
	 *
	 * @param string                $path     Route path under the namespace.
	 * @param array<string, string> $handlers Method constant => public method name on $owner.
	 * @param object                $owner    Controller instance.
	 * @return void
	 */
	public static function add( string $path, array $handlers, object $owner ): void {
		$endpoints = [];

		foreach ( $handlers as $methods => $callback ) {
			$endpoints[] = [
				'methods'             => $methods,
				'callback'            => [ $owner, $callback ],
				'permission_callback' => [ self::class, 'can_manage' ],
			];
		}

		register_rest_route( self::NAMESPACE, $path, $endpoints );
	}

	/**
	 * A translated REST error.
	 *
	 * @param string               $code    Error code.
	 * @param string               $message Translated message.
	 * @param int                  $status  HTTP status.
	 * @param array<string, mixed> $data    Extra error data.
	 * @return WP_Error
	 */
	public static function error( string $code, string $message, int $status, array $data = [] ): WP_Error {
		return new WP_Error( $code, $message, array_merge( [ 'status' => $status ], $data ) );
	}

	/**
	 * Whether a query flag such as ?refresh=1 is set.
	 *
	 * @param mixed $value Raw parameter.
	 * @return bool
	 */
	public static function flag( mixed $value ): bool {
		return in_array( is_scalar( $value ) ? strtolower( (string) $value ) : '', [ '1', 'true', 'yes' ], true );
	}
}
