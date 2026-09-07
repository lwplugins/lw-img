<?php
/**
 * The combined outcome of every pattern rule that matched one file.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Plain value object filled by RuleSet::resolve().
 */
final class RuleMatch {

	public function __construct(
		public bool $excluded = false,
		public bool $keep_size = false,
		public ?string $level = null,
		public bool $keep_exif = false,
		public int $matched = 0
	) {}
}
