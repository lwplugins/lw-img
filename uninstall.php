<?php
/**
 * Uninstall handler.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'lw_img_options' );
delete_option( 'lw_img_version' );
delete_option( 'lw_img_log' );
