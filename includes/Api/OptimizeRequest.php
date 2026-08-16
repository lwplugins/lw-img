<?php
/**
 * Request DTO for /v1/optimize.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Api;

use LightweightPlugins\Img\Options;

/**
 * Value object describing a /v1/optimize request.
 */
final class OptimizeRequest {

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
		public int $max_height = 0
	) {}

	/**
	 * Build a request for a file from the saved plugin options.
	 *
	 * @param string      $file_path        Absolute path of the file to optimize.
	 * @param string|null $convert_override Force a specific output format (e.g. 'webp'
	 *                                      for animated input, where only WebP keeps
	 *                                      the animation). Null uses the saved option.
	 * @param string|null $level_override   Force a specific optimization level
	 *                                      (used by re-optimize). Null uses the saved option.
	 * @return self
	 */
	public static function from_options( string $file_path, ?string $convert_override = null, ?string $level_override = null ): self {
		$level = $level_override ?? (string) Options::get( 'level' );
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
			(bool) Options::get( 'keep_exif' ),
			$format,
			max( 0, (int) Options::get( 'max_width' ) ),
			max( 0, (int) Options::get( 'max_height' ) )
		);
	}

	public function to_data_payload(): array {
		$data = [
			'level'     => $this->level,
			'keep_exif' => $this->keep_exif,
		];

		if ( null !== $this->convert ) {
			$data['convert'] = $this->convert;
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
			[ self::LEVEL_NORMAL, self::LEVEL_AGGRESSIVE, self::LEVEL_ULTRA ],
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
