<?php
/**
 * Request DTO for /v1/optimize.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Api;

/**
 * Value object describing a /v1/optimize request.
 */
final class OptimizeRequest {

	public const LEVEL_NORMAL     = 'normal';
	public const LEVEL_AGGRESSIVE = 'aggressive';
	public const LEVEL_ULTRA      = 'ultra';

	public function __construct(
		public string $file_path,
		public string $level = self::LEVEL_NORMAL,
		public bool $keep_exif = false,
		public ?string $convert = 'webp'
	) {}

	public function to_data_payload(): array {
		$data = [
			'level'     => $this->level,
			'keep_exif' => $this->keep_exif,
		];

		if ( null !== $this->convert ) {
			$data['convert'] = $this->convert;
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
}
