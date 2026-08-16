<?php
/**
 * Main Plugin class.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

use LightweightPlugins\Img\Admin\SettingsPage;
use LightweightPlugins\Img\Log\ClearHandler;
use LightweightPlugins\Img\Log\EventLog;
use LightweightPlugins\Img\Upload\UploadInterceptor;

/**
 * Wires up plugin components and hooks.
 */
final class Plugin {

	public function __construct() {
		$this->init_hooks();
		$this->init_components();
	}

	private function init_hooks(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );
	}

	private function init_components(): void {
		EventLog::register();
		new UploadInterceptor();

		if ( is_admin() ) {
			ClearHandler::register();
			new SettingsPage();
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'lw-img',
			false,
			dirname( plugin_basename( LW_IMG_FILE ) ) . '/languages'
		);
	}
}
