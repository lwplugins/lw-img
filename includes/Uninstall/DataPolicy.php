<?php
/**
 * What plugin deletion may remove.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Uninstall;

use LightweightPlugins\Img\Bulk\BulkJob;
use LightweightPlugins\Img\Bulk\UnoptimizedQuery;
use LightweightPlugins\Img\Compat\CompetitorNotice;
use LightweightPlugins\Img\Db\Schema;
use LightweightPlugins\Img\Health\HealthReport;
use LightweightPlugins\Img\Health\RedirectProbe;
use LightweightPlugins\Img\Log\EventLog;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Stats\SiteStats;

defined( 'ABSPATH' ) || exit;

/**
 * Pure rules for uninstall.php: cron and transients always go (they are
 * rebuilt on activation); settings, the API key, the event log and the
 * per-image table go only when the site owner opted in — either in the
 * deactivation dialog or on the Backup tab. Backup originals never go.
 */
final class DataPolicy {

	public const OPTION_KEY = 'delete_on_uninstall';

	/**
	 * Options that are safe to drop every time (regenerated at runtime).
	 *
	 * @var array<int, string>
	 */
	public const VOLATILE_OPTIONS = [
		BulkJob::OPTION_NAME,
		UnoptimizedQuery::CURSOR_OPTION,
		SiteStats::LEFTOVERS_OPTION,
	];

	/**
	 * Transients that are safe to drop every time (regenerated at runtime).
	 *
	 * @var array<int, string>
	 */
	public const VOLATILE_TRANSIENTS = [
		'lw_img_stats', // SiteStats::CACHE_KEY is private; kept as a literal here.
		UnoptimizedQuery::COUNT_TRANSIENT,
		RedirectProbe::CACHE_KEY,
		HealthReport::CACHE_KEY,
	];

	/**
	 * Options that hold the owner's configuration and history.
	 *
	 * @var array<int, string>
	 */
	public const PERSISTENT_OPTIONS = [
		Options::OPTION_NAME,
		'lw_img_version', // Written by Activator as a literal; no constant exists for it.
		Schema::VERSION_OPTION,
		EventLog::OPTION_NAME,
		CompetitorNotice::OPTION_NAME,
	];

	/**
	 * Whether the stored options ask for a full wipe.
	 *
	 * @param mixed $saved_options The raw lw_img_options value.
	 * @return bool
	 */
	public static function should_wipe( mixed $saved_options ): bool {
		if ( ! is_array( $saved_options ) ) {
			return false;
		}

		return ! empty( $saved_options[ self::OPTION_KEY ] );
	}
}
