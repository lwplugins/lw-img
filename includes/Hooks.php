<?php
/**
 * Public hooks (filters/actions) reference.
 *
 * Documents the filters this plugin exposes for third-party integration.
 * The converted/skipped/failed actions fire for uploads AND for bulk /
 * on-demand optimization of existing attachments:
 *
 *  - filter `lw_img_should_convert`        ( bool $should_convert, string $file_path, string $mime_type ): bool
 *  - filter `lw_img_optimize_request_args` ( array $args, string $file_path ): array
 *  - action `lw_img_upload_converted`     ( string $original_path, string $new_path, array $result )
 *  - action `lw_img_upload_skipped`       ( string $file_path, string $reason )
 *  - action `lw_img_upload_failed`        ( string $file_path, string $error )
 *  - action `lw_img_restored`             ( int $attachment_id, string $restored_path )
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

/**
 * Reference holder for the plugin's public hooks (documented in the file header).
 */
final class Hooks {

	public function __construct() {
		// Reserved for future runtime hooks (none required for v1.0.0).
	}
}
