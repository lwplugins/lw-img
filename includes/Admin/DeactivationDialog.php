<?php
/**
 * "Keep or delete your data?" dialog on the Plugins screen.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Uninstall\DataPolicy;

/**
 * WordPress has no hook for "about to delete"; uninstall.php runs without
 * a UI. So, like Wordfence, the question is asked when the plugin's own
 * Deactivate link is clicked, the answer is stored in the options, and
 * uninstall.php reads it later. Without JavaScript the link deactivates
 * as usual and nothing is deleted.
 */
final class DeactivationDialog {

	public const ACTION = 'lw_img_uninstall_choice';

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'admin_footer-plugins.php', [ self::class, 'render' ] );
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'save' ] );
	}

	/**
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue( string $hook ): void {
		if ( 'plugins.php' !== $hook || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style( 'lw-img-deactivate', LW_IMG_URL . 'assets/css/deactivate.css', [], LW_IMG_VERSION );
		wp_enqueue_script( 'lw-img-deactivate', LW_IMG_URL . 'assets/js/deactivate.js', [], LW_IMG_VERSION, true );
		wp_localize_script(
			'lw-img-deactivate',
			'lwImgDeactivate',
			[
				'plugin' => plugin_basename( LW_IMG_FILE ),
				'nonce'  => wp_create_nonce( self::ACTION ),
				'action' => self::ACTION,
			]
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$delete = (bool) Options::get( DataPolicy::OPTION_KEY );
		?>
		<div id="lw-img-deactivate" class="lw-img-deact" hidden role="dialog" aria-modal="true" aria-labelledby="lw-img-deact-title">
			<div class="lw-img-deact-box">
				<h2 id="lw-img-deact-title"><?php esc_html_e( 'Deactivate LW Image', 'lw-img' ); ?></h2>
				<p><?php esc_html_e( 'If you later delete the plugin, what should happen to its data?', 'lw-img' ); ?></p>
				<label class="lw-img-deact-opt">
					<input type="radio" name="lw_img_delete" value="0" <?php checked( ! $delete ); ?> />
					<span><strong><?php esc_html_e( 'Keep everything (recommended)', 'lw-img' ); ?></strong><br />
					<?php esc_html_e( 'Settings, API key, pattern rules, event log and per-image statistics survive a delete and reinstall.', 'lw-img' ); ?></span>
				</label>
				<label class="lw-img-deact-opt">
					<input type="radio" name="lw_img_delete" value="1" <?php checked( $delete ); ?> />
					<span><strong><?php esc_html_e( 'Delete all LW Image data on uninstall', 'lw-img' ); ?></strong><br />
					<?php esc_html_e( 'Removes the settings, the API key, the log and the statistics table when the plugin is deleted.', 'lw-img' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Converted images and the backup originals in uploads/lw-img-backups/ are kept either way. You can change this later on the Backup tab.', 'lw-img' ); ?></p>
				<p class="lw-img-deact-actions">
					<button type="button" class="button button-primary" id="lw-img-deact-go"><?php esc_html_e( 'Deactivate', 'lw-img' ); ?></button>
					<button type="button" class="button" id="lw-img-deact-cancel"><?php esc_html_e( 'Cancel', 'lw-img' ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}

	public static function save(): void {
		check_ajax_referer( self::ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'lw-img' ) ], 403 );
		}

		$delete = isset( $_POST['delete'] ) && '1' === sanitize_key( wp_unslash( (string) $_POST['delete'] ) );

		Options::set( DataPolicy::OPTION_KEY, $delete );

		wp_send_json_success( [ 'delete' => $delete ] );
	}
}
