<?php
/**
 * Fetches the account, through the cache.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Account;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\ApiException;
use LightweightPlugins\Img\Api\Client;
use LightweightPlugins\Img\Options;
use Throwable;

/**
 * One cached /v1/account call. `refresh` bypasses the cache (the Test
 * connection button); without a key no call is made at all.
 */
final class AccountFetcher {

	/**
	 * The fetch result.
	 *
	 * @param bool $refresh Bypass the cache.
	 * @return array{ok: bool, account: array<string, mixed>|null, error: string, error_code: string, http_status: int, checked_at: int, cached: bool}
	 */
	public static function fetch( bool $refresh = false ): array {
		$key = trim( (string) Options::get( 'api_key' ) );

		if ( '' === $key ) {
			return self::entry( false, null, '', '', 0, 0, false );
		}

		$fingerprint = AccountCache::fingerprint( $key );

		if ( ! $refresh ) {
			$cached = AccountCache::get( $fingerprint );
			if ( null !== $cached ) {
				return self::entry(
					! empty( $cached['ok'] ),
					is_array( $cached['account'] ?? null ) ? $cached['account'] : null,
					(string) ( $cached['error'] ?? '' ),
					(string) ( $cached['error_code'] ?? '' ),
					(int) ( $cached['http_status'] ?? 0 ),
					(int) ( $cached['checked_at'] ?? 0 ),
					true
				);
			}
		}

		$entry = self::call( $key );
		AccountCache::put( $fingerprint, $entry );

		return $entry;
	}

	/**
	 * Call the API.
	 *
	 * @param string $key API key.
	 * @return array{ok: bool, account: array<string, mixed>|null, error: string, error_code: string, http_status: int, checked_at: int, cached: bool}
	 */
	private static function call( string $key ): array {
		try {
			$account = ( new Client( $key ) )->get_account();

			return self::entry( true, $account, '', '', 200, time(), false );
		} catch ( ApiException $e ) {
			return self::entry( false, null, $e->getMessage(), $e->get_error_code(), $e->get_http_status(), time(), false );
		} catch ( Throwable $e ) {
			return self::entry( false, null, $e->getMessage(), 'unknown', 0, time(), false );
		}
	}

	/**
	 * Build a result.
	 *
	 * @param bool                      $ok          Whether the call succeeded.
	 * @param array<string, mixed>|null $account     Account payload.
	 * @param string                    $error       Error message.
	 * @param string                    $error_code  Error code.
	 * @param int                       $http_status HTTP status.
	 * @param int                       $checked_at  Unix time of the call.
	 * @param bool                      $cached      Whether it came from the cache.
	 * @return array{ok: bool, account: array<string, mixed>|null, error: string, error_code: string, http_status: int, checked_at: int, cached: bool}
	 */
	private static function entry( bool $ok, ?array $account, string $error, string $error_code, int $http_status, int $checked_at, bool $cached ): array {
		return [
			'ok'          => $ok,
			'account'     => $account,
			'error'       => $error,
			'error_code'  => $error_code,
			'http_status' => $http_status,
			'checked_at'  => $checked_at,
			'cached'      => $cached,
		];
	}
}
