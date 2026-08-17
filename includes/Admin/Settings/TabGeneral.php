<?php
/**
 * General tab — API key + connection test.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Api\ApiException;
use LightweightPlugins\Img\Api\Client;

/**
 * General tab: API key field and account status.
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
		?>
		<h2><?php esc_html_e( 'HelloImg API', 'lw-img' ); ?></h2>

		<table class="form-table">
			<tr>
				<th><label for="api_key"><?php esc_html_e( 'API Key', 'lw-img' ); ?></label></th>
				<td>
					<?php
					$this->render_text_field(
						[
							'name'        => 'api_key',
							'type'        => 'password',
							'placeholder' => 'himg_...',
							'description' => __( 'Stored in wp_options. HelloImg is in open beta: keys are not validated yet, so any value enables conversion. Key validation will arrive later.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
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

		<?php $this->render_account_status(); ?>
		<?php
	}

	private function render_account_status(): void {
		echo '<h3>' . esc_html__( 'Account', 'lw-img' ) . '</h3>';

		try {
			$client  = new Client();
			$account = $client->get_account();
		} catch ( ApiException $e ) {
			printf(
				'<p><span style="color:#d63638;">%s</span> <code>%s</code></p>',
				esc_html__( 'Could not load account info.', 'lw-img' ),
				esc_html( $e->getMessage() )
			);
			return;
		}

		if ( ! empty( $account['mock'] ) ) {
			printf(
				'<p><span style="display:inline-block;background:#dba617;color:#1d2327;font-size:11px;padding:2px 6px;border-radius:3px;">%s</span> %s</p>',
				esc_html__( 'Preview data', 'lw-img' ),
				esc_html__( 'Billing is not live yet — the figures below are placeholders, identical for every account.', 'lw-img' )
			);
		}

		$balance    = (string) ( $account['balance']['amount'] ?? '$0.00' );
		$credits    = (int) ( $account['balance']['credits_remaining'] ?? 0 );
		$free_used  = (int) ( $account['free_tier']['used'] ?? 0 );
		$free_limit = (int) ( $account['free_tier']['limit'] ?? 0 );
		$free_left  = (int) ( $account['free_tier']['remaining'] ?? 0 );

		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Balance', 'lw-img' ); ?></th>
				<td>
					<strong><?php echo esc_html( $balance ); ?></strong>
					<span class="description">(<?php echo esc_html( number_format_i18n( $credits ) ); ?> <?php esc_html_e( 'credits', 'lw-img' ); ?>)</span>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Free tier this month', 'lw-img' ); ?></th>
				<td>
					<?php
					printf(
						/* translators: 1: used count, 2: total limit, 3: remaining count */
						esc_html__( '%1$s / %2$s used (%3$s remaining)', 'lw-img' ),
						esc_html( number_format_i18n( $free_used ) ),
						esc_html( number_format_i18n( $free_limit ) ),
						esc_html( number_format_i18n( $free_left ) )
					);
					?>
				</td>
			</tr>
		</table>
		<?php
	}
}
