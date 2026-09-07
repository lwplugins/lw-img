<?php
/**
 * "This run uses" card: the Upload-tab settings a bulk run applies.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Upload\Rules\RuleSet;

/**
 * Answers "does the level apply to bulk?" and "do I need to regenerate
 * thumbnails?" on the tab where people ask them. Read-only.
 */
final class BulkRunSummary {

	public function render(): void {
		$width  = (int) Options::get( 'max_width' );
		$height = (int) Options::get( 'max_height' );
		$rules  = RuleSet::from_options()->count();

		/* translators: 1: max width, 2: max height (0 = unlimited). */
		$resize_format = __( 'fit inside %1$s × %2$s px', 'lw-img' );
		$resize_text   = ( $width > 0 || $height > 0 )
			? sprintf( $resize_format, $width > 0 ? (string) $width : '∞', $height > 0 ? (string) $height : '∞' )
			: __( 'original dimensions kept', 'lw-img' );

		/* translators: %s: number of rules. */
		$rules_format = _n( '%s rule', '%s rules', $rules, 'lw-img' );
		$rules_text   = sprintf( $rules_format, number_format_i18n( $rules ) );

		$items = [
			[ __( 'Level', 'lw-img' ), ucfirst( (string) Options::get( 'level' ) ) ],
			[ __( 'Output', 'lw-img' ), strtoupper( (string) Options::get( 'output_format' ) ) ],
			[ __( 'Resize', 'lw-img' ), $resize_text ],
			[ __( 'Pattern rules', 'lw-img' ), $rules_text ],
			[ __( 'EXIF', 'lw-img' ), Options::get( 'keep_exif' ) ? __( 'kept', 'lw-img' ) : __( 'removed', 'lw-img' ) ],
			[ __( 'Backup', 'lw-img' ), Options::get( 'backup_enabled' ) ? __( 'on', 'lw-img' ) : __( 'off', 'lw-img' ) ],
		];

		echo '<div class="lw-img-run-uses">';
		echo '<span class="lw-img-k">' . esc_html__( 'This run uses your Upload settings', 'lw-img' ) . '</span>';
		echo '<ul>';
		foreach ( $items as [ $label, $value ] ) {
			printf( '<li><span>%s</span><strong>%s</strong></li>', esc_html( $label ), esc_html( $value ) );
		}
		echo '</ul>';
		echo '<p class="description">' . esc_html__( 'Thumbnails are regenerated from the converted file after every conversion — no separate regeneration needed.', 'lw-img' ) . ' <a href="#upload" class="lw-img-goto">' . esc_html__( 'Change on the Upload tab', 'lw-img' ) . '</a></p>';
		echo '</div>';
	}
}
