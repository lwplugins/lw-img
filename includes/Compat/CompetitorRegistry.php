<?php
/**
 * Known competitor image optimizers: their plugin paths and postmeta marks.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Compat;

/**
 * Meta keys were verified against each plugin's source. Plugins without
 * meta keys listed are only used for the "another optimizer is active"
 * warning, not for per-image detection. The list is filterable via
 * `lw_img_competitor_plugins`.
 */
final class CompetitorRegistry {

	private const COMPETITORS = [
		'shortpixel' => [
			'name'      => 'ShortPixel Image Optimizer',
			'plugin'    => 'shortpixel-image-optimiser/wp-shortpixel.php',
			'meta_keys' => [ '_shortpixel_optimized', '_shortpixel_status', '_shortpixel_meta' ],
		],
		'tinypng'    => [
			'name'      => 'TinyPNG (Tinify)',
			'plugin'    => 'tiny-compress-images/tiny-compress-images.php',
			'meta_keys' => [ '_tiny_compress_images', 'tiny_compress_images' ],
		],
		'imagify'    => [
			'name'      => 'Imagify',
			'plugin'    => 'imagify/imagify.php',
			'meta_keys' => [ '_imagify_data', '_imagify_status' ],
		],
		'smush'      => [
			'name'      => 'Smush',
			'plugin'    => 'wp-smushit/wp-smush.php',
			'meta_keys' => [],
		],
		'ewww'       => [
			'name'      => 'EWWW Image Optimizer',
			'plugin'    => 'ewww-image-optimizer/ewww-image-optimizer.php',
			'meta_keys' => [],
		],
	];

	/**
	 * The competitor list (filterable — entries added via the filter may
	 * omit keys, hence the optional shape).
	 *
	 * @return array<string, array{name?: string, plugin?: string, meta_keys?: array<int, string>}>
	 */
	public static function competitors(): array {
		return (array) apply_filters( 'lw_img_competitor_plugins', self::COMPETITORS );
	}

	/**
	 * Which competitor (if any) already optimized this attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|null Human-readable plugin name, or null.
	 */
	public static function managed_by( int $attachment_id ): ?string {
		foreach ( self::competitors() as $competitor ) {
			foreach ( (array) ( $competitor['meta_keys'] ?? [] ) as $meta_key ) {
				if ( metadata_exists( 'post', $attachment_id, (string) $meta_key ) ) {
					return (string) ( $competitor['name'] ?? 'another optimizer' );
				}
			}
		}

		return null;
	}

	/**
	 * Names of currently active competitor plugins, keyed by slug.
	 *
	 * @return array<string, string>
	 */
	public static function active_competitors(): array {
		$active_plugins = (array) get_option( 'active_plugins', [] );
		$found          = [];

		foreach ( self::competitors() as $slug => $competitor ) {
			if ( in_array( (string) ( $competitor['plugin'] ?? '' ), $active_plugins, true ) ) {
				$found[ (string) $slug ] = (string) ( $competitor['name'] ?? $slug );
			}
		}

		return $found;
	}
}
