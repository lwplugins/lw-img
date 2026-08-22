<?php
/**
 * Decides which thumbnail sizes get a smart-crop API call.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload\SmartCrop;

defined( 'ABSPATH' ) || exit;

/**
 * Pure selection logic — every rule that turns user choices and attachment
 * metadata into paid API calls lives here, with no I/O, so it is the tested
 * part of the feature.
 *
 * A size becomes a job only when: the user selected it, it is registered
 * with crop => true (re-checked here because a theme switch may have changed
 * it), WordPress actually generated it, and its aspect ratio differs from
 * the original's — an equal ratio means cropping removes nothing and the
 * WordPress thumbnail is already correct.
 */
final class SizeCatalog {

	/**
	 * Build the crop jobs for one attachment.
	 *
	 * Dimensions come from the GENERATED size, never the registered one:
	 * WordPress scales a crop down when the source is smaller than the
	 * registered box, and cropping at registered dims would then disagree
	 * with the metadata and corrupt srcset.
	 *
	 * @param array<int, string>   $selected   User-selected size names.
	 * @param array<string, mixed> $registered wp_get_registered_image_subsizes() map.
	 * @param array<string, mixed> $metadata   wp_get_attachment_metadata() array.
	 * @return array<int, array{name: string, width: int, height: int, file: string}>
	 */
	public static function jobs( array $selected, array $registered, array $metadata ): array {
		$original_width  = (int) ( $metadata['width'] ?? 0 );
		$original_height = (int) ( $metadata['height'] ?? 0 );
		$sizes           = is_array( $metadata['sizes'] ?? null ) ? $metadata['sizes'] : [];

		if ( $original_width <= 0 || $original_height <= 0 ) {
			return [];
		}

		$jobs = [];

		foreach ( $selected as $name ) {
			$definition = $registered[ $name ] ?? null;
			$generated  = $sizes[ $name ] ?? null;

			if ( ! is_array( $definition ) || empty( $definition['crop'] ) || ! is_array( $generated ) ) {
				continue;
			}

			$width  = (int) ( $generated['width'] ?? 0 );
			$height = (int) ( $generated['height'] ?? 0 );
			$file   = (string) ( $generated['file'] ?? '' );

			if ( $width <= 0 || $height <= 0 || '' === $file ) {
				continue;
			}

			if ( self::ratio_matches( $original_width, $original_height, $width, $height ) ) {
				continue;
			}

			$jobs[] = [
				'name'   => $name,
				'width'  => $width,
				'height' => $height,
				'file'   => $file,
			];
		}

		return $jobs;
	}

	/**
	 * Whether two aspect ratios are equal after rounding to two decimals.
	 *
	 * @param int $a_width  First width.
	 * @param int $a_height First height.
	 * @param int $b_width  Second width.
	 * @param int $b_height Second height.
	 * @return bool
	 */
	private static function ratio_matches( int $a_width, int $a_height, int $b_width, int $b_height ): bool {
		return round( $a_width / $a_height, 2 ) === round( $b_width / $b_height, 2 );
	}
}
