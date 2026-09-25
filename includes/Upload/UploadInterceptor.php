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
use LightweightPlugins\Img\Upload\SmartCrop\CropScheduler;
use LightweightPlugins\Img\Upload\SubsizeSideload;
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
		if ( SubsizeSideload::is_current_request() ) {
			return $upload;
		}

		$file = (string) ( $upload['file'] ?? '' );
		$type = (string) ( $upload['type'] ?? '' );

		if ( $this->detector->smart_crop_eligible( $file, $type ) ) {
			CropScheduler::record( $file );
		}

		if ( ! $this->detector->should_convert( $file, $type ) ) {
			return $upload;
		}

		try {
			$client  = new Client();
			$request = OptimizeRequest::filtered( $file, $this->animation_safe_override( $file, $type ) );

			$result = $client->optimize( $request );

			if ( ! $result->is_smaller() ) {
				/**
				 * Fires when a file is not converted. Despite the name, it also
				 * fires for bulk / on-demand runs of existing attachments.
				 *
				 * $context keys by call site:
				 * - upload, result not smaller: original_size, new_size (bytes).
				 * - bulk / on-demand, result not smaller: attachment_id, original_size, new_size.
				 * - skip rules (ConvertibleDetector): attachment_id on bulk / on-demand
				 *   runs, empty on upload.
				 *
				 * @since 1.0.0
				 *
				 * @param string               $file_path Absolute path of the file that was not converted.
				 * @param string               $reason    Machine-readable skip reason (e.g. 'excluded by rule').
				 * @param array<string, mixed> $context   Always an array, possibly empty; attachment_id is optional.
				 */
				do_action(
					'lw_img_upload_skipped',
					$file,
					'optimized result not smaller',
					[
						'original_size' => $result->original_size,
						'new_size'      => $result->new_size,
					]
				);
				return $upload;
			}

			$swapped = $this->swapper->swap( $file, (string) ( $upload['url'] ?? '' ), $result );

			CropScheduler::record( (string) $swapped['file'] );

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

			/**
			 * Fires after a file was converted and swapped in. Despite the name,
			 * it also fires for bulk / on-demand runs of existing attachments.
			 *
			 * $context keys: original_size, new_size (bytes), percent (saving),
			 * job_id (HelloImg job), mime, mime_to; plus attachment_id on bulk /
			 * on-demand runs. On upload no attachment exists yet, so
			 * attachment_id is absent there.
			 *
			 * @since 1.0.0
			 * @since 2.0.1 $context carries attachment_id on bulk / on-demand runs.
			 *
			 * @param string               $original_path Absolute path of the original file.
			 * @param string               $new_path      Absolute path of the converted file.
			 * @param array<string, mixed> $context       Conversion result, see above.
			 */
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
			/**
			 * Fires when a conversion or smart crop fails. Despite the name, it
			 * also fires for bulk / on-demand runs and smart crop.
			 *
			 * $context is empty on upload (no attachment exists yet) and holds
			 * attachment_id everywhere else. Smart-crop failures pass the size
			 * file's path and prefix $error with 'smart crop: '.
			 *
			 * @since 1.0.0
			 * @since 2.0.1 The bulk "API key missing" halt passes the attachment's
			 *              file path instead of the literal 'bulk run', and smart
			 *              crop passes the size file's absolute path, not its basename.
			 *
			 * @param string               $file_path Absolute path of the file that failed.
			 * @param string               $error     Error message.
			 * @param array<string, mixed> $context   Always an array, possibly empty; attachment_id is optional.
			 */
			do_action( 'lw_img_upload_failed', $file, $e->getMessage(), [] );
			return $upload;
		} catch ( Throwable $e ) {
			Logger::error( 'upload unexpected error', [ 'msg' => $e->getMessage() ] );
			/** This action is documented in includes/Upload/UploadInterceptor.php */
			do_action( 'lw_img_upload_failed', $file, $e->getMessage(), [] );
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
