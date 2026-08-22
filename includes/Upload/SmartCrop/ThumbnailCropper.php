<?php
/**
 * Executes the smart-crop jobs for one attachment.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload\SmartCrop;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\ApiException;
use LightweightPlugins\Img\Api\Client;
use LightweightPlugins\Img\Api\OptimizeRequest;
use LightweightPlugins\Img\Logger;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Upload\ImageBytes;
use Throwable;

/**
 * Sends the MAIN (already converted) file once per selected size with the
 * size's exact dimensions and overwrites the generated thumbnail file in
 * place — same name, same dimensions, only the bytes change, which is what
 * keeps URL rewriting, redirects and backups entirely out of this feature.
 *
 * There is deliberately NO "result not smaller" guard here: the
 * subject-aware crop is 12-16% larger than the centre crop by design (it
 * keeps the detailed part of the picture). The only gates on the downloaded
 * bytes are "is it an image" and "is it the size we asked for".
 */
final class ThumbnailCropper {

	/**
	 * Ceiling on a downloaded crop, mirroring FileSwapper's bound.
	 */
	private const MAX_DOWNLOAD_BYTES = 67108864;

	/**
	 * Crop every selected size of one attachment.
	 *
	 * Failures are per-size: a failed size keeps its WordPress thumbnail
	 * and the loop continues — except a quota error, which stops the
	 * remaining sizes so attempts are not burned. The upload finished long
	 * ago; nothing here can affect it.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public function crop( int $attachment_id ): void {
		$main = (string) get_attached_file( $attachment_id );
		if ( '' === $main || ! file_exists( $main ) || '' === (string) Options::get( 'api_key' ) ) {
			return;
		}

		$convert = self::convert_target_for( $main );
		if ( null === $convert ) {
			Logger::debug( 'smart crop: unsupported main format', [ 'file' => $main ] );
			return;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) ) {
			return;
		}

		$jobs = SizeCatalog::jobs(
			array_map( 'strval', (array) Options::get( 'smartcrop_sizes' ) ),
			wp_get_registered_image_subsizes(),
			$metadata
		);

		$level     = (string) Options::get( 'level' );
		$keep_exif = (bool) Options::get( 'keep_exif' );
		$directory = dirname( $main );
		$updated   = false;

		foreach ( $jobs as $job ) {
			try {
				$bytes = $this->cropped_bytes( $main, $job, $convert, $level, $keep_exif );
				$this->swap_file( $directory . '/' . $job['file'], $bytes );

				if ( isset( $metadata['sizes'][ $job['name'] ]['filesize'] ) ) {
					$metadata['sizes'][ $job['name'] ]['filesize'] = strlen( $bytes );
				}
				$updated = true;
			} catch ( ApiException $e ) {
				do_action( 'lw_img_upload_failed', $job['file'], 'smart crop: ' . $e->getMessage() );
				if ( $e->is_quota() ) {
					break;
				}
			} catch ( Throwable $e ) {
				do_action( 'lw_img_upload_failed', $job['file'], 'smart crop: ' . $e->getMessage() );
			}
		}

		if ( $updated ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
			Logger::debug( 'smart crop done', [ 'attachment' => $attachment_id ] );
		}
	}

	/**
	 * The convert target matching the main file's format.
	 *
	 * Explicit even though the worker preserves the input format since
	 * 2026-08-18 — belt over that fix. Returns null for formats the API's
	 * conversion path cannot encode (gif has no encoder there, and a crop
	 * would flatten its animation anyway).
	 *
	 * @param string $file_path Absolute path of the main file.
	 * @return string|null jpeg|png|webp|avif, or null when unsupported.
	 */
	public static function convert_target_for( string $file_path ): ?string {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		return match ( $extension ) {
			'jpg', 'jpeg' => 'jpeg',
			'png'         => 'png',
			'webp'        => 'webp',
			'avif'        => 'avif',
			default       => null,
		};
	}

	/**
	 * Run one crop job through the API and return the validated bytes.
	 *
	 * @param string                                                     $main      Main file path.
	 * @param array{name: string, width: int, height: int, file: string} $job       Crop job.
	 * @param string                                                     $convert   Output format.
	 * @param string                                                     $level     Optimization level.
	 * @param bool                                                       $keep_exif Whether to keep EXIF.
	 * @return string Raw image bytes at exactly the requested dimensions.
	 * @throws ApiException|\RuntimeException On any failure worth logging.
	 */
	private function cropped_bytes( string $main, array $job, string $convert, string $level, bool $keep_exif ): string {
		$request = OptimizeRequest::for_smart_crop( $main, $job['width'], $job['height'], $convert, $level, $keep_exif );
		$result  = ( new Client() )->optimize( $request );

		if ( $result->width !== $job['width'] || $result->height !== $job['height'] ) {
			throw new \RuntimeException(
				esc_html( sprintf( 'crop came back %dx%d, wanted %dx%d', $result->width, $result->height, $job['width'], $job['height'] ) )
			);
		}

		$response = wp_safe_remote_get(
			$result->image_url,
			[
				'timeout'             => 30,
				'limit_response_size' => self::MAX_DOWNLOAD_BYTES,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( esc_html( 'download failed: ' . $response->get_error_message() ) );
		}

		$bytes = (string) wp_remote_retrieve_body( $response );
		if ( '' === $bytes || ! ImageBytes::is_image( $bytes ) ) {
			throw new \RuntimeException( 'downloaded crop is not an image' );
		}

		return $bytes;
	}

	/**
	 * Replace a thumbnail's bytes atomically (tmp + rename), so a failed
	 * write can never leave a truncated file where a good thumbnail was.
	 *
	 * @param string $path  Absolute path of the sub-size file.
	 * @param string $bytes New file contents.
	 * @return void
	 * @throws \RuntimeException When the write or rename fails.
	 */
	private function swap_file( string $path, string $bytes ): void {
		$tmp = $path . '.lwtmp';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing inside the uploads dir; WP_Filesystem adds nothing here.
		$written = file_put_contents( $tmp, $bytes );

		if ( false === $written || strlen( $bytes ) !== $written ) {
			wp_delete_file( $tmp );
			throw new \RuntimeException( 'could not write the cropped file' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic replace within the uploads dir.
		if ( ! rename( $tmp, $path ) ) {
			wp_delete_file( $tmp );
			throw new \RuntimeException( 'could not move the cropped file into place' );
		}
	}
}
