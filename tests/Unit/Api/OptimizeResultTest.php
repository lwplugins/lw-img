<?php
/**
 * Tests for the OptimizeResult value object.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Api;

use LightweightPlugins\Img\Api\OptimizeResult;
use LightweightPlugins\Img\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Img\Api\OptimizeResult
 */
final class OptimizeResultTest extends MonkeyTestCase {

	public function test_from_response_maps_nested_result_fields(): void {
		$result = OptimizeResult::from_response(
			[
				'job_id' => 'job_123',
				'status' => 'completed',
				'result' => [
					'image_url'     => 'https://cdn.example.com/a.webp',
					'original_size' => 1000,
					'new_size'      => 400,
					'percent'       => 60.0,
					'format'        => 'webp',
					'width'         => 800,
					'height'        => 600,
				],
			]
		);

		$this->assertSame( 'job_123', $result->job_id );
		$this->assertSame( 'completed', $result->status );
		$this->assertSame( 'https://cdn.example.com/a.webp', $result->image_url );
		$this->assertSame( 1000, $result->original_size );
		$this->assertSame( 400, $result->new_size );
		$this->assertSame( 60.0, $result->percent );
		$this->assertSame( 'webp', $result->format );
		$this->assertSame( 800, $result->width );
		$this->assertSame( 600, $result->height );
	}

	public function test_from_response_defaults_missing_fields_to_empty_values(): void {
		$result = OptimizeResult::from_response( [] );

		$this->assertSame( '', $result->job_id );
		$this->assertSame( '', $result->status );
		$this->assertSame( '', $result->image_url );
		$this->assertSame( 0, $result->original_size );
		$this->assertSame( 0.0, $result->percent );
	}

	/**
	 * @dataProvider provide_completion_states
	 */
	public function test_is_completed_requires_completed_status_and_image_url( string $status, string $image_url, bool $expected ): void {
		$result = OptimizeResult::from_response(
			[
				'status' => $status,
				'result' => [ 'image_url' => $image_url ],
			]
		);

		$this->assertSame( $expected, $result->is_completed() );
	}

	/**
	 * Completion state cases.
	 *
	 * @return array<string, array{string, string, bool}>
	 */
	public static function provide_completion_states(): array {
		return [
			'completed with url' => [ 'completed', 'https://cdn.example.com/a.webp', true ],
			'completed, no url'  => [ 'completed', '', false ],
			'still processing'   => [ 'processing', 'https://cdn.example.com/a.webp', false ],
			'failed'             => [ 'failed', '', false ],
		];
	}
}
