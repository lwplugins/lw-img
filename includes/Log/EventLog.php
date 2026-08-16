<?php
/**
 * Per-upload event log (ring buffer, last 200 entries).
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Log;

use LightweightPlugins\Img\Options;

/**
 * Records upload events in a capped wp_options ring buffer.
 */
final class EventLog {

	public const OPTION_NAME = 'lw_img_log';

	public const MAX_ENTRIES = 200;

	public const STATUS_CONVERTED = 'converted';
	public const STATUS_SKIPPED   = 'skipped';
	public const STATUS_FAILED    = 'failed';

	public static function register(): void {
		add_action( 'lw_img_upload_converted', [ self::class, 'on_converted' ], 10, 3 );
		add_action( 'lw_img_upload_skipped', [ self::class, 'on_skipped' ], 10, 2 );
		add_action( 'lw_img_upload_failed', [ self::class, 'on_failed' ], 10, 2 );
	}

	public static function on_converted( string $original_path, string $new_path, array $result ): void {
		if ( ! self::enabled() ) {
			return;
		}

		self::record(
			[
				'status'   => self::STATUS_CONVERTED,
				'file'     => basename( $original_path ),
				'mime'     => (string) ( $result['mime'] ?? self::guess_mime( $original_path ) ?? '' ),
				'mime_to'  => (string) ( $result['mime_to'] ?? self::guess_mime( $new_path ) ?? 'image/webp' ),
				'size_in'  => (int) ( $result['original_size'] ?? 0 ),
				'size_out' => (int) ( $result['new_size'] ?? 0 ),
				'percent'  => (float) ( $result['percent'] ?? 0 ),
				'job_id'   => (string) ( $result['job_id'] ?? '' ),
			]
		);
	}

	public static function on_skipped( string $file_path, string $reason ): void {
		if ( ! self::enabled() ) {
			return;
		}

		self::record(
			[
				'status' => self::STATUS_SKIPPED,
				'file'   => basename( $file_path ),
				'mime'   => self::guess_mime( $file_path ) ?? '',
				'reason' => $reason,
			]
		);
	}

	public static function on_failed( string $file_path, string $error ): void {
		if ( ! self::enabled() ) {
			return;
		}

		self::record(
			[
				'status' => self::STATUS_FAILED,
				'file'   => basename( $file_path ),
				'mime'   => self::guess_mime( $file_path ) ?? '',
				'error'  => $error,
			]
		);
	}

	public static function all(): array {
		$log = get_option( self::OPTION_NAME, [] );
		return is_array( $log ) ? $log : [];
	}

	public static function clear(): void {
		delete_option( self::OPTION_NAME );
	}

	private static function enabled(): bool {
		return (bool) Options::get( 'enable_log' );
	}

	private static function record( array $entry ): void {
		$entry['ts'] = time();

		$log = self::all();
		array_unshift( $log, $entry );

		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_ENTRIES );
		}

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, $log, '', false );
			return;
		}

		update_option( self::OPTION_NAME, $log );
	}

	private static function guess_mime( string $path ): ?string {
		if ( is_readable( $path ) && function_exists( 'mime_content_type' ) ) {
			$mime = mime_content_type( $path );
			if ( is_string( $mime ) && '' !== $mime ) {
				return $mime;
			}
		}

		$by_ext = [
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'gif'  => 'image/gif',
			'avif' => 'image/avif',
			'heic' => 'image/heic',
			'heif' => 'image/heif',
			'tiff' => 'image/tiff',
			'tif'  => 'image/tiff',
			'bmp'  => 'image/bmp',
		];

		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return $by_ext[ $ext ] ?? null;
	}
}
