<?php
/**
 * Repeater rows for the pattern rules on the Upload tab.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Upload\Rules\RuleSet;

/**
 * Renders the rule table, the "Add rule" button and the <template> row
 * admin.js clones for new rules. Pure markup; sanitization lives in
 * SettingsSanitizer::sanitize_rules().
 */
final class RuleListRenderer {

	/**
	 * Render the whole control.
	 *
	 * @param array<int, array<string, string>> $rules Saved rules.
	 * @return void
	 */
	public function render( array $rules ): void {
		printf( '<div id="lw-img-rules" data-next="%d">', (int) count( $rules ) );
		echo '<table class="lw-img-rules"><thead><tr>';
		echo '<th>' . esc_html__( 'Pattern', 'lw-img' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'lw-img' ) . '</th>';
		echo '<th><span class="screen-reader-text">' . esc_html__( 'Remove', 'lw-img' ) . '</span></th>';
		echo '</tr></thead><tbody id="lw-img-rules-body">';
		foreach ( array_values( $rules ) as $index => $rule ) {
			$this->row( (string) $index, $rule );
		}
		echo '</tbody></table>';
		echo '<button type="button" class="button lw-img-rule-add">' . esc_html__( 'Add rule', 'lw-img' ) . '</button>';
		echo '<template id="lw-img-rule-template">';
		$this->row(
			'__INDEX__',
			[
				'pattern' => '',
				'action'  => RuleSet::ACTION_EXCLUDE,
				'value'   => '',
			]
		);
		echo '</template>';
		echo '</div>';
	}

	/**
	 * One rule row.
	 *
	 * @param string                $index Row index or the __INDEX__ placeholder.
	 * @param array<string, string> $rule  Rule values.
	 * @return void
	 */
	private function row( string $index, array $rule ): void {
		$base    = Options::OPTION_NAME . '[pattern_rules][' . $index . ']';
		$action  = (string) ( $rule['action'] ?? RuleSet::ACTION_EXCLUDE );
		$value   = (string) ( $rule['value'] ?? '' );
		$actions = [
			RuleSet::ACTION_EXCLUDE   => __( 'Skip entirely (never sent to the API)', 'lw-img' ),
			RuleSet::ACTION_KEEP_SIZE => __( 'Keep original dimensions', 'lw-img' ),
			RuleSet::ACTION_LEVEL     => __( 'Use optimization level…', 'lw-img' ),
			RuleSet::ACTION_KEEP_EXIF => __( 'Keep EXIF metadata', 'lw-img' ),
		];
		$levels  = [
			'lossless'   => __( 'Lossless', 'lw-img' ),
			'normal'     => __( 'Normal', 'lw-img' ),
			'aggressive' => __( 'Aggressive', 'lw-img' ),
			'ultra'      => __( 'Ultra', 'lw-img' ),
		];

		echo '<tr class="lw-img-rule">';
		printf(
			'<td><input type="text" class="regular-text code lw-img-rule-pattern" name="%s[pattern]" value="%s" placeholder="*-full.jpg" aria-label="%s" /></td>',
			esc_attr( $base ),
			esc_attr( (string) ( $rule['pattern'] ?? '' ) ),
			esc_attr__( 'Pattern', 'lw-img' )
		);
		echo '<td>';
		printf( '<select name="%s[action]" class="lw-img-rule-action" aria-label="%s">', esc_attr( $base ), esc_attr__( 'Action', 'lw-img' ) );
		foreach ( $actions as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $action, $key, false ), esc_html( $label ) );
		}
		echo '</select> ';
		printf(
			'<select name="%s[value]" class="lw-img-rule-level" aria-label="%s"%s>',
			esc_attr( $base ),
			esc_attr__( 'Level', 'lw-img' ),
			RuleSet::ACTION_LEVEL === $action ? '' : ' hidden'
		);
		foreach ( $levels as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '</td>';
		printf(
			'<td><button type="button" class="button-link lw-img-rule-remove" aria-label="%s"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></td>',
			esc_attr__( 'Remove rule', 'lw-img' )
		);
		echo '</tr>';
	}
}
