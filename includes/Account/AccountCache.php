<?php
/**
 * Server-side cache of the HelloImg account response.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the last /v1/account answer so opening the settings screen does not
 * call the API every time. Entries are bound to the key they were fetched
 * with (by a fingerprint, never the key itself), so a key change can never
 * serve another key's account.
 */
final class AccountCache {

	public const TRANSIENT = 'lw_img_account';

	/**
	 * Seconds a successful answer is kept.
	 */
	public const TTL_OK = 600;

	/**
	 * Seconds a failure is kept: short, so a fixed key or network shows up
	 * quickly, but long enough that a broken key is not re-tried per view.
	 */
	public const TTL_ERROR = 60;

	/**
	 * Cached entry for this key, or null.
	 *
	 * @param string $fingerprint Fingerprint of the key in use.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $fingerprint ): ?array {
		$entry = get_transient( self::TRANSIENT );

		if ( ! is_array( $entry ) || ( $entry['fingerprint'] ?? '' ) !== $fingerprint ) {
			return null;
		}

		return $entry;
	}

	/**
	 * Store an entry.
	 *
	 * @param string               $fingerprint Fingerprint of the key in use.
	 * @param array<string, mixed> $entry       Fetch result.
	 * @return void
	 */
	public static function put( string $fingerprint, array $entry ): void {
		$entry['fingerprint'] = $fingerprint;

		set_transient( self::TRANSIENT, $entry, ! empty( $entry['ok'] ) ? self::TTL_OK : self::TTL_ERROR );
	}

	/**
	 * Drop the cached entry (key changed).
	 *
	 * @return void
	 */
	public static function forget(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Non-reversible fingerprint of a key.
	 *
	 * @param string $key API key.
	 * @return string
	 */
	public static function fingerprint( string $key ): string {
		return substr( hash( 'sha256', 'lw-img|' . $key ), 0, 20 );
	}
}
