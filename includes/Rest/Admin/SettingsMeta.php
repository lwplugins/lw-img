<?php
/**
 * Context the settings screen builds its controls from.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Rest\Admin;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\SiteHost;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Settings\Input\RuleListParser;
use LightweightPlugins\Img\Settings\SettingsSchema;
use LightweightPlugins\Img\Upload\Rules\RuleSet;

use function LightweightPlugins\Img\lw_img_dashboard_url;

/**
 * Locked keys, ranges, enums, the hard-crop image sizes, links and the
 * server capabilities the screen shows.
 */
final class SettingsMeta {

	/**
	 * Plugin documentation.
	 */
	public const DOCS_URL = 'https://github.com/lwplugins/lw-img#readme';

	/**
	 * Build the meta block.
	 *
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$dashboard = lw_img_dashboard_url();
		$host      = wp_parse_url( $dashboard, PHP_URL_HOST );

		return [
			'locked'            => (object) self::locked(),
			'ranges'            => (object) self::ranges(),
			'enums'             => (object) SettingsSchema::enums(),
			'rule_actions'      => RuleSet::ACTIONS,
			'max_rules'         => RuleListParser::MAX_RULES,
			'image_sizes'       => self::image_sizes(),
			'retention_presets' => [ 7, 30, 90, 365, 0 ],
			'backup_path'       => 'uploads/lw-img-backups/',
			'dashboard_url'     => $dashboard,
			'dashboard_host'    => is_string( $host ) && '' !== $host ? $host : $dashboard,
			'docs_url'          => self::DOCS_URL,
			'site_host'         => SiteHost::current(),
			'capabilities'      => self::capabilities(),
		];
	}

	/**
	 * Keys pinned by a wp-config.php constant, with the constant name.
	 *
	 * @return array<string, string>
	 */
	private static function locked(): array {
		return null !== Options::constant_api_key()
			? [ SettingsSchema::SECRET_KEY => SettingsSchema::SECRET_CONSTANT ]
			: [];
	}

	/**
	 * Numeric ranges as {min, max}.
	 *
	 * @return array<string, array{min: int, max: int}>
	 */
	private static function ranges(): array {
		$ranges = [];

		foreach ( SettingsSchema::ranges() as $key => [ $min, $max ] ) {
			$ranges[ $key ] = [
				'min' => $min,
				'max' => $max,
			];
		}

		return $ranges;
	}

	/**
	 * Registered hard-crop sizes (the only ones smart crop can work on).
	 *
	 * @return array<int, array{name: string, width: int, height: int}>
	 */
	private static function image_sizes(): array {
		$sizes = [];

		foreach ( wp_get_registered_image_subsizes() as $name => $size ) {
			if ( empty( $size['crop'] ) ) {
				continue;
			}

			$sizes[] = [
				'name'   => (string) $name,
				'width'  => (int) ( $size['width'] ?? 0 ),
				'height' => (int) ( $size['height'] ?? 0 ),
			];
		}

		return $sizes;
	}

	/**
	 * What the server's image editor can do with the output formats.
	 *
	 * @return array{webp_thumbnails: bool, avif_thumbnails: bool}
	 */
	private static function capabilities(): array {
		return [
			'webp_thumbnails' => (bool) wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ),
			'avif_thumbnails' => (bool) wp_image_editor_supports( [ 'mime_type' => 'image/avif' ] ),
		];
	}
}
