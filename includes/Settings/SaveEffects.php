<?php
/**
 * Side effects of a settings save.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Account\AccountCache;
use LightweightPlugins\Img\Bulk\UnoptimizedQuery;
use LightweightPlugins\Img\Health\HealthReport;

/**
 * What must happen after the settings changed, in order:
 *
 * 1. Drop the Tester report and the redirect-probe verdict (the classic
 *    save did this through the update_option hook, which REST requests do
 *    not register — so it is explicit here).
 * 2. A new API key drops the cached account response.
 * 3. A new MIME list changes the queue, so the cached pending count goes.
 *
 * The retention cleanup needs no reschedule: it reads the retention days
 * when it runs. Legacy exclusion patterns are migrated on every boot
 * (RuleMigration), before any save can happen.
 */
final class SaveEffects {

	/**
	 * Run the effects for the keys whose value changed.
	 *
	 * @param array<string, mixed> $before Settings before the save.
	 * @param array<string, mixed> $after  Settings after the save.
	 * @return void
	 */
	public static function apply( array $before, array $after ): void {
		$changed = self::changed_keys( $before, $after );

		if ( [] === $changed ) {
			return;
		}

		HealthReport::invalidate();

		if ( in_array( SettingsSchema::SECRET_KEY, $changed, true ) ) {
			AccountCache::forget();
		}

		if ( in_array( 'mime_types', $changed, true ) ) {
			delete_transient( UnoptimizedQuery::COUNT_TRANSIENT );
		}
	}

	/**
	 * Keys whose value differs between two settings arrays.
	 *
	 * @param array<string, mixed> $before Settings before.
	 * @param array<string, mixed> $after  Settings after.
	 * @return array<int, string>
	 */
	public static function changed_keys( array $before, array $after ): array {
		$changed = [];

		foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $key ) {
			if ( ( $before[ $key ] ?? null ) !== ( $after[ $key ] ?? null ) ) {
				$changed[] = (string) $key;
			}
		}

		return $changed;
	}
}
