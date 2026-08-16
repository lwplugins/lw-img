<?php
/**
 * Bulk tab — optimize the existing Media Library in the background.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Bulk\BulkJob;
use LightweightPlugins\Img\Bulk\JobHandlers;
use LightweightPlugins\Img\Bulk\StatusEndpoint;
use LightweightPlugins\Img\Bulk\StatusMeta;
use LightweightPlugins\Img\Bulk\UnoptimizedQuery;

/**
 * Bulk tab: pending/processed counts, background run controls, and the
 * progress area polled by admin.js. The run continues with the tab closed.
 */
final class TabBulk implements TabInterface {

	public function get_slug(): string {
		return 'bulk';
	}

	public function get_label(): string {
		return __( 'Bulk', 'lw-img' );
	}

	public function get_icon(): string {
		return 'dashicons-images-alt2';
	}

	public function render(): void {
		$query     = new UnoptimizedQuery();
		$pending   = $query->count();
		$optimized = $query->optimized_count();
		$counts    = StatusMeta::counts();
		$job       = BulkJob::get();
		$running   = BulkJob::is_running();

		?>
		<h2><?php esc_html_e( 'Bulk optimize', 'lw-img' ); ?></h2>
		<p><?php esc_html_e( 'Convert the images already in your Media Library. The run happens in the background (WP-Cron) — you can close this tab, the progress below keeps itself up to date while it is open. References to converted files in post content and page-builder data are rewritten automatically.', 'lw-img' ); ?></p>
		<p class="description"><?php esc_html_e( 'Note: WP-Cron only runs while the site receives visits. For very large libraries (thousands of images) the command line is the guaranteed path: wp lw-img optimize --all', 'lw-img' ); ?></p>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Status', 'lw-img' ); ?></th>
				<td>
					<p>
						<strong><?php echo esc_html( number_format_i18n( $pending ) ); ?></strong> <?php esc_html_e( 'pending', 'lw-img' ); ?> ·
						<strong><?php echo esc_html( number_format_i18n( $optimized ) ); ?></strong> <?php esc_html_e( 'optimized', 'lw-img' ); ?> ·
						<strong><?php echo esc_html( number_format_i18n( $counts[ StatusMeta::SKIPPED ] ) ); ?></strong> <?php esc_html_e( 'skipped', 'lw-img' ); ?> ·
						<strong><?php echo esc_html( number_format_i18n( $counts[ StatusMeta::FAILED ] ) ); ?></strong> <?php esc_html_e( 'failed', 'lw-img' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Run', 'lw-img' ); ?></th>
				<td>
					<div
						id="lw-img-bulk"
						data-nonce="<?php echo esc_attr( wp_create_nonce( StatusEndpoint::NONCE_ACTION ) ); ?>"
						data-running="<?php echo esc_attr( $running ? '1' : '0' ); ?>"
					>
						<?php if ( $running ) : ?>
							<a href="<?php echo esc_url( JobHandlers::url( JobHandlers::ACTION_CANCEL ) ); ?>" class="button">
								<?php esc_html_e( 'Cancel run', 'lw-img' ); ?>
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url( JobHandlers::url( JobHandlers::ACTION_START ) ); ?>" class="button button-primary<?php echo 0 === $pending ? ' disabled' : ''; ?>">
								<?php esc_html_e( 'Optimize all in background', 'lw-img' ); ?>
							</a>
						<?php endif; ?>
						<span id="lw-img-bulk-status" class="description" style="margin-left: 8px;">
						<?php
						if ( $running ) {
							esc_html_e( 'Run in progress…', 'lw-img' );
						} elseif ( BulkJob::STATE_DONE === ( $job['state'] ?? '' ) ) {
							esc_html_e( 'Last run finished.', 'lw-img' );
						}
						?>
						</span>
						<div id="lw-img-bulk-bar" class="lw-img-bulk-bar" <?php echo $running ? '' : 'hidden'; ?>><div id="lw-img-bulk-bar-fill" class="lw-img-bulk-bar-fill"></div></div>
					</div>
				</td>
			</tr>
			<?php if ( $counts[ StatusMeta::FAILED ] > 0 || $counts[ StatusMeta::SKIPPED ] > 0 ) : ?>
			<tr>
				<th><?php esc_html_e( 'Re-queue', 'lw-img' ); ?></th>
				<td>
					<?php if ( $counts[ StatusMeta::FAILED ] > 0 ) : ?>
						<a href="<?php echo esc_url( JobHandlers::url( JobHandlers::ACTION_RETRY ) ); ?>" class="button">
							<?php esc_html_e( 'Retry failed', 'lw-img' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $counts[ StatusMeta::SKIPPED ] > 0 ) : ?>
						<a href="<?php echo esc_url( JobHandlers::url( JobHandlers::ACTION_RESCAN ) ); ?>" class="button">
							<?php esc_html_e( 'Re-scan skipped', 'lw-img' ); ?>
						</a>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Skipped and failed images are not retried automatically. Re-queue them after changing settings or fixing the cause — details are in the Log tab.', 'lw-img' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}
}
