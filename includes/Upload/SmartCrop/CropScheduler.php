<?php
/**
 * Bridges new uploads to background smart-crop jobs.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload\SmartCrop;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;

/**
 * Holds the request-scoped registry of eligible uploads and turns them into
 * single-fire cron events once WordPress has generated the sub-sizes.
 *
 * The registry IS the "new uploads only" guarantee: restore, bulk rebuild
 * and thumbnail-regenerator plugins also fire the metadata filter, but none
 * of them run wp_handle_upload in the same request, so their requests have
 * an empty registry and nothing is ever scheduled. Structural, not a flag.
 */
final class CropScheduler {

	public const HOOK = 'lw_img_smart_crop';

	/**
	 * Files uploaded (and eligible) in this request, keyed by path.
	 *
	 * @var array<string, bool>
	 */
	private static array $uploads = [];

	/**
	 * Record an eligible upload.
	 *
	 * @param string $file_path Absolute path of the uploaded (post-conversion) file.
	 * @return void
	 */
	public static function record( string $file_path ): void {
		if ( '' !== $file_path ) {
			self::$uploads[ $file_path ] = true;
		}
	}

	/**
	 * Whether a file was recorded in this request.
	 *
	 * @param string $file_path Absolute file path.
	 * @return bool
	 */
	public static function is_recorded( string $file_path ): bool {
		return isset( self::$uploads[ $file_path ] );
	}

	/**
	 * Empty the registry (tests; the request lifecycle does this naturally).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$uploads = [];
	}
}
