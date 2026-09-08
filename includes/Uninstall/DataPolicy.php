<?php
/**
 * What plugin deletion may remove.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Uninstall;

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
		'lw_img_bulk_job',
		'lw_img_bulk_cursor',
		'lw_img_leftovers',
	];

	/**
	 * Options that hold the owner's configuration and history.
	 *
	 * @var array<int, string>
	 */
	public const PERSISTENT_OPTIONS = [
		'lw_img_options',
		'lw_img_version',
		'lw_img_db_version',
		'lw_img_log',
		'lw_img_competitor_notice_dismissed',
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
