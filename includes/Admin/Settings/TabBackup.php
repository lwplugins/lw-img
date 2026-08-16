<?php
/**
 * Backup tab — original image backup and retention settings.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

/**
 * Backup tab: backup toggle and retention period.
 */
final class TabBackup implements TabInterface {

	use FieldRendererTrait;

	public function get_slug(): string {
		return 'backup';
	}

	public function get_label(): string {
		return __( 'Backup', 'lw-img' );
	}

	public function get_icon(): string {
		return 'dashicons-backup';
	}

	public function render(): void {
		?>
		<h2><?php esc_html_e( 'Original image backup', 'lw-img' ); ?></h2>
		<p><?php esc_html_e( 'Before an upload is replaced with its optimized version, the original file is moved to wp-content/uploads/lw-img-backups/. Optimized images can then be restored from the Media Library ("Restore original" row action) — thumbnails are regenerated from the restored file.', 'lw-img' ); ?></p>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Backup originals', 'lw-img' ); ?></th>
				<td>
					<?php
					$this->render_checkbox_field(
						[
							'name'        => 'backup_enabled',
							'label'       => __( 'Keep a backup of the original image (recommended)', 'lw-img' ),
							'description' => __( 'When disabled, originals are deleted after conversion and cannot be restored.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th><label for="backup_retention_days"><?php esc_html_e( 'Keep backups for (days)', 'lw-img' ); ?></label></th>
				<td>
					<?php
					$this->render_number_field(
						[
							'name'        => 'backup_retention_days',
							'min'         => '0',
							'max'         => '3650',
							'description' => __( 'Backups older than this are deleted by a daily cleanup task. Set to 0 to keep backups forever.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
		</table>
		<?php
	}
}
