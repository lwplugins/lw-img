<?php
/**
 * Request DTO for /v1/optimize.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Api;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Upload\Rules\RuleSet;

/**
 * Value object describing a /v1/optimize request.
 */
final class OptimizeRequest {

	public const LEVEL_LOSSLESS   = 'lossless';
	public const LEVEL_NORMAL     = 'normal';
	public const LEVEL_AGGRESSIVE = 'aggressive';
	public const LEVEL_ULTRA      = 'ultra';

	public const FORMAT_WEBP = 'webp';
	public const FORMAT_AVIF = 'avif';

	public function __construct(
		public string $file_path,
		public string $level = self::LEVEL_NORMAL,
		public bool $keep_exif = false,
		public ?string $convert = self::FORMAT_WEBP,
		public int $max_width = 0,
		public int $max_height = 0,
		public ?int $crop_width = null,
		public ?int $crop_height = null
	) {}

	/**
	 * Build a request for a file from the saved plugin options.
	 *
	 * @param string      $file_path        Absolute path of the file to optimize.
	 * @param string|null $convert_override Force a specific output format (e.g. 'webp'
	 *                                      for animated input, where only WebP keeps
	 *                                      the animation). Null uses the saved option.
	 * @param string|null $level_override   Force a specific level (re-optimize, CLI --level);
	 *                                      beats a level rule.
	 * @return self
	 */
	public static function from_options( string $file_path, ?string $convert_override = null, ?string $level_override = null ): self {
		$match = RuleSet::from_options()->resolve( $file_path );

		$level = $level_override ?? $match->level ?? (string) Options::get( 'level' );
		if ( ! self::valid_level( $level ) ) {
			$level = self::LEVEL_NORMAL;
		}

		$format = $convert_override ?? (string) Options::get( 'output_format' );
		if ( ! self::valid_format( $format ) ) {
			$format = self::FORMAT_WEBP;
		}

		return new self(
			$file_path,
			$level,
			(bool) Options::get( 'keep_exif' ) || $match->keep_exif,
			$format,
			$match->keep_size ? 0 : max( 0, (int) Options::get( 'max_width' ) ),
			$match->keep_size ? 0 : max( 0, (int) Options::get( 'max_height' ) )
		);
	}

	/**
	 * `from_options()` plus the `lw_img_optimize_request_args` filter — the
	 * single factory every conversion path (upload, bulk, row action) uses.
	 *
	 * @param string      $file_path        Absolute path of the file to optimize.
	 * @param string|null $convert_override Force an output format (null = saved option).
	 * @param string|null $level_override   Force a level (null = rule or saved option).
	 * @return self
	 */
	public static function filtered( string $file_path, ?string $convert_override = null, ?string $level_override = null ): self {
		$request = self::from_options( $file_path, $convert_override, $level_override );
		$args    = (array) apply_filters( 'lw_img_optimize_request_args', $request->to_data_payload(), $file_path );

		return new self(
			$file_path,
			(string) ( $args['level'] ?? $request->level ),
			(bool) ( $args['keep_exif'] ?? $request->keep_exif ),
			(string) ( $args['convert'] ?? $request->convert ),
			(int) ( $args['max_width'] ?? $request->max_width ),
			(int) ( $args['max_height'] ?? $request->max_height )
		);
	}

	/**
	 * Build a subject-aware crop request for one thumbnail size.
	 *
	 * The main (already converted) file is sent with the thumbnail's exact
	 * target dimensions; the API places the crop window over the subject.
	 * $convert is passed explicitly even though the worker preserves the
	 * input format since 2026-08-18 — belt over that fix.
	 *
	 * @param string $file_path Absolute path of the MAIN file.
	 * @param int    $width     Target width (the generated sub-size's, not the registered one).
	 * @param int    $height    Target height.
	 * @param string $convert   Output format matching the main file (jpeg|png|webp|avif).
	 * @param string $level     Optimization level.
	 * @param bool   $keep_exif Whether to keep EXIF.
	 * @return self
	 */
	public static function for_smart_crop( string $file_path, int $width, int $height, string $convert, string $level, bool $keep_exif ): self {
		if ( ! self::valid_level( $level ) ) {
			$level = self::LEVEL_NORMAL;
		}

		return new self( $file_path, $level, $keep_exif, $convert, 0, 0, $width, $height );
	}

	public function to_data_payload(): array {
		$data = [
			'level'     => $this->level,
			'keep_exif' => $this->keep_exif,
		];

		if ( null !== $this->convert ) {
			$data['convert'] = $this->convert;
		}

		if ( null !== $this->crop_width && null !== $this->crop_height ) {
			$data['resize'] = [
				'width'     => $this->crop_width,
				'height'    => $this->crop_height,
				'smartcrop' => true,
			];

			return $data;
		}

		if ( $this->max_width > 0 ) {
			$data['max_width'] = $this->max_width;
		}

		if ( $this->max_height > 0 ) {
			$data['max_height'] = $this->max_height;
		}

		return $data;
	}

	public static function valid_level( string $level ): bool {
		return in_array(
			$level,
			[ self::LEVEL_LOSSLESS, self::LEVEL_NORMAL, self::LEVEL_AGGRESSIVE, self::LEVEL_ULTRA ],
			true
		);
	}

	/**
	 * Whether the output format is one the plugin offers.
	 *
	 * @param string $format Candidate output format.
	 * @return bool
	 */
	public static function valid_format( string $format ): bool {
		return in_array( $format, [ self::FORMAT_WEBP, self::FORMAT_AVIF ], true );
	}
}
