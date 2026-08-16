<?php
/**
 * Bulk tab — optimize the existing Media Library.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

use LightweightPlugins\Img\Bulk\AjaxHandler;
use LightweightPlugins\Img\Bulk\UnoptimizedQuery;

/**
 * Bulk tab: counts, start button, and the progress area driven by admin.js.
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
		$query       = new UnoptimizedQuery();
		$unoptimized = $query->count();
		$optimized   = $query->optimized_count();

		?>
		<h2><?php esc_html_e( 'Bulk optimize', 'lw-img' ); ?></h2>
		<p><?php esc_html_e( 'Convert the images already in your Media Library. Images are processed a few at a time — keep this tab open until the run finishes. The upload skip rules (exclusions, max size, animated GIFs) apply here too.', 'lw-img' ); ?></p>
		<p><strong><?php esc_html_e( 'Note:', 'lw-img' ); ?></strong> <?php esc_html_e( 'converting renames files (photo.jpg becomes photo.webp). Content that embeds an image by its file URL (typically page-builder data) keeps pointing at the old name — re-select the image there, restore that image, or exclude it before running. Featured images and anything referenced by attachment ID are unaffected.', 'lw-img' ); ?></p>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Status', 'lw-img' ); ?></th>
				<td>
					<p>
						<strong><?php echo esc_html( number_format_i18n( $unoptimized ) ); ?></strong> <?php esc_html_e( 'images not optimized yet', 'lw-img' ); ?>,
						<strong><?php echo esc_html( number_format_i18n( $optimized ) ); ?></strong> <?php esc_html_e( 'already optimized', 'lw-img' ); ?>.
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Run', 'lw-img' ); ?></th>
				<td>
					<div
						id="lw-img-bulk"
						data-nonce="<?php echo esc_attr( wp_create_nonce( AjaxHandler::NONCE_ACTION ) ); ?>"
						data-remaining="<?php echo esc_attr( (string) $unoptimized ); ?>"
					>
						<button type="button" class="button button-primary" id="lw-img-bulk-start" <?php disabled( 0 === $unoptimized ); ?>>
							<?php esc_html_e( 'Optimize all', 'lw-img' ); ?>
						</button>
						<span id="lw-img-bulk-status" class="description" style="margin-left: 8px;"></span>
						<div id="lw-img-bulk-bar" class="lw-img-bulk-bar" hidden><div id="lw-img-bulk-bar-fill" class="lw-img-bulk-bar-fill"></div></div>
						<ul id="lw-img-bulk-log" class="lw-img-bulk-log"></ul>
					</div>
					<p class="description"><?php esc_html_e( 'Tip: the same run is available from the command line: wp lw-img optimize --all', 'lw-img' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
