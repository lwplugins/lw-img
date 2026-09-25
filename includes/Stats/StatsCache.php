<?php
/**
 * Access to the cached savings figures.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Reads or drops the hourly SiteStats figures without computing them (the
 * leftover scan, stored separately, is never touched here).
 */
final class StatsCache {

	/**
	 * Drop the cached savings figures.
	 *
	 * @return void
	 */
	public static function forget(): void {
		delete_transient( SiteStats::CACHE_KEY );
	}

	/**
	 * The cached figures, or null when there are none.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function peek(): ?array {
		$cached = get_transient( SiteStats::CACHE_KEY );

		return is_array( $cached ) ? $cached : null;
	}
}
