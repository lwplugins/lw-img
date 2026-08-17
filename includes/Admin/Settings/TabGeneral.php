<?php
/**
 * General tab — connection, account, defaults at a glance.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Api\Client;
use LightweightPlugins\Img\Options;
use Throwable;

/**
 * Status-first layout: a connection hero (pill + key field + test), the
 * account tiles and current-defaults strip when connected, a three-step
 * onboarding when no key is set, and the advanced timeout at the end.
 */
final class TabGeneral implements TabInterface {

	use FieldRendererTrait;

	public function get_slug(): string {
		return 'general';
	}

	public function get_label(): string {
		return __( 'General', 'lw-img' );
	}

	public function get_icon(): string {
		return 'dashicons-admin-generic';
	}

	public function render(): void {
		$api_key = (string) Options::get( 'api_key' );
		$account = null;
		$error   = '';

		if ( '' !== $api_key ) {
			try {
				$account = ( new Client() )->get_account();
			} catch ( Throwable $e ) {
				$error = $e->getMessage();
			}
		}

		$this->render_hero( $api_key, null !== $account, $error );

		if ( null !== $account ) {
			AccountPanel::render( $account );
			$this->render_defaults();
		} elseif ( '' === $api_key ) {
			$this->render_steps();
		}

		$this->render_advanced();
	}

	/**
	 * Connection hero: status pill, key field, test button.
	 *
	 * @param string $api_key   Saved API key.
	 * @param bool   $connected Whether the account fetch succeeded.
	 * @param string $error     Fetch error message, if any.
	 * @return void
	 */
	private function render_hero( string $api_key, bool $connected, string $error ): void {
		if ( $connected ) {
			$pill = [ 'ok', __( 'Connected', 'lw-img' ) ];
			$lead = __( 'New uploads are converted automatically. The key is stored in wp_options.', 'lw-img' );
		} elseif ( '' === $api_key ) {
			$pill = [ 'info', __( 'Not connected', 'lw-img' ) ];
			$lead = __( 'Paste an API key to enable conversion. Free tier: 1,000 images / month.', 'lw-img' );
		} else {
			$pill = [ 'critical', __( 'Error', 'lw-img' ) ];
			$lead = $error;
		}

		echo '<div class="lw-img-gen-hero">';
		printf(
			'<div class="lw-img-gen-head"><span class="lw-img-health-pill lw-img-health-pill--%s">%s</span> <strong>%s</strong></div>',
			esc_attr( $pill[0] ),
			esc_html( $pill[1] ),
			esc_html__( 'HelloImg API', 'lw-img' )
		);
		echo '<p class="lw-img-gen-lead">' . esc_html( $lead ) . '</p>';

		echo '<div class="lw-img-gen-keyrow">';
		$this->render_text_field(
			[
				'name'        => 'api_key',
				'type'        => 'password',
				'placeholder' => 'himg_...',
			]
		);
		printf(
			'<button type="button" class="button lw-img-key-toggle" aria-pressed="false" title="%1$s" aria-label="%1$s" data-label-show="%1$s" data-label-hide="%2$s"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button>',
			esc_attr__( 'Show key', 'lw-img' ),
			esc_attr__( 'Hide key', 'lw-img' )
		);

		if ( '' === $api_key ) {
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save key', 'lw-img' ) . '</button>';
		} else {
			printf(
				'<a href="%s" class="button">%s</a>',
				esc_url( add_query_arg( 'lw-img-retest', (string) time(), admin_url( 'admin.php?page=lw-img' ) ) . '#general' ),
				esc_html__( 'Test connection', 'lw-img' )
			);
		}
		echo '</div>';

		echo '<p class="lw-img-gen-hint">'
			. esc_html__( 'Open beta: keys are not validated yet — any value enables conversion. Get one at', 'lw-img' )
			. ' <a href="https://dashboard.helloimg.io/api-keys" target="_blank" rel="noopener">dashboard.helloimg.io/api-keys</a> · <a href="#tester" class="lw-img-goto">'
			. esc_html__( 'Full environment checks on the Tester tab', 'lw-img' )
			. '</a></p>';
		echo '</div>';
	}

