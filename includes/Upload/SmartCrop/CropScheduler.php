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

	/**
	 * Hook the metadata filter and the cron event.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_generate_attachment_metadata', [ self::class, 'maybe_schedule' ], 20, 2 );
		add_action( self::HOOK, [ self::class, 'run' ] );
	}

	/**
	 * Schedule the crop job for a freshly uploaded attachment.
	 *
	 * Runs on wp_generate_attachment_metadata, which also fires on restore,
	 * re-optimize and thumbnail regeneration — the registry check is what
	 * limits this to genuine new uploads (see the class docblock). Also
	 * falls back to the metadata's original_image sibling, because core
	 * rewrites the attached file for scaled, rotated or format-converted
	 * images between the wp_handle_upload and this filter.
	 *
	 * @param mixed $metadata      Generated attachment metadata (passed through).
	 * @param int   $attachment_id Attachment post ID.
	 * @return mixed The metadata, always unchanged.
	 */
	public static function maybe_schedule( mixed $metadata, int $attachment_id = 0 ): mixed {
		if ( ! is_array( $metadata ) || 0 === $attachment_id ) {
			return $metadata;
		}

		if ( ! (bool) Options::get( 'auto_convert' ) || ! (bool) Options::get( 'smartcrop_enabled' ) ) {
			return $metadata;
		}

		if ( [] === (array) Options::get( 'smartcrop_sizes' ) ) {
			return $metadata;
		}

		$attached = (string) get_attached_file( $attachment_id );
		$recorded = self::is_recorded( $attached );

		if ( ! $recorded && ! empty( $metadata['original_image'] ) ) {
			$recorded = self::is_recorded( dirname( $attached ) . '/' . (string) $metadata['original_image'] );
		}

		if ( ! $recorded ) {
			return $metadata;
		}

		wp_schedule_single_event( time(), self::HOOK, [ $attachment_id ] );

		return $metadata;
	}

	/**
	 * Cron entry point: crop one attachment's selected sizes.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public static function run( int $attachment_id ): void {
		if ( ! (bool) Options::get( 'smartcrop_enabled' ) ) {
			return;
		}

		( new ThumbnailCropper() )->crop( $attachment_id );
	}
}
