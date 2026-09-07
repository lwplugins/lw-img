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
use LightweightPlugins\Img\Upload\Rules\RuleSet;

/**
 * Applies the skip rules that decide whether an upload is sent to HelloImg.
 */
final class ConvertibleDetector {

	private const SKIP_TYPES = [
		'image/webp',
		'image/avif',
	];

	/**
	 * Injected rule set (tests); null resolves the saved option per call.
	 *
	 * @var RuleSet|null
	 */
	private ?RuleSet $rules;

	public function __construct( ?RuleSet $rules = null ) {
		$this->rules = $rules;
	}

	private function rules(): RuleSet {
		return $this->rules ?? RuleSet::from_options();
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
	 * @param string $file_path     Absolute file path.
	 * @param string $mime_type     File mime type.
	 * @param int    $attachment_id Attachment post id, when known (0 otherwise).
	 * @return bool
	 */
	public function should_convert_on_demand( string $file_path, string $mime_type, int $attachment_id = 0 ): bool {
		return $this->passes_common_rules( $file_path, $mime_type, $attachment_id );
	}

	/**
	 * Whether a new upload may be recorded for smart-crop scheduling.
	 *
	 * Deliberately NOT should_convert(): the conversion-specific skips
	 * (already webp/avif, animated gif, the auto_convert toggle, API key)
	 * must not gate this — a native WebP upload still gets cropped thumbs,
	 * and the cron handler re-checks the key. What DOES carry over: the
	 * mime scope, the user's pattern rules (exclude), and the file-size limits —
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

		return null === $this->file_rules_reason( $file_path );
	}

	private function passes_common_rules( string $file_path, string $mime_type, int $attachment_id = 0 ): bool {
		if ( '' === (string) Options::get( 'api_key' ) ) {
			return $this->skip( $file_path, 'no API key', $attachment_id );
		}

		if ( ! str_starts_with( $mime_type, 'image/' ) ) {
			return $this->skip( $file_path, 'not an image', $attachment_id );
		}

		if ( Options::get( 'skip_already_webp' ) && in_array( $mime_type, self::SKIP_TYPES, true ) ) {
			return $this->skip( $file_path, 'already webp/avif', $attachment_id );
		}

		$allowed = (array) Options::get( 'mime_types' );
		if ( ! in_array( $mime_type, $allowed, true ) ) {
			return $this->skip( $file_path, 'mime not in allowed list', $attachment_id );
		}

		$reason = $this->file_rules_reason( $file_path );
		if ( null !== $reason ) {
			return $this->skip( $file_path, $reason, $attachment_id );
		}

		if ( Options::get( 'skip_animated_gif' ) && 'image/gif' === $mime_type && AnimatedGifProbe::is_animated( $file_path ) ) {
			return $this->skip( $file_path, 'animated gif', $attachment_id );
		}

		return (bool) apply_filters( 'lw_img_should_convert', true, $file_path, $mime_type );
	}

	/**
	 * The file-level rules shared by should_convert()/should_convert_on_demand()
	 * (via passes_common_rules()) and smart_crop_eligible(): pattern rules,
	 * readability, and the min/max size window. Mime-scope checks stay in each
	 * caller — they legitimately differ (smart_crop_eligible ORs in
	 * SKIP_TYPES; passes_common_rules excludes them).
	 *
	 * @param string $file_path Absolute file path.
	 * @return string|null Skip reason, or null when the file passes all rules.
	 */
	private function file_rules_reason( string $file_path ): ?string {
		if ( $this->rules()->resolve( $file_path )->excluded ) {
			return 'excluded by rule';
		}

		if ( ! is_readable( $file_path ) ) {
			return 'file not readable';
		}

		$max_bytes = ( (int) Options::get( 'max_filesize_mb' ) ) * 1024 * 1024;
		if ( $max_bytes > 0 && filesize( $file_path ) > $max_bytes ) {
			return 'exceeds max_filesize_mb';
		}

		$min_bytes = ( (int) Options::get( 'min_filesize_kb' ) ) * 1024;
		if ( $min_bytes > 0 && filesize( $file_path ) < $min_bytes ) {
			return 'below min_filesize_kb';
		}

		return null;
	}

	private function skip( string $file_path, string $reason, int $attachment_id = 0 ): bool {
		do_action(
			'lw_img_upload_skipped',
			$file_path,
			$reason,
			$attachment_id > 0 ? [ 'attachment_id' => $attachment_id ] : []
		);
		return false;
	}
}
