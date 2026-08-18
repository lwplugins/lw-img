<?php
/**
 * Hooks `wp_handle_upload` and swaps the uploaded file with an optimized WebP.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\ApiException;
use LightweightPlugins\Img\Api\Client;
use LightweightPlugins\Img\Api\OptimizeRequest;
use LightweightPlugins\Img\Logger;
use Throwable;

/**
 * Intercepts wp_handle_upload and swaps uploads with the optimized WebP.
 */
final class UploadInterceptor {

	/**
	 * Decides whether an upload should be converted.
	 *
	 * @var ConvertibleDetector
	 */
	private ConvertibleDetector $detector;

	/**
	 * Replaces the uploaded file with the optimized result.
	 *
	 * @var FileSwapper
	 */
	private FileSwapper $swapper;

	/**
	 * Persists optimization stats to attachment meta.
	 *
	 * @var AttachmentMetaWriter
	 */
	private AttachmentMetaWriter $meta_writer;

	public function __construct(
		?ConvertibleDetector $detector = null,
		?FileSwapper $swapper = null,
		?AttachmentMetaWriter $meta_writer = null
	) {
		$this->detector    = $detector ?? new ConvertibleDetector();
		$this->swapper     = $swapper ?? new FileSwapper();
		$this->meta_writer = $meta_writer ?? new AttachmentMetaWriter();

		add_filter( 'wp_handle_upload', [ $this, 'maybe_convert' ], 10, 1 );
	}

	public function maybe_convert( array $upload ): array {
		$file = (string) ( $upload['file'] ?? '' );
		$type = (string) ( $upload['type'] ?? '' );

		if ( ! $this->detector->should_convert( $file, $type ) ) {
			return $upload;
		}

		try {
			$client  = new Client();
			$request = OptimizeRequest::from_options( $file, $this->animation_safe_override( $file, $type ) );

			$args    = (array) apply_filters( 'lw_img_optimize_request_args', $request->to_data_payload(), $file );
			$request = new OptimizeRequest(
				$file,
				(string) ( $args['level'] ?? $request->level ),
				(bool) ( $args['keep_exif'] ?? $request->keep_exif ),
				(string) ( $args['convert'] ?? $request->convert ),
				(int) ( $args['max_width'] ?? $request->max_width ),
				(int) ( $args['max_height'] ?? $request->max_height )
			);

			$result = $client->optimize( $request );

			if ( ! $result->is_smaller() ) {
				do_action( 'lw_img_upload_skipped', $file, 'optimized result not smaller' );
				return $upload;
			}

			$swapped = $this->swapper->swap( $file, (string) ( $upload['url'] ?? '' ), $result );

			$backup_rel = $swapped['lw_img_backup'] ?? null;
			unset( $swapped['lw_img_backup'] );

			$this->meta_writer->stash(
				$swapped['file'],
				$result,
				is_string( $backup_rel ) ? $backup_rel : null,
				$request->level,
				$request->keep_exif
			);

			Logger::debug(
				'upload converted',
				[
					'from'    => $file,
					'to'      => $swapped['file'],
					'percent' => $result->percent,
				]
			);

			do_action(
				'lw_img_upload_converted',
				$file,
				$swapped['file'],
				[
					'original_size' => $result->original_size,
					'new_size'      => $result->new_size,
					'percent'       => $result->percent,
					'job_id'        => $result->job_id,
					'mime'          => $type,
					'mime_to'       => (string) ( $swapped['type'] ?? '' ),
				]
			);

			return array_merge( $upload, $swapped );
		} catch ( ApiException $e ) {
			Logger::error(
				'upload api error',
				[
					'code' => $e->get_error_code(),
					'msg'  => $e->getMessage(),
				]
			);
			do_action( 'lw_img_upload_failed', $file, $e->getMessage() );
			return $upload;
		} catch ( Throwable $e ) {
			Logger::error( 'upload unexpected error', [ 'msg' => $e->getMessage() ] );
			do_action( 'lw_img_upload_failed', $file, $e->getMessage() );
			return $upload;
		}
	}

	/**
	 * Force WebP output for animated GIFs.
	 *
	 * Only WebP is verified to preserve frames and timing — an AVIF target
	 * would silently flatten the animation to its first frame.
	 *
	 * @param string $file Absolute file path.
	 * @param string $mime File mime type.
	 * @return string|null 'webp' for animated GIF input, null otherwise.
	 */
	private function animation_safe_override( string $file, string $mime ): ?string {
		return ( 'image/gif' === $mime && AnimatedGifProbe::is_animated( $file ) )
			? OptimizeRequest::FORMAT_WEBP
			: null;
	}
}
