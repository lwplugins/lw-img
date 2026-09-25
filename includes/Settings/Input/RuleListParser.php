<?php
/**
 * Pattern-rule list parser.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings\Input;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Settings\SettingsSchema;
use LightweightPlugins\Img\Upload\Rules\RuleSet;

/**
 * Validates the pattern-rule rows ({pattern, action, value}). A bad row is
 * reported with its position ("rules.3.pattern") instead of being dropped
 * silently, as the classic form did.
 */
final class RuleListParser {

	/**
	 * Most rules the list may hold (every upload and bulk image walks them).
	 */
	public const MAX_RULES = 100;

	/**
	 * Longest accepted pattern (matches the table's detail column width).
	 */
	private const MAX_PATTERN = 191;

	/**
	 * Parse the submitted rows.
	 *
	 * @param mixed $raw List of rule rows.
	 * @return ParseResult
	 */
	public static function parse( mixed $raw ): ParseResult {
		if ( ! is_array( $raw ) || array_keys( $raw ) !== array_keys( array_values( $raw ) ) ) {
			return ParseResult::fail( [ __( 'Must be a list of rules.', 'lw-img' ) ] );
		}

		if ( count( $raw ) > self::MAX_RULES ) {
			/* translators: %d: maximum number of rules */
			return ParseResult::fail( [ sprintf( __( 'Too many rules: at most %d are allowed.', 'lw-img' ), self::MAX_RULES ) ] );
		}

		$rules  = [];
		$fields = [];

		foreach ( $raw as $index => $row ) {
			$errors = self::row_errors( $row );

			foreach ( $errors as $field => $message ) {
				$fields[ 'rules.' . $index . '.' . $field ] = [ $message ];
			}

			if ( [] === $errors ) {
				$rules[] = self::clean( (array) $row );
			}
		}

		if ( [] !== $fields ) {
			return ParseResult::fail( [ __( 'Some pattern rules are not valid.', 'lw-img' ) ], $fields );
		}

		return ParseResult::ok( $rules );
	}

	/**
	 * Messages per field of one row; empty when the row is valid.
	 *
	 * @param mixed $row Submitted row.
	 * @return array<string, string>
	 */
	private static function row_errors( mixed $row ): array {
		if ( ! is_array( $row ) ) {
			return [ 'pattern' => __( 'Each rule needs a pattern and an action.', 'lw-img' ) ];
		}

		$errors  = [];
		$pattern = self::pattern( $row );
		$action  = is_string( $row['action'] ?? null ) ? $row['action'] : '';

		if ( '' === $pattern ) {
			$errors['pattern'] = __( 'Enter a pattern, e.g. *-logo.png.', 'lw-img' );
		} elseif ( strlen( $pattern ) > self::MAX_PATTERN ) {
			/* translators: %d: maximum pattern length */
			$errors['pattern'] = sprintf( __( 'The pattern is too long (at most %d characters).', 'lw-img' ), self::MAX_PATTERN );
		}

		if ( ! in_array( $action, RuleSet::ACTIONS, true ) ) {
			$errors['action'] = __( 'Choose what the rule does.', 'lw-img' );
		} elseif ( RuleSet::ACTION_LEVEL === $action && ! in_array( $row['value'] ?? null, SettingsSchema::levels(), true ) ) {
			$errors['value'] = __( 'Choose an optimization level.', 'lw-img' );
		}

		if ( array_key_exists( 'value', $row ) && ! is_scalar( $row['value'] ) && null !== $row['value'] ) {
			$errors['value'] = __( 'Choose an optimization level.', 'lw-img' );
		}

		return $errors;
	}

	/**
	 * The stored shape of a valid row.
	 *
	 * @param array<string, mixed> $row Submitted row.
	 * @return array{pattern: string, action: string, value: string}
	 */
	private static function clean( array $row ): array {
		$action = (string) $row['action'];

		return [
			'pattern' => self::pattern( $row ),
			'action'  => $action,
			'value'   => RuleSet::ACTION_LEVEL === $action ? (string) $row['value'] : '',
		];
	}

	/**
	 * The sanitized, trimmed pattern of a row.
	 *
	 * @param array<string, mixed> $row Submitted row.
	 * @return string
	 */
	private static function pattern( array $row ): string {
		$pattern = $row['pattern'] ?? '';

		return is_string( $pattern ) ? trim( sanitize_text_field( $pattern ) ) : '';
	}
}
