<?php
/**
 * Field Renderer Trait.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Options;

trait FieldRendererTrait {

	protected function render_text_field( array $args ): void {
		printf(
			'<input type="%5$s" id="%1$s" name="%2$s[%1$s]" value="%3$s" placeholder="%4$s" class="regular-text" />',
			esc_attr( (string) $args['name'] ),
			esc_attr( Options::OPTION_NAME ),
			esc_attr( (string) Options::get( (string) $args['name'] ) ),
			esc_attr( (string) ( $args['placeholder'] ?? '' ) ),
			esc_attr( (string) ( $args['type'] ?? 'text' ) )
		);
		$this->render_description( (string) ( $args['description'] ?? '' ) );
	}

	protected function render_checkbox_field( array $args ): void {
		printf(
			'<label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( (string) $args['name'] ),
			esc_attr( Options::OPTION_NAME ),
			checked( Options::get( (string) $args['name'] ), true, false ),
			esc_html( (string) ( $args['label'] ?? '' ) )
		);
		$this->render_description( (string) ( $args['description'] ?? '' ) );
	}

	protected function render_select_field( array $args ): void {
		$current = (string) Options::get( (string) $args['name'] );
		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( (string) $args['name'] ),
			esc_attr( Options::OPTION_NAME )
		);
		foreach ( (array) ( $args['options'] ?? [] ) as $value => $label ) {
			printf(
				'<option value="%1$s" %3$s>%2$s</option>',
				esc_attr( (string) $value ),
				esc_html( (string) $label ),
				selected( $current, (string) $value, false )
			);
		}
		echo '</select>';
		$this->render_description( (string) ( $args['description'] ?? '' ) );
	}

	protected function render_number_field( array $args ): void {
		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="small-text" />',
			esc_attr( (string) $args['name'] ),
			esc_attr( Options::OPTION_NAME ),
			esc_attr( (string) Options::get( (string) $args['name'] ) ),
			esc_attr( (string) ( $args['min'] ?? '' ) ),
			esc_attr( (string) ( $args['max'] ?? '' ) ),
			esc_attr( (string) ( $args['step'] ?? '1' ) )
		);
		$this->render_description( (string) ( $args['description'] ?? '' ) );
	}

	private function render_description( string $desc ): void {
		if ( '' === $desc ) {
			return;
		}
		printf( '<p class="description">%s</p>', esc_html( $desc ) );
	}
}
