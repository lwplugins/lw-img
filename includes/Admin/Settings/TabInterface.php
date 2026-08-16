<?php
/**
 * Settings Tab Interface.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

interface TabInterface {

	public function get_slug(): string;

	public function get_label(): string;

	public function get_icon(): string;

	public function render(): void;
}
