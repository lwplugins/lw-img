<?php
/**
 * Upload tab — auto-conversion settings.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

/**
 * Upload tab: auto-conversion settings.
 */
final class TabUpload implements TabInterface {

	use FieldRendererTrait;

	public function get_slug(): string {
		return 'upload';
	}

	public function get_label(): string {
		return __( 'Upload', 'lw-img' );
	}

	public function get_icon(): string {
		return 'dashicons-upload';
	}

	public function render(): void {
		?>
		<h2><?php esc_html_e( 'Auto-convert uploads', 'lw-img' ); ?></h2>
		<p><?php esc_html_e( 'When a non-WebP image is uploaded, it is sent to HelloImg and replaced with the optimized WebP. WordPress sub-sizes are then generated from the WebP source.', 'lw-img' ); ?></p>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Auto-convert', 'lw-img' ); ?></th>
				<td>
					<?php
					$this->render_checkbox_field(
						[
							'name'        => 'auto_convert',
							'label'       => __( 'Convert non-WebP uploads to WebP automatically', 'lw-img' ),
							'description' => __( 'Disable to leave uploads untouched.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th><label for="level"><?php esc_html_e( 'Optimization level', 'lw-img' ); ?></label></th>
				<td>
					<?php
					$this->render_select_field(
						[
							'name'        => 'level',
							'options'     => [
								'lossless'   => __( 'Lossless — pixel-perfect, larger files', 'lw-img' ),
								'normal'     => __( 'Normal — balanced', 'lw-img' ),
								'aggressive' => __( 'Aggressive — smaller files', 'lw-img' ),
								'ultra'      => __( 'Ultra — maximum compression', 'lw-img' ),
							],
							'description' => __( 'Higher levels save more bytes but may show visible compression.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th><label for="output_format"><?php esc_html_e( 'Output format', 'lw-img' ); ?></label></th>
				<td>
					<?php
					$this->render_select_field(
						[
							'name'        => 'output_format',
							'options'     => [
								'webp' => __( 'WebP — widest support', 'lw-img' ),
								'avif' => __( 'AVIF — smaller, modern browsers', 'lw-img' ),
							],
							'description' => __( 'The format uploads are converted to. If the converted file would not be smaller than the original, the original is kept.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Resize large images', 'lw-img' ); ?></th>
				<td>
					<?php
					$this->render_number_field(
						[
							'name'        => 'max_width',
							'min'         => '0',
							'max'         => '10000',
							'description' => __( 'Max width (px). 0 = no limit.', 'lw-img' ),
						]
					);
					$this->render_number_field(
						[
							'name'        => 'max_height',
							'min'         => '0',
							'max'         => '10000',
							'description' => __( 'Max height (px). 0 = no limit. Images are scaled down proportionally to fit; small images are never upscaled.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'EXIF metadata', 'lw-img' ); ?></th>
				<td>
					<?php
					$this->render_checkbox_field(
						[
							'name'        => 'keep_exif',
							'label'       => __( 'Keep EXIF metadata (camera info, GPS, copyright)', 'lw-img' ),
							'description' => __( 'Off by default — smaller files and removes potentially sensitive data.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Skip rules', 'lw-img' ); ?></th>
				<td>
					<?php
					$this->render_checkbox_field(
						[
							'name'  => 'skip_already_webp',
							'label' => __( 'Skip already-WebP / AVIF uploads (recommended)', 'lw-img' ),
						]
					);
					echo '<br>';
					$this->render_checkbox_field(
						[
							'name'        => 'skip_animated_gif',
							'label'       => __( 'Skip animated GIFs', 'lw-img' ),
							'description' => __( 'Off by default: animated GIFs are converted to animated WebP with frames and timing preserved. Turn on to leave animated GIFs untouched. If the animated WebP would be larger than the GIF, the original is kept automatically.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th><label for="exclusion_patterns"><?php esc_html_e( 'Exclusions', 'lw-img' ); ?></label></th>
				<td>
					<?php
					$this->render_textarea_field(
						[
							'name'        => 'exclusion_patterns',
							'placeholder' => "*-original.jpg\n2026/08/*",
							'description' => __( 'One pattern per line, * matches anything. Patterns without / match the filename; patterns with / match anywhere in the file path. Matching files are never converted.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th><label for="max_filesize_mb"><?php esc_html_e( 'Max file size (MB)', 'lw-img' ); ?></label></th>
				<td>
					<?php
					$this->render_number_field(
						[
							'name'        => 'max_filesize_mb',
							'min'         => '1',
							'max'         => '10',
							'description' => __( 'HelloImg rejects images over 10 MB. Larger uploads are skipped (original kept).', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Old URL redirect', 'lw-img' ); ?></th>
				<td>
					<?php
					$this->render_checkbox_field(
						[
							'name'        => 'redirect_missing_images',
							'label'       => __( 'Redirect old image URLs to the converted file (recommended)', 'lw-img' ),
							'description' => __( 'If a converted image is still requested by its old URL (external links, sent newsletters, search engines), respond with a 301 redirect to the new file instead of a 404.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th><label for="min_filesize_kb"><?php esc_html_e( 'Min file size (KB)', 'lw-img' ); ?></label></th>
				<td>
					<?php
					$this->render_number_field(
						[
							'name'        => 'min_filesize_kb',
							'min'         => '0',
							'max'         => '10240',
							'description' => __( 'Skip images below this size — tiny files rarely benefit from conversion. 0 = no minimum.', 'lw-img' ),
						]
					);
					?>
				</td>
			</tr>
		</table>
		<?php
	}
}
