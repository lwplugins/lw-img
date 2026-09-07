<?php
/**
 * Settings Tab Interface.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

defined( 'ABSPATH' ) || exit;

interface TabInterface {

	public function get_slug(): string;

	public function get_label(): string;

	public function get_icon(): string;

	/**
	 * Whether the panel contains fields of the settings form (and so needs
	 * a Save button under it).
	 */
	public function has_settings(): bool;

	public function render(): void;
}
