<?php
/**
 * The account panel's data, from the /v1/account payload.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Pure shaping of the account response for the admin screen.
 *
 * The payload is {plan, month, website: {domain, images, bytes_saved}|null,
 * limit: {monthly_images, used, ...}|null}; limit is null on unlimited
 * plans. The site's own totals are local data (the API counts the whole
 * key/domain, not this install), passed in by the caller.
 */
final class AccountSummary {

	/**
	 * Build the response.
	 *
	 * @param array<string, mixed> $fetch     AccountFetcher::fetch() result.
	 * @param array<string, mixed> $key_state ApiKeyState::describe() result.
	 * @param string               $site_host This site's host.
	 * @param array<string, int>   $site      Local totals: optimized, saved.
	 * @return array<string, mixed>
	 */
	public static function build( array $fetch, array $key_state, string $site_host, array $site ): array {
		$account = is_array( $fetch['account'] ?? null ) ? $fetch['account'] : null;
		$ok      = ! empty( $fetch['ok'] ) && null !== $account;
		$website = $ok ? self::website( $account ) : null;
		$domain  = null === $website ? '' : $website['domain'];

		return [
			'configured'    => ! empty( $key_state['set'] ),
			'connected'     => $ok,
			'key'           => $key_state,
			'plan'          => $ok ? self::plan( (string) ( $account['plan'] ?? '' ) ) : null,
			'month'         => $ok && is_scalar( $account['month'] ?? null ) ? (string) $account['month'] : null,
			'usage'         => $ok ? self::usage( $account ) : null,
			'website'       => $website,
			'site_host'     => $site_host,
			'site_mismatch' => '' !== $domain && '' !== $site_host && ! self::host_matches( $site_host, $domain ),
			'site'          => [
				'optimized' => (int) ( $site['optimized'] ?? 0 ),
				'saved'     => (int) ( $site['saved'] ?? 0 ),
			],
			'error'         => self::error( $fetch, $key_state ),
			'checked_at'    => (int) ( $fetch['checked_at'] ?? 0 ) > 0 ? (int) $fetch['checked_at'] : null,
			'cached'        => ! empty( $fetch['cached'] ),
		];
	}

	/**
	 * The account-wide monthly limit block, or null on unlimited plans.
	 *
	 * @param array<string, mixed> $account Account payload.
	 * @return array{monthly_images: int, used: int}|null
	 */
	public static function limit_from( array $account ): ?array {
		$limit = $account['limit'] ?? null;
		if ( ! is_array( $limit ) ) {
			return null;
		}

		return [
			'monthly_images' => (int) ( $limit['monthly_images'] ?? 0 ),
			'used'           => (int) ( $limit['used'] ?? 0 ),
		];
	}

	/**
	 * Same rule the API applies to X-HIMG-Site: exact or subdomain, www. ignored.
	 *
	 * @param string $host   Host to check (e.g. this site's host).
	 * @param string $domain Domain the key is bound to.
	 * @return bool
	 */
	public static function host_matches( string $host, string $domain ): bool {
		$host   = (string) preg_replace( '/^www\./', '', strtolower( $host ) );
		$domain = (string) preg_replace( '/^www\./', '', strtolower( $domain ) );

		return $host === $domain || str_ends_with( $host, '.' . $domain );
	}

	/**
	 * Humanize the API's plan slug: "reseller_ai" → "Reseller AI".
	 *
	 * Underscores and hyphens both split; the "ai" token is uppercased as an
	 * initialism, everything else is ucfirst'd, so unknown future slugs
	 * still read well without a lookup table.
	 *
	 * @param string $plan Plan slug from the API.
	 * @return string Human-readable label, '' when the slug is empty.
	 */
	public static function plan_label( string $plan ): string {
		$words = array_filter( explode( '_', str_replace( '-', '_', strtolower( $plan ) ) ) );

		$words = array_map(
			static fn ( string $word ): string => 'ai' === $word ? 'AI' : ucfirst( $word ),
			$words
		);

		return implode( ' ', $words );
	}

	/**
	 * Plan slug and label.
	 *
	 * @param string $slug Plan slug.
	 * @return array{slug: string, label: string}|null
	 */
	private static function plan( string $slug ): ?array {
		if ( '' === $slug ) {
			return null;
		}

		return [
			'slug'  => $slug,
			'label' => self::plan_label( $slug ),
		];
	}

	/**
	 * Monthly usage: account-wide used/limit on limited plans.
	 *
	 * @param array<string, mixed> $account Account payload.
	 * @return array{limited: bool, monthly_limit: int|null, used: int|null, percent: float|null}
	 */
	private static function usage( array $account ): array {
		$limit = self::limit_from( $account );

		if ( null === $limit ) {
			return [
				'limited'       => false,
				'monthly_limit' => null,
				'used'          => null,
				'percent'       => null,
			];
		}

		$max = $limit['monthly_images'];

		return [
			'limited'       => true,
			'monthly_limit' => $max,
			'used'          => $limit['used'],
			'percent'       => $max > 0 ? round( min( 100, 100 * $limit['used'] / $max ), 1 ) : 0.0,
		];
	}

	/**
	 * This site's figures as the API counts them.
	 *
	 * @param array<string, mixed> $account Account payload.
	 * @return array{domain: string, images: int, bytes_saved: int}|null
	 */
	private static function website( array $account ): ?array {
		$website = $account['website'] ?? null;
		if ( ! is_array( $website ) ) {
			return null;
		}

		return [
			'domain'      => is_scalar( $website['domain'] ?? null ) ? (string) $website['domain'] : '',
			'images'      => (int) ( $website['images'] ?? 0 ),
			'bytes_saved' => (int) ( $website['bytes_saved'] ?? 0 ),
		];
	}

	/**
	 * The error block, or null when connected or no key is set.
	 *
	 * @param array<string, mixed> $fetch     Fetch result.
	 * @param array<string, mixed> $key_state Key state.
	 * @return array{code: string, message: string, status: int}|null
	 */
	private static function error( array $fetch, array $key_state ): ?array {
		if ( ! empty( $fetch['ok'] ) || empty( $key_state['set'] ) ) {
			return null;
		}

		return [
			'code'    => (string) ( $fetch['error_code'] ?? '' ),
			'message' => (string) ( $fetch['error'] ?? '' ),
			'status'  => (int) ( $fetch['http_status'] ?? 0 ),
		];
	}
}
