<?php
/**
 * One-time upgrade of the legacy exclusion_patterns option into rules.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload\Rules;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;

/**
 * Turns every saved exclusion pattern into an `exclude` rule (ahead of any
 * rules already saved) and removes the old key. Lazy: runs on boot until
 * the old key is gone, then costs one array lookup per request.
 */
final class RuleMigration {

	/**
	 * Read, migrate, save — once.
	 *
	 * @return void
	 */
	public static function run(): void {
		$saved = get_option( Options::OPTION_NAME, [] );
		if ( ! is_array( $saved ) ) {
			return;
		}

		$migrated = self::migrate( $saved );
		if ( null === $migrated ) {
			return;
		}

		update_option( Options::OPTION_NAME, $migrated );
		Options::clear_cache();
	}

	/**
	 * Pure transformation of the stored options array.
	 *
	 * @param array<string, mixed> $saved Stored options.
	 * @return array<string, mixed>|null New options, or null when there is nothing to migrate.
	 */
	public static function migrate( array $saved ): ?array {
		if ( ! array_key_exists( 'exclusion_patterns', $saved ) ) {
			return null;
		}

		$rules  = is_array( $saved['pattern_rules'] ?? null ) ? array_values( $saved['pattern_rules'] ) : [];
		$legacy = [];

		foreach ( (array) $saved['exclusion_patterns'] as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}
			$legacy[] = [
				'pattern' => $pattern,
				'action'  => RuleSet::ACTION_EXCLUDE,
				'value'   => '',
			];
		}

		unset( $saved['exclusion_patterns'] );
		$saved['pattern_rules'] = array_merge( $legacy, $rules );

		return $saved;
	}
}
