<?php
/**
 * Default option values.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

/**
 * Provides the plugin's default option values.
 */
final class DefaultOptions {

	public static function all(): array {
		return array_merge(
			self::api(),
			self::upload(),
			self::advanced()
		);
	}

	private static function api(): array {
		return [
			'api_key' => '',
		];
	}

	private static function upload(): array {
		return [
			'auto_convert'       => true,
			'level'              => 'normal',
			'output_format'      => 'webp',
			'keep_exif'          => false,
			'max_width'          => 0,
			'max_height'         => 0,
			'skip_already_webp'  => true,
			'skip_animated_gif'  => false,
			'max_filesize_mb'    => 10,
			'exclusion_patterns' => [],
			'mime_types'         => [
				'image/jpeg',
				'image/png',
				'image/heic',
				'image/heif',
				'image/tiff',
				'image/bmp',
				'image/gif',
			],
		];
	}

	private static function advanced(): array {
		return [
			'backup_enabled'        => true,
			'backup_retention_days' => 30,
			'request_timeout'       => 30,
			'debug_mode'            => false,
			'enable_log'            => true,
		];
	}
}
