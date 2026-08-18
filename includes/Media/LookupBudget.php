<?php
/**
 * Rolling-window budget for the expensive 404 attachment lookup.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Media;

defined( 'ABSPATH' ) || exit;

/**
 * The 404 redirect's fallback query searches postmeta by value, which no
 * index covers: on a 95k-image library it examines ~216,000 rows and costs
 * ~0.11s. Anyone can trigger it, without logging in, by requesting any
 * missing image path under the uploads directory.
 *
 * So the fallback gets a budget: a fixed number of misses per rolling
 * window, tracked in a single transient. Past the limit the fallback is
 * skipped until the window resets — the cheap, indexed backup-path lookup
 * keeps running, so images we actually converted still redirect.
 *
 * Deliberately one counter and not a per-path negative cache: caching each
 * miss under its own key would write two options rows per probe on sites
 * with no external object cache, which turns a read amplification into a
 * write amplification for an attacker who simply varies the path.
 */
final class LookupBudget {

	private const TRANSIENT = 'lw_img_lookup_budget';

	private const WINDOW = 60;

	private const LIMIT = 20;

	/**
	 * Take one unit from the current window.
	 *
	 * @return bool True when the caller may run the expensive lookup.
	 */
	public static function consume(): bool {
		$now   = time();
		$state = self::window( get_transient( self::TRANSIENT ), $now );

		if ( ! self::has_room( $state ) ) {
			return false;
		}

		++$state['count'];
		set_transient( self::TRANSIENT, $state, self::WINDOW );

		return true;
	}

	/**
	 * The window to count in: the stored one while it is still open, a fresh
	 * one otherwise.
	 *
	 * @param mixed $stored Stored state, if any.
	 * @param int   $now    Current timestamp.
	 * @return array{until: int, count: int}
	 */
	public static function window( mixed $stored, int $now ): array {
		if ( is_array( $stored ) && (int) ( $stored['until'] ?? 0 ) > $now ) {
			return [
				'until' => (int) $stored['until'],
				'count' => max( 0, (int) ( $stored['count'] ?? 0 ) ),
			];
		}

		return [
			'until' => $now + self::WINDOW,
			'count' => 0,
		];
	}

	/**
	 * Whether the window still has room for one more lookup.
	 *
	 * @param array{until: int, count: int} $state Current window.
	 * @return bool
	 */
	public static function has_room( array $state ): bool {
		return $state['count'] < self::LIMIT;
	}
}
