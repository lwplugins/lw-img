<?php
/**
 * Result of parsing a batch of submitted settings.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Valid values, per-field errors, and the submitted keys that are not
 * settings at all.
 */
final class InputReport {

	/**
	 * Parsed values of the valid fields.
	 *
	 * @var array<string, mixed>
	 */
	private array $values;

	/**
	 * Messages per invalid field (setting key or sub-field path).
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $errors;

	/**
	 * Submitted keys that are not settings.
	 *
	 * @var array<int, string>
	 */
	private array $unknown;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed>              $values  Parsed values.
	 * @param array<string, array<int, string>> $errors  Messages per field.
	 * @param array<int, string>                $unknown Unknown keys.
	 */
	public function __construct( array $values, array $errors, array $unknown ) {
		$this->values  = $values;
		$this->errors  = $errors;
		$this->unknown = $unknown;
	}

	/**
	 * Parsed values of the valid fields.
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array {
		return $this->values;
	}

	/**
	 * Messages per invalid field.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Submitted keys that are not settings.
	 *
	 * @return array<int, string>
	 */
	public function unknown(): array {
		return $this->unknown;
	}

	/**
	 * Whether any field was refused.
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		return [] !== $this->errors;
	}
}
