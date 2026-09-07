<?php
/**
 * Pattern rules: a wildcard pattern plus what to do with matching files.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload\Rules;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\OptimizeRequest;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Upload\ExclusionMatcher;

/**
 * Resolves the `pattern_rules` option for one file. Every matching rule
 * applies; an exclude match wins outright; when two level rules match, the
 * first one in list order wins; keep_exif can only turn EXIF on.
 */
final class RuleSet {

	public const ACTION_EXCLUDE   = 'exclude';
	public const ACTION_KEEP_SIZE = 'keep_size';
	public const ACTION_LEVEL     = 'level';
	public const ACTION_KEEP_EXIF = 'keep_exif';

	public const ACTIONS = [
		self::ACTION_EXCLUDE,
		self::ACTION_KEEP_SIZE,
		self::ACTION_LEVEL,
		self::ACTION_KEEP_EXIF,
	];

	/**
	 * Rules as stored: list of {pattern, action, value}.
	 *
	 * @var array<int, mixed>
	 */
	private array $rules;

	/**
	 * Wildcard matcher shared with the legacy exclusion logic.
	 *
	 * @var ExclusionMatcher
	 */
	private ExclusionMatcher $matcher;

	/**
	 * @param array<int, mixed>     $rules   Stored rules.
	 * @param ExclusionMatcher|null $matcher Pattern matcher.
	 */
	public function __construct( array $rules, ?ExclusionMatcher $matcher = null ) {
		$this->rules   = array_values( $rules );
		$this->matcher = $matcher ?? new ExclusionMatcher();
	}

	public static function from_options(): self {
		return new self( (array) Options::get( 'pattern_rules' ) );
	}

	public function count(): int {
		return count( $this->rules );
	}

	/**
	 * Combine every matching rule for a file.
	 *
	 * @param string $file_path Absolute file path.
	 * @return RuleMatch
	 */
	public function resolve( string $file_path ): RuleMatch {
		$match = new RuleMatch();

		foreach ( $this->rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$pattern = trim( (string) ( $rule['pattern'] ?? '' ) );
			$action  = (string) ( $rule['action'] ?? '' );

			if ( '' === $pattern || ! in_array( $action, self::ACTIONS, true ) ) {
				continue;
			}

			if ( ! $this->matcher->matches( $file_path, [ $pattern ] ) ) {
				continue;
			}

			++$match->matched;
			$this->apply( $match, $action, (string) ( $rule['value'] ?? '' ) );
		}

		return $match;
	}

	private function apply( RuleMatch $match, string $action, string $value ): void {
		switch ( $action ) {
			case self::ACTION_EXCLUDE:
				$match->excluded = true;
				break;
			case self::ACTION_KEEP_SIZE:
				$match->keep_size = true;
				break;
			case self::ACTION_KEEP_EXIF:
				$match->keep_exif = true;
				break;
			case self::ACTION_LEVEL:
				if ( null === $match->level && OptimizeRequest::valid_level( $value ) ) {
					$match->level = $value;
				}
				break;
		}
	}
}
