<?php
/**
 * Public helper functions.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

if ( ! function_exists( __NAMESPACE__ . '\\lw_img_is_configured' ) ) {
	function lw_img_is_configured(): bool {
		$key = (string) Options::get( 'api_key' );
		return '' !== trim( $key );
	}
}
