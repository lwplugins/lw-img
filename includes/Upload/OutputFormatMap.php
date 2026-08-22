<?php
/**
 * Maps the image editor's output format to the plugin's.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;

/**
 * WordPress 7.1's client-side uploader reads the image_editor_output_format
 * map from the server and generates every sub-size in the mapped format —
 * in the browser, at zero API cost. Mapping jpeg/png to the plugin's output
 * format therefore hands the thumbnail conversion to the browser while the
 * main file still goes through the API once.
 *
 * The same map governs the server-side editor (wp-admin edits, thumbnail
 * regeneration), which is consistent with what the plugin does to uploads;
 * the whole mapping is gated on auto_convert so switching the pipeline off
 * restores stock behaviour everywhere at once.
 *
 * GIF is deliberately absent (a client-side conversion would flatten
 * animation) and HEIC stays on core's own heic-to-jpeg mapping.
 */
final class OutputFormatMap {

	/**
	 * Hook the filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'image_editor_output_format', [ self::class, 'map' ] );
	}

	/**
	 * Extend the mapping with the plugin's output format.
	 *
	 * @param array<string, string> $map Mime-to-mime output mapping.
	 * @return array<string, string>
	 */
	public static function map( array $map ): array {
		if ( ! (bool) Options::get( 'auto_convert' ) ) {
			return $map;
		}

		$target = 'avif' === Options::get( 'output_format' ) ? 'image/avif' : 'image/webp';

		$map['image/jpeg'] = $target;
		$map['image/png']  = $target;

		return $map;
	}
}
