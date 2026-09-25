<?php
/**
 * Settings read/write for the admin API.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\DefaultOptions;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Settings\Input\OptionInput;

/**
 * Reads the settings as typed values (the API key masked) and applies
 * partial, atomic updates: a key the client did not send keeps its stored
 * value — including the settings without a control (mime_types,
 * debug_mode) — and nothing is saved when any submitted field is invalid.
 */
final class SettingsStore {

	/**
	 * Every setting, typed like its default, with the API key replaced by
	 * its masked state.
	 *
	 * @return array<string, mixed>
	 */
	public static function current(): array {
		$stored = Options::get_all();
		$typed  = SettingsTypes::typed( $stored, DefaultOptions::all() );

		$typed[ SettingsSchema::SECRET_KEY ] = ApiKeyState::describe(
			Options::constant_api_key(),
			(string) ( $stored[ SettingsSchema::SECRET_KEY ] ?? '' )
		);

		return $typed;
	}

	/**
	 * Apply a partial update.
	 *
	 * @param array<string|int, mixed> $body Submitted key => value pairs.
	 * @return array<string, array<int, string>> Messages per invalid field; empty when saved.
	 */
	public static function save( array $body ): array {
		$pinned = null !== Options::constant_api_key();
		$report = OptionInput::parse( $body, $pinned );

		if ( $report->has_errors() ) {
			return $report->errors();
		}

		if ( [] !== $report->values() ) {
			self::write( $report->values() );
		}

		return [];
	}

	/**
	 * Merge validated values over the stored settings and persist them.
	 *
	 * Starts from the stored (database-only) settings, so a wp-config.php
	 * key is never copied into the database, and keeps only known keys.
	 *
	 * @param array<string, mixed> $values Parsed values keyed by option.
	 * @return void
	 */
	public static function write( array $values ): void {
		$before = array_intersect_key( Options::get_all(), DefaultOptions::all() );
		$after  = array_merge( $before, $values );

		Options::save( $after );
		SaveEffects::apply( $before, $after );
	}
}
