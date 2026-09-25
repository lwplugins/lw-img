<?php
/**
 * Settings Page (lw-img).
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Rest\Admin\Routes;
use LightweightPlugins\Img\Rest\Admin\SettingsMeta;

use function LightweightPlugins\Img\lw_img_dashboard_url;

/**
 * The LW Image screen: a mount point for the React admin (build/index),
 * which reads and writes through the lw-img/v1 REST routes.
 */
final class SettingsPage {

	/**
	 * Settings page slug.
	 */
	public const SLUG = 'lw-img';

	/**
	 * Script and style handle.
	 */
	private const HANDLE = 'lw-img-admin-app';

	/**
	 * Hook suffix returned by add_submenu_page().
	 *
	 * Assets are keyed on it rather than on a hard-coded
	 * "lw-plugins_page_lw-img": WordPress derives that prefix from the
	 * translated parent menu title, so a locale that translates "LW Plugins"
	 * would silently stop the screen from loading.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'admin_body_class', [ $this, 'body_class' ] );
	}

	/**
	 * Add menu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		ParentPage::maybe_register();

		$hook = add_submenu_page(
			ParentPage::SLUG,
			__( 'Image', 'lw-img' ),
			__( 'Image', 'lw-img' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);

		$this->hook_suffix = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Enqueue the React app on the settings screen.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->hook_suffix || $hook !== $this->hook_suffix ) {
			return;
		}

		if ( ! BuildAssets::enqueue( 'index', self::HANDLE ) ) {
			return;
		}

		wp_add_inline_script(
			self::HANDLE,
			'window.lwImg = ' . wp_json_encode(
				[
					'version'      => LW_IMG_VERSION,
					'namespace'    => Routes::NAMESPACE,
					'docsUrl'      => SettingsMeta::DOCS_URL,
					'dashboardUrl' => lw_img_dashboard_url(),
				]
			) . ';',
			'before'
		);
	}

	/**
	 * Mark the settings screen body for the app's styles.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( '' === $this->hook_suffix || ! $screen || $screen->id !== $this->hook_suffix ) {
			return $classes;
		}

		return $classes . ' lw-img-screen';
	}

	/**
	 * Render the mount point (or a notice when the build is missing).
	 *
	 * The mount point sits outside .wrap so NoticeManager's direct-child
	 * notice rules never reach the app; the missing-build notice carries
	 * `lw-notice` so it is not hidden.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! BuildAssets::exists( 'index' ) ) {
			printf(
				'<div class="wrap"><h1>%s</h1><div class="notice notice-error lw-notice"><p>%s</p></div></div>',
				esc_html__( 'LW Image', 'lw-img' ),
				esc_html__( 'The settings screen files are missing. Re-install the plugin from a release ZIP, or run "npm install && npm run build" in the plugin directory.', 'lw-img' )
			);
			return;
		}

		echo '<div id="lw-img-root" class="lw-img-root"></div>';
	}
}
