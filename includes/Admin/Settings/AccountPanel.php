<?php
/**
 * Account section of the General tab.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Bulk\UnoptimizedQuery;
use LightweightPlugins\Img\Stats\SiteStats;

/**
 * Renders the account tiles: balance (unlimited during open beta), the
 * free-tier gauge, and this site's own optimization total.
 */
final class AccountPanel {

	/**
	 * Render the section.
	 *
	 * @param array<string, mixed> $account Account payload from the API.
	 * @return void
	 */
	public static function render( array $account ): void {
		$free_used  = (int) ( $account['free_tier']['used'] ?? 0 );
		$free_limit = (int) ( $account['free_tier']['limit'] ?? 0 );
		$free_left  = (int) ( $account['free_tier']['remaining'] ?? 0 );
		$pct        = $free_limit > 0 ? min( 100, 100 * $free_used / $free_limit ) : 0;

		$optimized = ( new UnoptimizedQuery() )->optimized_count();
		$saved     = (int) SiteStats::get()['saved'];

		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Account', 'lw-img' );
		if ( ! empty( $account['mock'] ) ) {
			echo ' <span class="lw-img-gen-badge">' . esc_html__( 'Preview data', 'lw-img' ) . '</span>';
		}
		echo '</h3>';

		echo '<div class="lw-img-tiles lw-img-gen-tiles">';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'Balance', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-v">' . esc_html__( 'Unlimited', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-d">' . esc_html__( 'open beta — billing is not live yet', 'lw-img' ) . '</span>';
		echo '</div>';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'Free tier this month', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-v">' . esc_html( number_format_i18n( $free_used ) ) . ' <small>/ ' . esc_html( number_format_i18n( $free_limit ) ) . '</small></span>';
		echo '<span class="lw-img-gen-tierbar"><span style="width:' . esc_attr( number_format( $pct, 1, '.', '' ) ) . '%"></span></span>';
		echo '<span class="lw-img-tile-d">' . esc_html(
			sprintf(
				/* translators: %s: remaining image count. */
				__( '%s remaining', 'lw-img' ),
				number_format_i18n( $free_left )
			)
		) . '</span>';
		echo '</div>';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'Optimized on this site', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-v">' . esc_html( number_format_i18n( $optimized ) ) . '</span>';
		echo '<span class="lw-img-tile-d">' . esc_html(
			sprintf(
				/* translators: %s: human-readable saved size. */
				__( '%s saved', 'lw-img' ),
				(string) size_format( $saved, 1 )
			)
		) . ' · <a href="#stats" class="lw-img-goto">' . esc_html__( 'Stats', 'lw-img' ) . '</a></span>';
		echo '</div>';

		echo '</div>';
	}
}
