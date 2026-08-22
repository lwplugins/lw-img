<?php
/**
 * Upload tab — auto-conversion settings, grouped by question.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Admin\Settings;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;

/**
 * A master toggle with a pipeline strip, then Conversion / Size limits /
 * Skip & exclude / After conversion sections. When auto-convert is off,
 * the sections dim (admin.js keeps this live).
 */
final class TabUpload implements TabInterface {

	use FieldRendererTrait;
	use ControlRendererTrait;

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
		echo '<h2>' . esc_html__( 'Upload', 'lw-img' ) . '</h2>';
		echo '<p class="lw-img-sub">' . esc_html__( 'What happens to every new image upload.', 'lw-img' ) . '</p>';

		$this->render_master();

		echo '<div class="lw-img-up-sections' . ( Options::get( 'auto_convert' ) ? '' : ' lw-img-up-dim' ) . '">';
		$this->render_conversion();
		$this->render_size_limits();
		$this->render_skip_exclude();
		$this->render_after();
		$this->render_smartcrop();
		echo '</div>';
	}

	/**
	 * Master toggle card with the pipeline strip.
	 *
	 * @return void
	 */
	private function render_master(): void {
		$on = (bool) Options::get( 'auto_convert' );

		echo '<div class="lw-img-up-hero">';
		echo '<div class="lw-img-up-master">';
		$this->render_switch(
			[
				'name'  => 'auto_convert',
				'label' => __( 'Auto-convert uploads', 'lw-img' ),
			]
		);
		printf(
			'<span class="lw-img-up-state%s" id="lw-img-up-state" data-on="%s" data-off="%s">%s</span>',
			$on ? '' : ' lw-img-up-state-off',
			esc_attr__( 'On', 'lw-img' ),
			esc_attr__( 'Off — uploads are left untouched', 'lw-img' ),
			esc_html( $on ? __( 'On', 'lw-img' ) : __( 'Off — uploads are left untouched', 'lw-img' ) )
		);
		echo '</div>';

		$steps = [
			[ 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12', __( 'Upload', 'lw-img' ) ],
			[ 'M13 2 3 14h9l-1 8 10-12h-9l1-8z', __( 'Convert', 'lw-img' ) ],
			[ 'M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2zM17 21v-8H7v8M7 3v5h8', __( 'Back up original', 'lw-img' ) ],
			[ 'M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM10 8.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zM21 15l-5-5L5 21', __( 'Thumbnails', 'lw-img' ) ],
		];

		echo '<div class="lw-img-up-pipeline">';
		foreach ( $steps as $index => $step ) {
			if ( $index > 0 ) {
				echo '<span class="lw-img-up-arrow" aria-hidden="true">&rarr;</span>';
			}
			printf(
				'<span class="lw-img-up-step"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%s"/></svg>%s</span>',
				esc_attr( $step[0] ),
				esc_html( $step[1] )
			);
		}
		echo '</div></div>';
	}

	/**
	 * Format, level, EXIF.
	 *
	 * @return void
	 */
	private function render_conversion(): void {
		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Conversion', 'lw-img' ) . '</h3>';

		$this->row_open( __( 'Output format', 'lw-img' ) );
		$this->render_segment(
			[
				'name'    => 'output_format',
				'options' => [
					'webp' => [ 'WebP', __( 'WebP — widest support. If the converted file would not be smaller than the original, the original is kept.', 'lw-img' ) ],
					'avif' => [ 'AVIF', __( 'AVIF — smaller files, modern browsers. If the converted file would not be smaller than the original, the original is kept.', 'lw-img' ) ],
				],
			]
		);
		$this->row_close();

		$this->row_open( __( 'Optimization level', 'lw-img' ) );
		$this->render_segment(
			[
				'name'    => 'level',
				'options' => [
					'lossless'   => [ __( 'Lossless', 'lw-img' ), __( 'Lossless — pixel-perfect, larger files.', 'lw-img' ) ],
					'normal'     => [ __( 'Normal', 'lw-img' ), __( 'Normal — balanced quality and size.', 'lw-img' ) ],
					'aggressive' => [ __( 'Aggressive', 'lw-img' ), __( 'Aggressive — smaller files, slight compression may show.', 'lw-img' ) ],
					'ultra'      => [ __( 'Ultra', 'lw-img' ), __( 'Ultra — maximum compression, visible on detailed images.', 'lw-img' ) ],
				],
			]
		);
		$this->row_close();

		$this->row_open( __( 'EXIF metadata', 'lw-img' ) );
		$this->render_switch(
			[
				'name'        => 'keep_exif',
				'label'       => __( 'Keep EXIF metadata', 'lw-img' ),
				'description' => __( 'Camera info, GPS, copyright. Off by default — smaller files and removes potentially sensitive data.', 'lw-img' ),
			]
		);
		$this->row_close();
	}

	/**
	 * Resize and file-size range.
	 *
	 * @return void
	 */
	private function render_size_limits(): void {
		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Size limits', 'lw-img' ) . '</h3>';

		$this->row_open( __( 'Resize large images', 'lw-img' ) );
		echo '<span class="lw-img-up-dims">';
		$this->render_number_field(
			[
				'name'       => 'max_width',
				'min'        => '0',
				'max'        => '10000',
				'aria_label' => __( 'Maximum width in pixels', 'lw-img' ),
			]
		);
		echo '<span class="lw-img-up-x">&times;</span>';
		$this->render_number_field(
			[
				'name'       => 'max_height',
				'min'        => '0',
				'max'        => '10000',
				'aria_label' => __( 'Maximum height in pixels', 'lw-img' ),
			]
		);
		echo '<span class="lw-img-up-x">px</span></span>';
		echo '<p class="description">' . esc_html__( 'Images are scaled down proportionally to fit; small images are never upscaled. 0 = no limit.', 'lw-img' ) . '</p>';
		$this->row_close();

		$this->row_open( __( 'File size range', 'lw-img' ) );
		echo '<span class="lw-img-up-dims">';
		$this->render_number_field(
			[
				'name'       => 'min_filesize_kb',
				'min'        => '0',
				'max'        => '10240',
				'aria_label' => __( 'Minimum file size in kilobytes', 'lw-img' ),
			]
		);
		echo '<span class="lw-img-up-x">' . esc_html__( 'KB min', 'lw-img' ) . '</span><span class="lw-img-up-x">&mdash;</span>';
		$this->render_number_field(
			[
				'name'       => 'max_filesize_mb',
				'min'        => '1',
				'max'        => '10',
				'aria_label' => __( 'Maximum file size in megabytes', 'lw-img' ),
			]
		);
		echo '<span class="lw-img-up-x">' . esc_html__( 'MB max', 'lw-img' ) . '</span></span>';
		echo '<p class="description">' . esc_html__( 'Files outside the range are skipped (original kept). Tiny files rarely benefit; the API rejects images over 10 MB.', 'lw-img' ) . '</p>';
		$this->row_close();
	}

	/**
	 * Skip rules and exclusion patterns.
	 *
	 * @return void
	 */
	private function render_skip_exclude(): void {
		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Skip & exclude', 'lw-img' ) . '</h3>';

		$this->row_open( __( 'Skip rules', 'lw-img' ) );
		$this->render_switch(
			[
				'name'        => 'skip_already_webp',
				'label'       => __( 'Skip already-WebP / AVIF uploads', 'lw-img' ),
				'description' => __( 'Recommended — no API call, no credit usage.', 'lw-img' ),
			]
		);
		$this->render_switch(
			[
				'name'        => 'skip_animated_gif',
				'label'       => __( 'Skip animated GIFs', 'lw-img' ),
				'description' => __( 'Off by default: animated GIFs become animated WebP with frames and timing preserved. If the WebP would be larger, the original is kept automatically.', 'lw-img' ),
			]
		);
		$this->row_close();

		$this->row_open( __( 'Exclusions', 'lw-img' ) );
		$this->render_textarea_field(
			[
				'name'        => 'exclusion_patterns',
				'placeholder' => "*-original.jpg\n2026/08/*",
				'aria_label'  => __( 'Exclusion patterns, one per line', 'lw-img' ),
			]
		);
		echo '<span class="lw-img-up-hints">' . esc_html__( 'Examples:', 'lw-img' );
		foreach ( [ '*-logo.png', '2026/08/*', 'clients/*/raw-*' ] as $example ) {
			echo ' <button type="button" class="lw-img-up-hint" data-pattern="' . esc_attr( $example ) . '"><code>' . esc_html( $example ) . '</code></button>';
		}
		echo '</span>';
		echo '<p class="description">' . esc_html__( 'One pattern per line, * matches anything. Patterns without / match the filename; with / they match anywhere in the path. Matching files are never converted.', 'lw-img' ) . '</p>';
		$this->row_close();
	}

	/**
	 * Post-conversion behavior.
	 *
	 * @return void
	 */
	private function render_after(): void {
		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'After conversion', 'lw-img' ) . '</h3>';

		$this->row_open( __( 'Old URL redirect', 'lw-img' ) );
		$this->render_switch(
			[
				'name'        => 'redirect_missing_images',
				'label'       => __( 'Redirect old image URLs to the converted file', 'lw-img' ),
				'description' => __( 'Recommended. External links, sent newsletters, and search engines still pointing at photo.jpg get a 301 to photo.webp instead of a 404.', 'lw-img' ),
			]
		);
		$this->row_close();
	}

	/**
	 * Smart crop: opt-in subject-aware re-cropping of selected thumbnails.
	 *
	 * @return void
	 */
	private function render_smartcrop(): void {
		echo '<h3 class="lw-img-gen-heading">' . esc_html__( 'Smart crop', 'lw-img' ) . '</h3>';

		$this->render_switch(
			[
				'name'        => 'smartcrop_enabled',
				'label'       => __( 'Smart-crop thumbnails', 'lw-img' ),
				'description' => __( 'Re-crop the selected thumbnail sizes around the subject instead of the centre. Each selected size costs one extra API call per upload. Runs in the background right after the upload; on system-cron sites the cropped thumbnails appear within the cron interval.', 'lw-img' ),
			]
		);

		// Hidden sentinel: without it, unchecking every box would post no
		// smartcrop_sizes key at all and the old selection would survive.
		printf( '<input type="hidden" name="%s[smartcrop_sizes]" value="" />', esc_attr( Options::OPTION_NAME ) );

		$registered = wp_get_registered_image_subsizes();
		$selected   = array_map( 'strval', (array) Options::get( 'smartcrop_sizes' ) );
		$has_crops  = false;

		echo '<div class="lw-img-sc-sizes" id="lw-img-sc-sizes">';
		foreach ( $registered as $name => $size ) {
			if ( empty( $size['crop'] ) ) {
				continue;
			}
			$has_crops = true;
			printf(
				'<label class="lw-img-sc-size"><input type="checkbox" name="%1$s[smartcrop_sizes][]" value="%2$s" %3$s /> <span>%2$s</span> <span class="lw-img-sc-dims">%4$d &times; %5$d</span></label>',
				esc_attr( Options::OPTION_NAME ),
				esc_attr( (string) $name ),
				checked( in_array( (string) $name, $selected, true ), true, false ),
				(int) $size['width'],
				(int) $size['height']
			);
		}
		echo '</div>';

		if ( ! $has_crops ) {
			echo '<p class="description">' . esc_html__( 'No hard-cropped thumbnail sizes are registered on this site — smart crop has nothing to work on. Sizes that scale (keep the aspect ratio) never need it.', 'lw-img' ) . '</p>';
			return;
		}

		printf(
			'<p class="description lw-img-sc-cost" id="lw-img-sc-cost" data-template="%s"></p>',
			/* translators: %1$s: number of selected sizes, %2$s: total API calls per upload. */
			esc_attr__( 'Cost per upload: 1 + %1$s = %2$s API calls.', 'lw-img' )
		);
	}

	/**
	 * Open a label + control row.
	 *
	 * @param string $label Row label.
	 * @return void
	 */
	private function row_open( string $label ): void {
		echo '<div class="lw-img-up-row"><span class="lw-img-up-label">' . esc_html( $label ) . '</span><div class="lw-img-up-ctl">';
	}

	/**
	 * Close a row.
	 *
	 * @return void
	 */
	private function row_close(): void {
		echo '</div></div>';
	}
}
