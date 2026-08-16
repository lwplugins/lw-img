<?php
/**
 * Handles the "Clear log" admin-post action.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Log;

/**
 * Handles the admin-post action that clears the event log.
 */
final class ClearHandler {

	public const ACTION  = 'lw_img_clear_log';
	public const FORM_ID = 'lw-img-clear-log-form';

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
		add_action( 'lw_img_admin_auxiliary_forms', [ self::class, 'render_form' ] );
	}

	public static function render_form(): void {
		?>
		<form id="<?php echo esc_attr( self::FORM_ID ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<?php wp_nonce_field( self::ACTION ); ?>
		</form>
		<?php
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'lw-img' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( self::ACTION );

		EventLog::clear();

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => 'lw-img',
					'cleared' => '1',
				],
				admin_url( 'admin.php' )
			) . '#tab-log'
		);
		exit;
	}
}
