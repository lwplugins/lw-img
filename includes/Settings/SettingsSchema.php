<?php
/**
 * Value rules of every setting.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Settings;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\OptimizeRequest;
use LightweightPlugins\Img\Bulk\Throttle;

/**
 * Ranges and allowed values, in one place for the input layer, the REST
 * meta the admin screen builds its controls from, and the tests. Pure data.
 */
final class SettingsSchema {

	/**
	 * The setting that holds the API key (never returned in clear).
	 */
	public const SECRET_KEY = 'api_key';

	/**
	 * Constant that pins the API key in wp-config.php.
	 */
	public const SECRET_CONSTANT = 'LW_IMG_API_KEY';

	/**
	 * Inclusive [min, max] of every whole-number setting.
	 *
	 * @return array<string, array{0: int, 1: int}>
	 */
	public static function ranges(): array {
		return [
			'request_timeout'       => [ 5, 120 ],
			'max_filesize_mb'       => [ 1, 10 ],
			'min_filesize_kb'       => [ 0, 10240 ],
			'max_width'             => [ 0, 10000 ],
			'max_height'            => [ 0, 10000 ],
			'backup_retention_days' => [ 0, 3650 ],
		];
	}

	/**
	 * Allowed values of every select-type setting.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function enums(): array {
		return [
			'level'         => self::levels(),
			'output_format' => [ OptimizeRequest::FORMAT_WEBP, OptimizeRequest::FORMAT_AVIF ],
			'bulk_speed'    => Throttle::SPEEDS,
		];
	}

	/**
	 * Optimization levels, lightest compression first.
	 *
	 * @return array<int, string>
	 */
	public static function levels(): array {
		return [
			OptimizeRequest::LEVEL_LOSSLESS,
			OptimizeRequest::LEVEL_NORMAL,
			OptimizeRequest::LEVEL_AGGRESSIVE,
			OptimizeRequest::LEVEL_ULTRA,
		];
	}
}
