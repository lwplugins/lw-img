<?php
/**
 * Decides whether a freshly uploaded file should be sent to HelloImg.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Upload;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;

/**
 * Applies the skip rules that decide whether an upload is sent to HelloImg.
 */
final class ConvertibleDetector {

	private const SKIP_TYPES = [
		'image/webp',
		'image/avif',
	];

	/**
	 * Matches user-defined exclusion patterns.
	 *
	 * @var ExclusionMatcher
	 */
	private ExclusionMatcher $exclusions;

	public function __construct( ?ExclusionMatcher $exclusions = null ) {
		$this->exclusions = $exclusions ?? new ExclusionMatcher();
	}

	public function should_convert( string $file_path, string $mime_type ): bool {
		if ( ! Options::get( 'auto_convert' ) ) {
			return $this->skip( $file_path, 'auto_convert disabled' );
		}

		return $this->passes_common_rules( $file_path, $mime_type );
	}

	/**
	 * Like should_convert(), but for explicit user actions (bulk, row action):
	 * the auto-convert-on-upload toggle does not apply.
	 *
	 * @param string $file_path Absolute file path.
	 * @param string $mime_type File mime type.
	 * @return bool
	 */
	public function should_convert_on_demand( string $file_path, string $mime_type ): bool {
		return $this->passes_common_rules( $file_path, $mime_type );
	}

	/**
	 * Whether a new upload may be recorded for smart-crop scheduling.
	 *
	 * Deliberately NOT should_convert(): the conversion-specific skips
	 * (already webp/avif, animated gif, the auto_convert toggle, API key)
	 * must not gate this — a native WebP upload still gets cropped thumbs,
	 * and the cron handler re-checks the key. What DOES carry over: the
	 * mime scope, the user's exclusion patterns, and the file-size limits —
	 * smart crop re-sends the main file once per size, so a file the user
	 * excluded from the API stays excluded here.
	 *
	 * @param string $file_path Absolute file path.
	 * @param string $mime_type File mime type.
	 * @return bool
	 */
	public function smart_crop_eligible( string $file_path, string $mime_type ): bool {
		if ( ! str_starts_with( $mime_type, 'image/' ) ) {
			return false;
		}

		$allowed = (array) Options::get( 'mime_types' );
		if ( ! in_array( $mime_type, $allowed, true ) && ! in_array( $mime_type, self::SKIP_TYPES, true ) ) {
			return false;
		}

		$patterns = (array) Options::get( 'exclusion_patterns' );
		if ( [] !== $patterns && $this->exclusions->matches( $file_path, $patterns ) ) {
			return false;
		}

		if ( ! is_readable( $file_path ) ) {
			return false;
		}

		$max_bytes = ( (int) Options::get( 'max_filesize_mb' ) ) * 1024 * 1024;
		if ( $max_bytes > 0 && filesize( $file_path ) > $max_bytes ) {
			return false;
		}

		$min_bytes = ( (int) Options::get( 'min_filesize_kb' ) ) * 1024;
		if ( $min_bytes > 0 && filesize( $file_path ) < $min_bytes ) {
			return false;
		}

		return true;
	}

	private function passes_common_rules( string $file_path, string $mime_type ): bool {
		if ( '' === (string) Options::get( 'api_key' ) ) {
			return $this->skip( $file_path, 'no API key' );
		}

		if ( ! str_starts_with( $mime_type, 'image/' ) ) {
			return $this->skip( $file_path, 'not an image' );
		}

		if ( Options::get( 'skip_already_webp' ) && in_array( $mime_type, self::SKIP_TYPES, true ) ) {
			return $this->skip( $file_path, 'already webp/avif' );
		}

		$allowed = (array) Options::get( 'mime_types' );
		if ( ! in_array( $mime_type, $allowed, true ) ) {
			return $this->skip( $file_path, 'mime not in allowed list' );
		}

		$patterns = (array) Options::get( 'exclusion_patterns' );
		if ( [] !== $patterns && $this->exclusions->matches( $file_path, $patterns ) ) {
			return $this->skip( $file_path, 'excluded by pattern' );
		}

		if ( ! is_readable( $file_path ) ) {
			return $this->skip( $file_path, 'file not readable' );
		}

		$max_bytes = ( (int) Options::get( 'max_filesize_mb' ) ) * 1024 * 1024;
		if ( $max_bytes > 0 && filesize( $file_path ) > $max_bytes ) {
			return $this->skip( $file_path, 'exceeds max_filesize_mb' );
		}

		$min_bytes = ( (int) Options::get( 'min_filesize_kb' ) ) * 1024;
		if ( $min_bytes > 0 && filesize( $file_path ) < $min_bytes ) {
			return $this->skip( $file_path, 'below min_filesize_kb' );
		}

		if ( Options::get( 'skip_animated_gif' ) && 'image/gif' === $mime_type && AnimatedGifProbe::is_animated( $file_path ) ) {
			return $this->skip( $file_path, 'animated gif' );
		}

		return (bool) apply_filters( 'lw_img_should_convert', true, $file_path, $mime_type );
	}

	private function skip( string $file_path, string $reason ): bool {
		do_action( 'lw_img_upload_skipped', $file_path, $reason );
		return false;
	}
}
