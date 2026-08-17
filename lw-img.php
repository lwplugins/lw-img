<?php
/**
 * Plugin Name:       LW Img
 * Plugin URI:        https://github.com/lwplugins/lw-img
 * Description:       Lightweight image optimization — auto-convert WordPress uploads to WebP via the HelloImg API. No bloat, no upsell.
 * Version:           1.5.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            LW Plugins
 * Author URI:        https://lwplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lw-img
 * Domain Path:       /languages
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LW_IMG_VERSION', '1.5.0' );
define( 'LW_IMG_FILE', __FILE__ );
define( 'LW_IMG_PATH', plugin_dir_path( __FILE__ ) );
define( 'LW_IMG_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( LW_IMG_PATH . 'vendor/autoload.php' ) ) {
	require_once LW_IMG_PATH . 'vendor/autoload.php';
} elseif ( ! class_exists( Plugin::class ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p><strong>LW Img:</strong> %s</p></div>',
				esc_html__( 'Autoloader not found. Please run "composer install" in the plugin directory, or re-install the plugin from a release ZIP.', 'lw-img' )
			);
		}
	);
	return;
}

register_activation_hook( __FILE__, [ Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Activator::class, 'deactivate' ] );

function lw_img(): Plugin {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new Plugin();
	}

	return $instance;
}

add_action(
	'plugins_loaded',
	static function (): void {
		lw_img();
	}
);
