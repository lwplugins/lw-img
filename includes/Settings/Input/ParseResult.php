<?php
/**
 * Outcome of parsing one raw setting value.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Either a clean option value, or the reasons it was refused: messages for
 * the setting as a whole plus, for list settings, messages per sub-field
 * (e.g. "rules.3.pattern").
 */
final class ParseResult {

	/**
	 * Parsed value (null when invalid).
	 *
	 * @var mixed
	 */
	private mixed $value;

	/**
	 * Messages for the setting as a whole.
	 *
	 * @var array<int, string>
	 */
	private array $errors;

	/**
	 * Messages per sub-field path.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $field_errors;

	/**
	 * Constructor.
	 *
	 * @param mixed                             $value        Parsed value.
	 * @param array<int, string>                $errors       Messages for the setting.
	 * @param array<string, array<int, string>> $field_errors Messages per sub-field.
	 */
	private function __construct( mixed $value, array $errors, array $field_errors = [] ) {
		$this->value        = $value;
		$this->errors       = $errors;
		$this->field_errors = $field_errors;
	}

	/**
	 * A valid value.
	 *
	 * @param mixed $value Parsed value.
	 * @return self
	 */
	public static function ok( mixed $value ): self {
		return new self( $value, [] );
	}

	/**
	 * A refused value.
	 *
	 * @param array<int, string>                $errors       Messages for the setting (at least one).
	 * @param array<string, array<int, string>> $field_errors Messages per sub-field.
	 * @return self
	 */
	public static function fail( array $errors, array $field_errors = [] ): self {
		return new self( null, array_values( $errors ), $field_errors );
	}

	/**
	 * The parsed value, or null when invalid.
	 *
	 * @return mixed
	 */
	public function value(): mixed {
		return $this->value;
	}

	/**
	 * Messages for the setting as a whole; empty when valid.
	 *
	 * @return array<int, string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Messages per sub-field path.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function field_errors(): array {
		return $this->field_errors;
	}

	/**
	 * Whether the value was accepted.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return [] === $this->errors;
	}
}
