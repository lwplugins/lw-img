<?php
/**
 * Response DTO for /v1/optimize.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Api;

/**
 * Value object holding a parsed /v1/optimize response.
 */
final class OptimizeResult {

	public function __construct(
		public string $job_id,
		public string $status,
		public string $image_url,
		public int $original_size,
		public int $new_size,
		public float $percent,
		public string $format,
		public int $width,
		public int $height
	) {}

	public static function from_response( array $body ): self {
		$result = $body['result'] ?? [];

		return new self(
			(string) ( $body['job_id'] ?? '' ),
			(string) ( $body['status'] ?? '' ),
			(string) ( $result['image_url'] ?? '' ),
			(int) ( $result['original_size'] ?? 0 ),
			(int) ( $result['new_size'] ?? 0 ),
			(float) ( $result['percent'] ?? 0 ),
			(string) ( $result['format'] ?? '' ),
			(int) ( $result['width'] ?? 0 ),
			(int) ( $result['height'] ?? 0 )
		);
	}

	public function is_completed(): bool {
		return 'completed' === $this->status && '' !== $this->image_url;
	}
}