	/**
	 * Current defaults as chips linking to their tabs.
	 *
	 * @return void
	 */
	private function render_defaults(): void {
		$retention = (int) Options::get( 'backup_retention_days' );
		$chips     = [
			[ 'upload', 'avif' === Options::get( 'output_format' ) ? 'AVIF' : 'WebP' ],
			[ 'upload', (string) Options::get( 'level' ) . ' ' . __( 'level', 'lw-img' ) ],
			[ 'upload', Options::get( 'keep_exif' ) ? __( 'EXIF kept', 'lw-img' ) : __( 'EXIF stripped', 'lw-img' ) ],
			[
				'backup',
				Options::get( 'backup_enabled' )
					/* translators: %s: retention description (number of days or "forever"). */
					? sprintf( __( 'backups on · %s', 'lw-img' ), 0 === $retention ? __( 'forever', 'lw-img' ) : sprintf( /* translators: %d: number of days. */ __( '%d days', 'lw-img' ), $retention ) )
					: __( 'backups off', 'lw-img' ),
			],
			[
				'bulk',
				/* translators: %s: speed profile name. */
				sprintf( __( 'bulk speed: %s', 'lw-img' ), (string) Options::get( 'bulk_speed', 'normal' ) ),
			],
		];

		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Current defaults', 'lw-img' ) . '</h3>';
		echo '<div class="lw-img-gen-defaults"><span class="lw-img-gen-defaults-label">' . esc_html__( 'New uploads:', 'lw-img' ) . '</span>';
		foreach ( $chips as $chip ) {
			printf(
				'<a class="lw-img-gen-chip lw-img-goto" href="#%1$s">%2$s <span>%3$s</span></a>',
				esc_attr( $chip[0] ),
				esc_html( $chip[1] ),
				esc_html( ucfirst( $chip[0] ) )
			);
		}
		echo '</div>';
	}

	/**
	 * First-run onboarding steps.
	 *
	 * @return void
	 */
	private function render_steps(): void {
		$steps = [
			[
				__( 'Add your API key', 'lw-img' ),
				__( 'Open beta: keys are not validated yet, so any value works for now.', 'lw-img' ),
			],
			[
				__( 'Upload as usual', 'lw-img' ),
				__( 'New JPEG / PNG / HEIC / TIFF / BMP / GIF uploads become WebP automatically — originals are backed up first, and nothing ever fails because of LW Img.', 'lw-img' ),
			],
			[
				__( 'Optimize the existing library', 'lw-img' ),
				__( 'The Bulk tab converts everything already in the Media Library, in the background. The Tester tab checks your server first.', 'lw-img' ),
			],
		];

		echo '<ol class="lw-img-gen-steps">';
		foreach ( $steps as $index => $step ) {
			printf(
				'<li><span class="lw-img-gen-num" aria-hidden="true">%d</span><div><strong>%s</strong><p>%s</p></div></li>',
				(int) $index + 1,
				esc_html( $step[0] ),
				esc_html( $step[1] )
			);
		}
		echo '</ol>';

		echo '<p class="description">' . esc_html__( 'Everything is reversible: originals live in uploads/lw-img-backups/ and can be restored per image from the Media Library.', 'lw-img' ) . '</p>';
	}

	/**
	 * Advanced settings.
	 *
	 * @return void
	 */
	private function render_advanced(): void {
		?>
		<h3 class="lw-img-gen-heading"><?php esc_html_e( 'Advanced', 'lw-img' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="request_timeout"><?php esc_html_e( 'Request timeout (s)', 'lw-img' ); ?></label></th>
				<td>
					<?php
					$this->render_number_field(
						[
							'name'        => 'request_timeout',
							'min'         => '5',
							'max'         => '120',
							'description' => __( 'How long to wait for the API per image. Slow jobs past the API\'s own 30 s window are polled automatically before giving up.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
		</table>
		<?php
	}
}
