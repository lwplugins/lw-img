<?php
/**
 * Toggle-switch and segmented-control renderers.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;

trait ControlRendererTrait {

	/**
	 * A checkbox rendered as a toggle switch with label and description.
	 *
	 * @param array{name: string, label: string, description?: string} $args Field arguments.
	 * @return void
	 */
	protected function render_switch( array $args ): void {
		echo '<div class="lw-img-tog">';
		printf(
			'<label class="lw-img-switch"><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s /><span class="lw-img-switch-track" aria-hidden="true"></span></label>',
			esc_attr( $args['name'] ),
			esc_attr( Options::OPTION_NAME ),
			checked( Options::get( $args['name'] ), true, false )
		);
		echo '<div><label for="' . esc_attr( $args['name'] ) . '"><b>' . esc_html( $args['label'] ) . '</b></label>';
		if ( '' !== (string) ( $args['description'] ?? '' ) ) {
			echo '<p class="description">' . esc_html( (string) $args['description'] ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Radio group rendered as a segmented control; the active option's
	 * description shows below and admin.js swaps it on change.
	 *
	 * @param array{name: string, options: array<string, array{0: string, 1: string}>} $args Name and value => [label, description] map.
	 * @return void
	 */
	protected function render_segment( array $args ): void {
		$current = (string) Options::get( $args['name'] );

		echo '<div class="lw-img-seg-ctl" role="group">';
		foreach ( $args['options'] as $value => $option ) {
			printf(
				'<label class="lw-img-seg-item"><input type="radio" name="%1$s[%2$s]" value="%3$s" data-desc="%4$s" %5$s /><span>%6$s</span></label>',
				esc_attr( Options::OPTION_NAME ),
				esc_attr( $args['name'] ),
				esc_attr( (string) $value ),
				esc_attr( $option[1] ),
				checked( $current, (string) $value, false ),
				esc_html( $option[0] )
			);
		}
		echo '</div>';

		$active = $args['options'][ $current ] ?? reset( $args['options'] );
		echo '<p class="description lw-img-seg-desc">' . esc_html( is_array( $active ) ? $active[1] : '' ) . '</p>';
	}
}
