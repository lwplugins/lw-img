<?php
/**
 * Per-upload event log (ring buffer, last 200 entries).
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Log;

defined( 'ABSPATH' ) || exit;

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
	public const STATUS_RESTORED  = 'restored';

	public static function register(): void {
		add_action( 'lw_img_upload_converted', [ self::class, 'on_converted' ], 10, 3 );
		add_action( 'lw_img_upload_skipped', [ self::class, 'on_skipped' ], 10, 3 );
		add_action( 'lw_img_upload_failed', [ self::class, 'on_failed' ], 10, 3 );
		add_action( 'lw_img_restored', [ self::class, 'on_restored' ], 10, 2 );
	}

	public static function on_restored( int $attachment_id, string $file_path ): void {
		if ( ! self::enabled() ) {
			return;
		}

		self::record(
			[
				'status' => self::STATUS_RESTORED,
				'file'   => basename( $file_path ),
				'mime'   => self::guess_mime( $file_path ) ?? '',
			]
		);
	}

	/**
	 * @param string               $original_path Absolute path of the original.
	 * @param string               $new_path      Absolute path of the converted file.
	 * @param array<string, mixed> $context       Conversion result; attachment_id on bulk / on-demand runs.
	 * @return void
	 */
	public static function on_converted( string $original_path, string $new_path, array $context ): void {
		if ( ! self::enabled() ) {
			return;
		}

		self::record(
			array_merge(
				[
					'status'   => self::STATUS_CONVERTED,
					'file'     => basename( $original_path ),
					'mime'     => (string) ( $context['mime'] ?? self::guess_mime( $original_path ) ?? '' ),
					'mime_to'  => (string) ( $context['mime_to'] ?? self::guess_mime( $new_path ) ?? 'image/webp' ),
					'size_in'  => (int) ( $context['original_size'] ?? 0 ),
					'size_out' => (int) ( $context['new_size'] ?? 0 ),
					'percent'  => (float) ( $context['percent'] ?? 0 ),
					'job_id'   => (string) ( $context['job_id'] ?? '' ),
				],
				self::context_fields( $context )
			)
		);
	}

	/**
	 * @param string               $file_path Absolute path.
	 * @param string               $reason    Skip reason.
	 * @param array<string, mixed> $context   Optional attachment_id / original_size / new_size.
	 * @return void
	 */
	public static function on_skipped( string $file_path, string $reason, array $context = [] ): void {
		if ( ! self::enabled() ) {
			return;
		}

		self::record(
			array_merge(
				[
					'status' => self::STATUS_SKIPPED,
					'file'   => basename( $file_path ),
					'mime'   => self::guess_mime( $file_path ) ?? '',
					'reason' => $reason,
				],
				self::context_fields( $context )
			)
		);
	}

	/**
	 * @param string               $file_path Absolute path.
	 * @param string               $error     Error message.
	 * @param array<string, mixed> $context   Optional attachment_id.
	 * @return void
	 */
	public static function on_failed( string $file_path, string $error, array $context = [] ): void {
		if ( ! self::enabled() ) {
			return;
		}

		self::record(
			array_merge(
				[
					'status' => self::STATUS_FAILED,
					'file'   => basename( $file_path ),
					'mime'   => self::guess_mime( $file_path ) ?? '',
					'error'  => $error,
				],
				self::context_fields( $context )
			)
		);
	}

	/**
	 * Only positive values are worth storing (the ring buffer lives in wp_options).
	 *
	 * @param array<string, mixed> $context Action context.
	 * @return array<string, int>
	 */
	private static function context_fields( array $context ): array {
		$fields = [];
		$map    = [
			'attachment_id' => 'attachment_id',
			'original_size' => 'size_in',
			'new_size'      => 'size_out',
		];

		foreach ( $map as $from => $to ) {
			$value = (int) ( $context[ $from ] ?? 0 );
			if ( $value > 0 ) {
				$fields[ $to ] = $value;
			}
		}

		return $fields;
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
