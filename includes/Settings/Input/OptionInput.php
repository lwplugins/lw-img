<?php
/**
 * The input layer for settings writes.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings\Input;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\DefaultOptions;
use LightweightPlugins\Img\Settings\SettingsSchema;

/**
 * Turns raw submitted values into option values, per key.
 *
 * Only the keys that were sent are parsed (a partial save never touches the
 * others), a blank or out-of-range number is an error instead of a silent
 * clamp, and a key pinned in wp-config.php cannot be written.
 */
final class OptionInput {

	/**
	 * Parse a batch of submitted values.
	 *
	 * @param array<string|int, mixed> $raw        Submitted key => value pairs.
	 * @param bool                     $key_pinned Whether the API key is pinned by LW_IMG_API_KEY.
	 * @return InputReport
	 */
	public static function parse( array $raw, bool $key_pinned ): InputReport {
		$defaults = DefaultOptions::all();
		$values   = [];
		$errors   = [];
		$unknown  = [];

		foreach ( $raw as $key => $value ) {
			$key = (string) $key;

			if ( ! array_key_exists( $key, $defaults ) ) {
				$unknown[] = $key;
				continue;
			}

			if ( SettingsSchema::SECRET_KEY === $key && $key_pinned ) {
				$errors[ $key ] = [
					sprintf(
						/* translators: %s: constant name */
						__( 'The API key is set by %s in wp-config.php and cannot be changed here.', 'lw-img' ),
						SettingsSchema::SECRET_CONSTANT
					),
				];
				continue;
			}

			$result = self::parse_value( $key, $value );

			if ( $result->is_valid() ) {
				$values[ $key ] = $result->value();
				continue;
			}

			$errors[ $key ] = $result->errors();
			foreach ( $result->field_errors() as $path => $messages ) {
				$errors[ $path ] = $messages;
			}
		}

		return new InputReport( $values, $errors, $unknown );
	}

	/**
	 * Parse one value for a known key.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Raw value.
	 * @return ParseResult
	 */
	public static function parse_value( string $key, mixed $value ): ParseResult {
		switch ( $key ) {
			case SettingsSchema::SECRET_KEY:
				return ApiKeyParser::parse( $value );
			case 'pattern_rules':
				return RuleListParser::parse( $value );
			case 'smartcrop_sizes':
				return NameListParser::sizes( $value );
			case 'mime_types':
				return NameListParser::mime_types( $value );
		}

		$ranges = SettingsSchema::ranges();
		if ( isset( $ranges[ $key ] ) ) {
			return IntParser::parse( $value, $ranges[ $key ][0], $ranges[ $key ][1] );
		}

		$enums = SettingsSchema::enums();
		if ( isset( $enums[ $key ] ) ) {
			return EnumParser::parse( $value, $enums[ $key ] );
		}

		if ( is_bool( DefaultOptions::all()[ $key ] ?? null ) ) {
			return BoolParser::parse( $value );
		}

		/* translators: %s: setting key */
		return ParseResult::fail( [ sprintf( __( 'Unknown setting: %s.', 'lw-img' ), $key ) ] );
	}
}
