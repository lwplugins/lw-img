<?php
/**
 * LW Plugins Parent Page.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin;

/**
 * Handles the LW Plugins parent menu page.
 */
final class ParentPage {

	public const SLUG = 'lw-plugins';

	private const REGISTRY_URL = 'https://raw.githubusercontent.com/lwplugins/registry/main/plugins.json';

	private const CACHE_KEY = 'lw_plugins_registry';

	private const CACHE_TTL = 43200;

	public static function get_plugins_registry(): array {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$remote = self::fetch_remote_registry();

		if ( $remote ) {
			set_transient( self::CACHE_KEY, $remote, self::CACHE_TTL );
			return $remote;
		}

		return self::get_local_fallback();
	}

	private static function fetch_remote_registry(): ?array {
		$response = wp_remote_get( self::REGISTRY_URL, [ 'timeout' => 5 ] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) && ! empty( $data ) ? $data : null;
	}

	private static function get_local_fallback(): array {
		return [
			'lw-img' => [
				'name'          => 'LW Img',
				'description'   => 'Lightweight image optimization — auto-convert uploads to WebP via HelloImg.',
				'icon'          => 'dashicons-format-image',
				'icon_color'    => '#00a876',
				'constant'      => 'LW_IMG_VERSION',
				'settings_page' => 'lw-img',
				'github'        => 'https://github.com/lwplugins/lw-img',
			],
		];
	}

	public static function maybe_register(): void {
		global $admin_page_hooks;

		if ( ! empty( $admin_page_hooks[ self::SLUG ] ) ) {
			return;
		}

		add_menu_page(
			__( 'LW Plugins', 'lw-img' ),
			__( 'LW Plugins', 'lw-img' ),
			'manage_options',
			self::SLUG,
			[ self::class, 'render' ],
			'dashicons-superhero-alt',
			80
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap lw-plugins-overview">
			<h1><?php esc_html_e( 'LW Plugins', 'lw-img' ); ?></h1>
			<p><?php esc_html_e( 'Lightweight plugins for WordPress - minimal footprint, maximum impact.', 'lw-img' ); ?></p>

			<div class="lw-plugins-cards" style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
				<?php self::render_all_plugin_cards(); ?>
				<?php do_action( 'lw_plugins_overview_cards' ); ?>
			</div>

			<div class="lw-plugins-footer" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccd0d4;">
				<p>
					<a href="https://github.com/lwplugins" target="_blank">GitHub</a> |
					<a href="https://lwplugins.com" target="_blank">Website</a>
				</p>
			</div>
		</div>
		<?php
	}

	private static function render_all_plugin_cards(): void {
		foreach ( self::get_plugins_registry() as $plugin ) {
			self::render_plugin_card( $plugin );
		}
	}

	private static function render_plugin_card( array $plugin ): void {
		$is_active = defined( $plugin['constant'] );
		?>
		<div class="lw-plugin-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; width: 300px;">
			<h2 style="margin-top: 0;">
				<span class="dashicons <?php echo esc_attr( (string) $plugin['icon'] ); ?>" style="color: <?php echo esc_attr( (string) $plugin['icon_color'] ); ?>;"></span>
				<?php echo esc_html( (string) $plugin['name'] ); ?>
				<?php if ( $is_active ) : ?>
					<span style="display: inline-block; background: #00a32a; color: #fff; font-size: 11px; padding: 2px 6px; border-radius: 3px; margin-left: 8px; vertical-align: middle;"><?php esc_html_e( 'Active', 'lw-img' ); ?></span>
				<?php endif; ?>
			</h2>
			<p><?php echo esc_html( (string) $plugin['description'] ); ?></p>
			<p>
				<?php if ( $is_active ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $plugin['settings_page'] ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Settings', 'lw-img' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( (string) $plugin['github'] ); ?>" class="button" target="_blank">
						<?php esc_html_e( 'Get Plugin', 'lw-img' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
}
