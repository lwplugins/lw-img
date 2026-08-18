<?php
/**
 * Known competitor image optimizers: their plugin paths and postmeta marks.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Compat;

defined( 'ABSPATH' ) || exit;

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
			// core/modules/class-smush.php: $smushed_meta_key, also the key
			// its own uninstall.php deletes.
			'meta_keys' => [ 'wp-smpro-smush-data' ],
		],
		'ewww'       => [
			'name'      => 'EWWW Image Optimizer',
			'plugin'    => 'ewww-image-optimizer/ewww-image-optimizer.php',
			// EWWW keeps no postmeta: optimization records live in its own
			// {prefix}ewwwio_images table, queried separately below.
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

		if ( self::ewww_manages( $attachment_id ) ) {
			return 'EWWW Image Optimizer';
		}

		return null;
	}

	/**
	 * Whether EWWW already optimized this attachment.
	 *
	 * EWWW records optimizations in its own {prefix}ewwwio_images table
	 * rather than in postmeta, so it needs a lookup of its own. The query
	 * uses the plugin's own (gallery, attachment_id) index, and the
	 * table-exists probe is resolved once per request — on sites without
	 * EWWW this costs a single SHOW TABLES for the whole bulk run.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool
	 */
	private static function ewww_manages( int $attachment_id ): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		static $table = null;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- third-party table probed once per request and named from $wpdb->prefix; values are placeholders. The per-image lookup must stay uncached to reflect EWWW's live state.
		if ( null === $table ) {
			$candidate = $wpdb->prefix . 'ewwwio_images';
			$table     = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $candidate ) ) === $candidate ) ? $candidate : '';
		}

		if ( '' === $table ) {
			return false;
		}

		$found = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE attachment_id = %d AND gallery = 'media' AND pending = 0 AND image_size > 0 LIMIT 1",
				$attachment_id
			)
		);
		// phpcs:enable

		return $found;
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
