<?php
/**
 * Replaces the locally-uploaded file with the optimized HelloImg result.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\OptimizeResult;
use LightweightPlugins\Img\Backup\BackupStore;
use LightweightPlugins\Img\Logger;
use LightweightPlugins\Img\Options;

/**
 * Downloads the optimized image and replaces the uploaded file on disk.
 */
final class FileSwapper {

	/**
	 * Hard ceiling on the optimized file we are willing to read into memory.
	 */
	private const MAX_DOWNLOAD_BYTES = 67108864;

	/**
	 * Stores originals before they are replaced.
	 *
	 * @var BackupStore
	 */
	private BackupStore $backups;

	public function __construct( ?BackupStore $backups = null ) {
		$this->backups = $backups ?? new BackupStore();
	}

	public function swap( string $original_path, string $original_url, OptimizeResult $result ): array {
		$bytes = $this->download( $result->image_url );

		$new_path = $this->target_path( $original_path, $result->format );
		$new_url  = $this->url_for( $original_url, $new_path );

		$backup_enabled = (bool) Options::get( 'backup_enabled' );
		$backup_rel     = $this->backup_original( $original_path, $new_path, $backup_enabled );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing next to the upload in the uploads dir; WP_Filesystem adds nothing here.
		if ( false === file_put_contents( $new_path, $bytes ) ) {
			if ( null !== $backup_rel ) {
				$this->backups->delete( $backup_rel );
			}
			throw new \RuntimeException( esc_html( 'Failed to write optimized file: ' . $new_path ) );
		}

		$backup_ok = null !== $backup_rel || ! $backup_enabled;
		if ( $new_path !== $original_path && file_exists( $original_path ) && $backup_ok ) {
			wp_delete_file( $original_path );
		}

		return [
			'file'          => $new_path,
			'url'           => $new_url,
			'type'          => $this->mime_for_format( $result->format ),
			'lw_img_backup' => $backup_rel,
		];
	}

	/**
	 * Back up the original before it is deleted or overwritten.
	 *
	 * When backup is enabled but fails, the original is never destroyed:
	 * an in-place overwrite is aborted, and for a different target path the
	 * original file is simply left on disk.
	 *
	 * @param string $original_path Absolute path of the uploaded original.
	 * @param string $new_path      Absolute path the optimized file will be written to.
	 * @param bool   $backup_enabled Whether backups are enabled.
	 * @return string|null Backup-relative path, or null when disabled or failed.
	 * @throws \RuntimeException When the backup failed and writing would overwrite the original.
	 */
	private function backup_original( string $original_path, string $new_path, bool $backup_enabled ): ?string {
		if ( ! $backup_enabled || ! file_exists( $original_path ) ) {
			return null;
		}

		$backup_rel = $this->backups->store( $original_path );

		if ( null === $backup_rel ) {
			Logger::error( 'backup failed; original kept', [ 'file' => $original_path ] );
			if ( $new_path === $original_path ) {
				throw new \RuntimeException( esc_html( 'Backup failed — not overwriting the original in place: ' . $original_path ) );
			}
		}

		return $backup_rel;
	}

	/**
	 * Fetch the optimized file.
	 *
	 * The response is bounded and its bytes are checked before anything is
	 * written: this download replaces a file inside the uploads directory
	 * under an extension we pick, so a response that is not an image must
	 * never reach the disk. Throwing here leaves the original untouched —
	 * nothing has been backed up, deleted, or overwritten yet.
	 *
	 * @param string $url Optimized file URL from the API response.
	 * @return string Raw image bytes.
	 * @throws \RuntimeException On a transport error, a non-200, an empty
	 *                           body, or bytes that are not an image.
	 */
	private function download( string $url ): string {
		$response = wp_safe_remote_get(
			$url,
			[
				'timeout'             => 30,
				// A sanity bound, not a business rule: uploads are already
				// capped by max_filesize_mb (1-10 MB) and the optimized file
				// is meant to be smaller than its input.
				'limit_response_size' => self::MAX_DOWNLOAD_BYTES,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( esc_html( 'Download failed: ' . $response->get_error_message() ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			throw new \RuntimeException( esc_html( 'Download HTTP ' . $status ) );
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			throw new \RuntimeException( 'Empty response body from CDN' );
		}

		if ( ! ImageBytes::is_image( $body ) ) {
			throw new \RuntimeException( 'Optimized download is not an image — original kept' );
		}

		return $body;
	}

	/**
	 * Absolute target path for the optimized file.
	 *
	 * The core uniqueness guarantee only covers the *uploaded* name, so
	 * photo.jpg does not collide with an existing photo.webp. Without
	 * wp_unique_filename() here, converting photo.jpg would overwrite an
	 * unrelated attachment's photo.webp. An in-place conversion
	 * (webp -> webp, same path) must stay in place and is exempt.
	 *
	 * @param string $original_path Absolute path of the uploaded file.
	 * @param string $format        Optimized output format.
	 * @return string
	 */
	private function target_path( string $original_path, string $format ): string {
		$ext       = $this->extension_for_format( $format );
		$dir       = dirname( $original_path );
		$candidate = pathinfo( $original_path, PATHINFO_FILENAME ) . '.' . $ext;

		if ( $dir . '/' . $candidate !== $original_path ) {
			$candidate = wp_unique_filename( $dir, $candidate );
		}

		return $dir . '/' . $candidate;
	}

	/**
	 * Rewrite the upload URL to the optimized file's final name, so the URL
	 * always matches the (possibly de-duplicated) path.
	 *
	 * @param string $original_url Original upload URL.
	 * @param string $new_path     Final absolute path of the optimized file.
	 * @return string
	 */
	private function url_for( string $original_url, string $new_path ): string {
		$path = wp_parse_url( $original_url, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return $original_url;
		}

		$replaced = dirname( $path ) . '/' . wp_basename( $new_path );

		return str_replace( $path, $replaced, $original_url );
	}

	private function extension_for_format( string $format ): string {
		return match ( strtolower( $format ) ) {
			'jpeg', 'jpg' => 'jpg',
			'png'         => 'png',
			'avif'        => 'avif',
			'gif'         => 'gif',
			default       => 'webp',
		};
	}

	private function mime_for_format( string $format ): string {
		return match ( strtolower( $format ) ) {
			'jpeg', 'jpg' => 'image/jpeg',
			'png'         => 'image/png',
			'avif'        => 'image/avif',
			'gif'         => 'image/gif',
			default       => 'image/webp',
		};
	}
}
