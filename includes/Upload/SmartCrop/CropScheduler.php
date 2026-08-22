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
	 * Postmeta key marking an attachment as awaiting the finalize pass.
	 *
	 * Written at the 'create' pass when sizes are still empty (client-side
	 * upload); consumed and cleared at the 'update' pass once the browser
	 * has sideloaded the sizes.
	 */
	public const PENDING_META = '_lw_img_crop_pending';

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
		add_filter( 'wp_generate_attachment_metadata', [ self::class, 'maybe_schedule' ], 20, 3 );
		add_action( self::HOOK, [ self::class, 'run' ] );
	}

	/**
	 * Schedule the crop job for a freshly uploaded attachment.
	 *
	 * Runs on wp_generate_attachment_metadata, which also fires on restore,
	 * re-optimize and thumbnail regeneration — the registry check is what
	 * limits the 'create' pass to genuine new uploads (see the class
	 * docblock). WP 7.1's client-side finalize flow calls this filter
	 * twice: once at 'create' with empty sizes (the browser has not
	 * sideloaded them yet), and once at 'update' once the sizes exist. The
	 * PENDING_META marker carries the "eligible" decision across the gap
	 * between those two requests.
	 *
	 * @param mixed  $metadata      Generated attachment metadata (passed through).
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $context       Either 'create' or 'update'.
	 * @return mixed The metadata, always unchanged.
	 */
	public static function maybe_schedule( mixed $metadata, int $attachment_id = 0, string $context = 'create' ): mixed {
		if ( ! is_array( $metadata ) || 0 === $attachment_id ) {
			return $metadata;
		}

		if ( ! (bool) Options::get( 'auto_convert' ) || ! (bool) Options::get( 'smartcrop_enabled' ) ) {
			return $metadata;
		}

		if ( [] === (array) Options::get( 'smartcrop_sizes' ) ) {
			return $metadata;
		}

		if ( 'update' === $context ) {
			// The finalize pass of a client-side upload: the sizes exist now,
			// and the marker written at 'create' proves this attachment came
			// through a genuine upload in an earlier request.
			if ( get_post_meta( $attachment_id, self::PENDING_META, true ) ) {
				delete_post_meta( $attachment_id, self::PENDING_META );
				wp_schedule_single_event( time(), self::HOOK, [ $attachment_id ] );
			}

			return $metadata;
		}

		if ( ! self::is_recorded_upload( $attachment_id, $metadata ) ) {
			return $metadata;
		}

		if ( [] === ( $metadata['sizes'] ?? [] ) ) {
			// Client-side upload: the browser has not sideloaded the sizes
			// yet. Scheduling now would race them — mark instead, and the
			// finalize pass above turns the marker into the real event.
			update_post_meta( $attachment_id, self::PENDING_META, 1 );

			return $metadata;
		}

		wp_schedule_single_event( time(), self::HOOK, [ $attachment_id ] );

		return $metadata;
	}

	/**
	 * Whether an attachment's file was recorded as an eligible upload in
	 * this request.
	 *
	 * Also falls back to the metadata's original_image sibling, because
	 * core rewrites the attached file for scaled, rotated or
	 * format-converted images between wp_handle_upload and this filter.
	 *
	 * @param int   $attachment_id Attachment post ID.
	 * @param array $metadata      Generated attachment metadata.
	 * @return bool
	 */
	private static function is_recorded_upload( int $attachment_id, array $metadata ): bool {
		$attached = (string) get_attached_file( $attachment_id );
		$recorded = self::is_recorded( $attached );

		if ( ! $recorded && ! empty( $metadata['original_image'] ) ) {
			$recorded = self::is_recorded( dirname( $attached ) . '/' . (string) $metadata['original_image'] );
		}

		return $recorded;
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
