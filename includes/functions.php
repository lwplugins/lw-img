<?php
/**
 * Public helper functions.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

// `return`, not `exit`, in this one file: Composer pulls it in through
// autoload.files, so the autoloader itself would be killed by an exit here —
// silently taking PHPUnit and every other vendor binary with it. Returning
// leaves a direct request with an empty response just the same.
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( __NAMESPACE__ . '\\lw_img_is_configured' ) ) {
	function lw_img_is_configured(): bool {
		$key = (string) Options::get( 'api_key' );
		return '' !== trim( $key );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\lw_img_dashboard_url' ) ) {
	/**
	 * URL of the HelloImg dashboard, shown wherever the plugin links out.
	 *
	 * @return string
	 */
	function lw_img_dashboard_url(): string {
		/**
		 * Filters the HelloImg dashboard URL the plugin links to.
		 *
		 * @param string $url Dashboard URL.
		 */
		return (string) apply_filters( 'lw_img_dashboard_url', 'https://app.helloimg.io/' );
	}
}
