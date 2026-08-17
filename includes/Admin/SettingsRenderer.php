<?php
/**
 * Settings page HTML renderer.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin;

/**
 * Renders the tabbed settings page HTML.
 */
final class SettingsRenderer {

	/**
	 * Settings tabs in render order.
	 *
	 * @var array<int, Settings\TabInterface>
	 */
	private array $tabs;

	public function __construct( array $tabs ) {
		$this->tabs = $tabs;
	}

	public function render_page( string $settings_group ): void {
		?>
		<div class="wrap">
			<h1>
				<img src="<?php echo esc_url( LW_IMG_URL . 'assets/img/title-icon.svg' ); ?>" alt="" class="lw-title-icon" />
				<?php esc_html_e( 'LW Image', 'lw-img' ); ?>
				<span style="font-size: 13px; font-weight: 400; color: #888;">(<?php echo esc_html( LW_IMG_VERSION ); ?>)</span>
			</h1>

			<?php
			/**
			 * Auxiliary forms (rendered OUTSIDE the main settings form).
			 *
			 * Tab buttons reference these via the HTML5 `form="..."` attribute,
			 * so submits go to admin-post.php instead of options.php.
			 */
			do_action( 'lw_img_admin_auxiliary_forms' );
			?>

			<form method="post" action="options.php">
				<?php settings_fields( $settings_group ); ?>

				<div class="lw-img-settings">
					<?php $this->render_nav(); ?>

					<div class="lw-img-tab-content">
						<?php $this->render_panels(); ?>
						<?php submit_button(); ?>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	private function render_nav(): void {
		?>
		<ul class="lw-img-tabs">
			<?php foreach ( $this->tabs as $index => $tab ) : ?>
				<li>
					<a href="#<?php echo esc_attr( $tab->get_slug() ); ?>" <?php echo 0 === $index ? 'class="active"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $tab->get_icon() ); ?>"></span>
						<?php echo esc_html( $tab->get_label() ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private function render_panels(): void {
		foreach ( $this->tabs as $index => $tab ) {
			$active_class = 0 === $index ? ' active' : '';
			printf(
				'<div id="tab-%s" class="lw-img-tab-panel%s">',
				esc_attr( $tab->get_slug() ),
				esc_attr( $active_class )
			);
			$tab->render();
			echo '</div>';
		}
	}
}
