<?php
/**
 * Warns when another image optimizer plugin is active.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * Warning only — nothing is blocked or deactivated. Shown on the LW Image
 * settings page, the Media Library, and the Plugins screen; dismissible,
 * and it returns if the set of active competitors changes.
 */
final class CompetitorNotice {

	public const DISMISS_ACTION = 'lw_img_dismiss_competitors';

	public const OPTION_NAME = 'lw_img_competitor_notice_dismissed';

	/**
	 * Hook the notice and its dismiss action.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_notices', [ self::class, 'maybe_render' ] );
		add_action( 'admin_post_' . self::DISMISS_ACTION, [ self::class, 'dismiss' ] );
	}

	/**
	 * Render the warning when an active competitor is detected.
	 *
	 * @return void
	 */
	public static function maybe_render(): void {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_relevant_screen() ) {
			return;
		}

		$active = CompetitorRegistry::active_competitors();
		if ( [] === $active ) {
			return;
		}

		if ( self::signature( $active ) === (string) get_option( self::OPTION_NAME, '' ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg( 'action', self::DISMISS_ACTION, admin_url( 'admin-post.php' ) ),
			self::DISMISS_ACTION
		);

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'LW Image:', 'lw-img' ),
			esc_html(
				sprintf(
					/* translators: %s: comma-separated plugin names. */
					__( 'another image optimizer is active (%s). Running two optimizers on the same library leads to double conversions and wasted credits — please deactivate one of them.', 'lw-img' ),
					implode( ', ', $active )
				)
			),
			esc_html__( 'Images already optimized by them are left untouched by LW Image.', 'lw-img' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss this notice', 'lw-img' )
		);
	}

	/**
	 * Persist the dismissal for the current set of active competitors.
	 *
	 * @return void
	 */
	public static function dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'lw-img' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( self::DISMISS_ACTION );

		update_option( self::OPTION_NAME, self::signature( CompetitorRegistry::active_competitors() ), false );

		$referer = wp_get_referer();
		wp_safe_redirect( false !== $referer ? $referer : admin_url() );
		exit;
	}

	/**
	 * Fingerprint of the active competitor set — the dismissal is only
	 * valid while this set stays the same.
	 *
	 * @param array<string, string> $active Active competitors.
	 * @return string
	 */
	private static function signature( array $active ): string {
		$slugs = array_keys( $active );
		sort( $slugs );

		return md5( implode( '|', $slugs ) );
	}

	/**
	 * Whether the current screen should carry the notice.
	 *
	 * @return bool
	 */
	private static function is_relevant_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( null === $screen ) {
			return false;
		}

		return in_array( $screen->id, [ 'upload', 'plugins' ], true )
			|| str_ends_with( $screen->id, '_page_lw-img' );
	}
}
