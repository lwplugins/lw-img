<?php
/**
 * Public hooks (filters/actions) reference.
 *
 * Each hook also has a full docblock at its call site. Despite the
 * `lw_img_upload_` prefix, the converted/skipped/failed actions fire for
 * uploads AND for bulk / on-demand runs; `failed` also fires for smart crop.
 *
 * Filters:
 *  - `lw_img_should_convert` ( bool $should_convert, string $file_path, string $mime_type ): bool — since 1.0.0.
 *    Runs only after the built-in skip checks passed: it can veto a conversion, not force one.
 *  - `lw_img_optimize_request_args` ( array $args, string $file_path ): array — since 1.0.0.
 *    Keys: level, keep_exif, convert, max_width, max_height.
 *  - `lw_img_competitor_plugins` ( array $competitors ): array — since 1.2.0.
 *    slug => { name?, plugin?, meta_keys? }.
 *  - `lw_img_dashboard_url` ( string $url ): string — since 1.8.1.
 *  - `lw_img_site_host` ( string $host ): string — since 1.9.3. The host sent as
 *    the X-HIMG-Site request header on every API call (multisite, reverse
 *    proxies); defaults to home_url()'s host, site_url()'s when home has none.
 *
 * Actions ($context is always an array; attachment_id is present whenever an
 * attachment exists, i.e. everywhere except the upload path):
 *  - `lw_img_upload_converted` ( string $original_path, string $new_path, array $context ) — since 1.0.0.
 *    $context: original_size, new_size, percent, job_id, mime, mime_to, attachment_id (since 2.0.1, not on upload).
 *  - `lw_img_upload_skipped` ( string $file_path, string $reason, array $context ) — since 1.0.0.
 *    $context: attachment_id (not on upload); original_size, new_size when the result was not smaller.
 *  - `lw_img_upload_failed` ( string $file_path, string $error, array $context ) — since 1.0.0.
 *    $context: attachment_id (not on upload). Smart crop passes the size file's
 *    path and a 'smart crop: ' error prefix.
 *  - `lw_img_restored` ( int $attachment_id, string $restored_path ) — since 1.1.0.
 *  - `lw_plugins_overview_cards` () — since 1.0.0; shared by every LW plugin.
 *
 * Pattern rules (option `pattern_rules`, list of {pattern, action, value}) run
 * before these hooks: exclude / keep_size / level / keep_exif per wildcard
 * pattern, applied identically to uploads and bulk runs (exclude also gates
 * smart crop).
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

defined( 'ABSPATH' ) || exit;

/**
 * Reference holder for the plugin's public hooks (documented in the file header).
 */
final class Hooks {

	public function __construct() {
		// Reserved for future runtime hooks (none required for v1.0.0).
	}
}
