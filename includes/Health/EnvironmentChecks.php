<?php
/**
 * PHP and image-processing environment checks.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Health;

use LightweightPlugins\Img\Bulk\Throttle;
use LightweightPlugins\Img\Options;

/**
 * The plugin's real local work is thumbnail regeneration, so the image
 * editor's WebP/AVIF support is the make-or-break check here.
 */
final class EnvironmentChecks {

	/**
	 * Check rows.
	 *
	 * @return array<int, array{label: string, status: string, message: string}>
	 */
	public static function rows(): array {
		$rows = [];

		$rows[] = [
			'label'   => __( 'PHP version', 'lw-img' ),
			'status'  => 'ok',
			'message' => PHP_VERSION,
		];

		$memory = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		$rows[] = [
			'label'   => __( 'PHP memory limit', 'lw-img' ),
			'status'  => ( $memory < 0 || $memory >= 128 * MB_IN_BYTES ) ? 'ok' : 'warning',
			'message' => (string) ini_get( 'memory_limit' ),
		];

		$rows[] = [
			'label'   => __( 'cURL extension', 'lw-img' ),
			'status'  => extension_loaded( 'curl' ) ? 'ok' : 'critical',
			'message' => extension_loaded( 'curl' ) ? __( 'loaded', 'lw-img' ) : __( 'missing — API requests cannot be made', 'lw-img' ),
		];

		$editor = extension_loaded( 'imagick' ) ? 'Imagick' : ( extension_loaded( 'gd' ) ? 'GD' : '' );
		$rows[] = [
			'label'   => __( 'Image editor', 'lw-img' ),
			'status'  => '' !== $editor ? 'ok' : 'critical',
			'message' => '' !== $editor ? $editor : __( 'neither Imagick nor GD is available', 'lw-img' ),
		];

		$webp   = wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] );
		$rows[] = [
			'label'   => __( 'WebP thumbnail support', 'lw-img' ),
			'status'  => $webp ? 'ok' : 'critical',
			'message' => $webp ? __( 'supported', 'lw-img' ) : __( 'not supported — thumbnails cannot be generated from converted WebP files', 'lw-img' ),
		];

		if ( 'avif' === Options::get( 'output_format' ) ) {
			$avif   = wp_image_editor_supports( [ 'mime_type' => 'image/avif' ] );
			$rows[] = [
				'label'   => __( 'AVIF thumbnail support', 'lw-img' ),
				'status'  => $avif ? 'ok' : 'warning',
				'message' => $avif ? __( 'supported', 'lw-img' ) : __( 'not supported — switch the output format to WebP or upgrade the image editor', 'lw-img' ),
			];
		}

		$load   = function_exists( 'sys_getloadavg' ) ? sys_getloadavg() : false;
		$rows[] = [
			'label'   => __( 'CPU cores / load', 'lw-img' ),
			'status'  => 'info',
			'message' => sprintf(
				/* translators: 1: CPU core count, 2: load average. */
				__( '%1$d cores, 1-min load %2$s (in containers this may reflect the whole host)', 'lw-img' ),
				Throttle::cpu_cores(),
				is_array( $load ) ? number_format_i18n( (float) $load[0], 2 ) : __( 'unavailable', 'lw-img' )
			),
		];

		return $rows;
	}
}
