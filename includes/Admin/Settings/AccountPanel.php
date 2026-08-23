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
 * Renders the account tiles from the live /v1/account payload:
 * {plan, month, website: {domain, images, bytes_saved}, limit} — limit is
 * null on unlimited plans. Plus this site's own optimization total, which
 * is local data (the API counts the whole key/domain, not this install).
 */
final class AccountPanel {

	/**
	 * Render the section.
	 *
	 * @param array<string, mixed> $account Account payload from the API.
	 * @return void
	 */
	public static function render( array $account ): void {
		$dash_url  = \LightweightPlugins\Img\lw_img_dashboard_url();
		$dash_host = wp_parse_url( $dash_url, PHP_URL_HOST );
		$dash_text = is_string( $dash_host ) && '' !== $dash_host ? $dash_host : $dash_url;

		$plan   = self::plan_label( (string) ( $account['plan'] ?? '' ) );
		$limit  = is_numeric( $account['limit'] ?? null ) ? (int) $account['limit'] : null;
		$images = (int) ( $account['website']['images'] ?? 0 );
		$bytes  = (int) ( $account['website']['bytes_saved'] ?? 0 );
		$domain = (string) ( $account['website']['domain'] ?? '' );

		$optimized = ( new UnoptimizedQuery() )->optimized_count();
		$saved     = (int) SiteStats::get()['saved'];

		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Account', 'lw-img' ) . '</h3>';

		echo '<div class="lw-img-tiles lw-img-gen-tiles">';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'Plan', 'lw-img' ) . '</span>';
		echo '<span class="lw-img-tile-v">' . esc_html( '' === $plan ? '—' : $plan ) . '</span>';
		if ( null === $limit ) {
			$plan_detail = sprintf(
				/* translators: %s: dashboard host name. */
				__( 'no monthly limit · manage your plan at %s', 'lw-img' ),
				$dash_text
			);
		} else {
			$plan_detail = sprintf(
				/* translators: 1: monthly image limit, 2: dashboard host name. */
				__( '%1$s images/month · manage your plan at %2$s', 'lw-img' ),
				number_format_i18n( $limit ),
				$dash_text
			);
		}
		echo '<span class="lw-img-tile-d">' . esc_html( $plan_detail ) . '</span>';
		echo '</div>';

		echo '<div class="lw-img-tile">';
		echo '<span class="lw-img-tile-k">' . esc_html__( 'This month', 'lw-img' ) . '</span>';
		if ( null === $limit ) {
			echo '<span class="lw-img-tile-v">' . esc_html( number_format_i18n( $images ) ) . '</span>';
		} else {
			$pct = $limit > 0 ? min( 100, 100 * $images / $limit ) : 0;
			echo '<span class="lw-img-tile-v">' . esc_html( number_format_i18n( $images ) ) . ' <small>/ ' . esc_html( number_format_i18n( $limit ) ) . '</small></span>';
			echo '<span class="lw-img-gen-tierbar"><span style="width:' . esc_attr( number_format( $pct, 1, '.', '' ) ) . '%"></span></span>';
		}
		$month_detail = sprintf(
			/* translators: %s: human-readable saved size. */
			__( '%s saved via the API', 'lw-img' ),
			(string) size_format( $bytes, 1 )
		);
		if ( '' !== $domain ) {
			$month_detail .= ' · ' . $domain;
		}
		echo '<span class="lw-img-tile-d">' . esc_html( $month_detail ) . '</span>';
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

	/**
	 * Humanize the API's plan slug for display.
	 *
	 * "reseller_ai" → "Reseller AI". Underscores and hyphens both split;
	 * the "ai" token is uppercased as an initialism, everything else is
	 * ucfirst'd. Unknown future slugs degrade to readable words instead
	 * of needing a lookup table.
	 *
	 * @param string $plan Plan slug from the API.
	 * @return string Human-readable label, '' when the slug is empty.
	 */
	public static function plan_label( string $plan ): string {
		$words = array_filter( explode( '_', str_replace( '-', '_', strtolower( $plan ) ) ) );

		$words = array_map(
			static fn ( string $word ): string => 'ai' === $word ? 'AI' : ucfirst( $word ),
			$words
		);

		return implode( ' ', $words );
	}
}
