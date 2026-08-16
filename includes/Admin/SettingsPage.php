<?php
/**
 * Settings Page (lw-img).
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin;

use LightweightPlugins\Img\Admin\Settings\TabBackup;
use LightweightPlugins\Img\Admin\Settings\TabBulk;
use LightweightPlugins\Img\Admin\Settings\TabGeneral;
use LightweightPlugins\Img\Admin\Settings\TabLog;
use LightweightPlugins\Img\Admin\Settings\TabUpload;
use LightweightPlugins\Img\Options;

/**
 * Registers the settings submenu page, settings group, and admin assets.
 */
final class SettingsPage {

	public const SLUG = 'lw-img';

	private const SETTINGS_GROUP = 'lw_img_settings';

	/**
	 * Settings tabs in render order.
	 *
	 * @var array<int, Settings\TabInterface>
	 */
	private array $tabs;

	public function __construct() {
		$this->tabs = [
			new TabGeneral(),
			new TabUpload(),
			new TabBulk(),
			new TabBackup(),
			new TabLog(),
		];

		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_menu_page(): void {
		ParentPage::maybe_register();

		add_submenu_page(
			ParentPage::SLUG,
			__( 'Img', 'lw-img' ),
			__( 'Img', 'lw-img' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		$valid_hooks = [
			'toplevel_page_' . ParentPage::SLUG,
			ParentPage::SLUG . '_page_' . self::SLUG,
		];

		if ( ! in_array( $hook, $valid_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'lw-img-admin',
			LW_IMG_URL . 'assets/css/admin.css',
			[],
			LW_IMG_VERSION
		);

		wp_enqueue_script(
			'lw-img-admin',
			LW_IMG_URL . 'assets/js/admin.js',
			[],
			LW_IMG_VERSION,
			true
		);
	}

	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			Options::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => Options::get_defaults(),
			]
		);
	}

	public function sanitize_settings( array $input ): array {
		return SettingsSanitizer::sanitize( $input );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		( new SettingsRenderer( $this->tabs ) )->render_page( self::SETTINGS_GROUP );
	}
}
