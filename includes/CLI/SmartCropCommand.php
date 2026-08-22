<?php
/**
 * WP-CLI command to re-crop existing images.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\CLI;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Upload\SmartCrop\SizeCatalog;
use LightweightPlugins\Img\Upload\SmartCrop\ThumbnailCropper;
use WP_CLI;

/**
 * Re-crop existing Media Library images with smart crop.
 *
 * Smart crop only ever runs on new uploads (see CropScheduler) — a restore,
 * a re-optimize or a thumbnail regeneration all drop the crop with no way
 * to redo it from the admin. This command is that way: explicit, per-image
 * or whole-library re-cropping, run only when asked.
 */
final class SmartCropCommand {

	/**
	 * Re-crop attachments' smart-crop sizes.
	 *
	 * The `smartcrop_enabled` toggle is deliberately NOT checked — running
	 * this command IS the intent. The API key IS checked up front, so a
	 * missing key fails once instead of every selected size on every image
	 * failing silently.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Attachment IDs to re-crop. Not combinable with --all.
	 *
	 * [--all]
	 * : Re-crop every image attachment in the Media Library.
	 *
	 * [--yes]
	 * : With --all, skip the confirmation prompt.
	 *
	 * [--sizes=<name,name>]
	 * : Override the saved smart-crop size selection for this run.
	 *
	 * [--dry-run]
	 * : List the planned jobs and the total API calls; spend nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-img smartcrop 123 456
	 *     wp lw-img smartcrop --all --yes
	 *     wp lw-img smartcrop --all --sizes=thumbnail,medium --dry-run
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$all     = isset( $assoc_args['all'] );
		$has_ids = [] !== $args;

		if ( $all === $has_ids ) {
			WP_CLI::error( 'Pass attachment IDs or --all, not both or neither.' );
		}

		$sizes = $this->resolve_sizes( $assoc_args );
		if ( [] === $sizes ) {
			WP_CLI::error( 'No smart-crop sizes selected. Pick sizes on the Upload tab, or pass --sizes.' );
		}

		if ( '' === (string) Options::get( 'api_key' ) ) {
			WP_CLI::error( 'No HelloImg API key configured.' );
		}

		$ids = $all ? $this->all_image_ids() : array_values( array_filter( array_map( 'absint', $args ) ) );

		if ( isset( $assoc_args['dry-run'] ) ) {
			$this->dry_run( $ids, $sizes );
			return;
		}

		if ( $all && ! isset( $assoc_args['yes'] ) ) {
			$total = $this->planned_calls( $ids, $sizes );
			WP_CLI::log( sprintf( '%d image(s), %d API call(s) planned.', count( $ids ), $total ) );
			WP_CLI::confirm( 'Proceed?' );
		}

		$this->run( $ids, $sizes );
	}

	/**
	 * Resolve the size names for this run: --sizes if given, else the saved
	 * selection. Each name goes through sanitize_key() and empties are
	 * dropped either way.
	 *
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return array<int, string>
	 */
	private function resolve_sizes( array $assoc_args ): array {
		if ( isset( $assoc_args['sizes'] ) ) {
			$raw = explode( ',', (string) $assoc_args['sizes'] );
		} else {
			$raw = (array) Options::get( 'smartcrop_sizes' );
		}

		$sizes = array_map( static fn( $name ): string => sanitize_key( (string) $name ), $raw );

		return array_values( array_filter( $sizes, static fn( string $name ): bool => '' !== $name ) );
	}

	/**
	 * IDs of every image attachment in the Media Library.
	 *
	 * Direct SQL, matching UnoptimizedQuery: a one-off CLI scan of the whole
	 * attachment table has no reuse to justify a WP_Query object.
	 *
	 * @return array<int, int>
	 */
	private function all_image_ids(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- one-off CLI scan of the whole attachment table, no user input in the statement.
		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type LIKE 'image/%' ORDER BY ID ASC"
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Print the planned jobs per attachment and the grand total. Reads
	 * metadata only — no API calls, no writes.
	 *
	 * @param array<int, int>    $ids   Attachment IDs.
	 * @param array<int, string> $sizes Resolved size names.
	 * @return void
	 */
	private function dry_run( array $ids, array $sizes ): void {
		$registered = wp_get_registered_image_subsizes();
		$total      = 0;

		foreach ( $ids as $id ) {
			$jobs = $this->jobs_for( $id, $sizes, $registered );

			if ( [] === $jobs ) {
				WP_CLI::log( sprintf( '#%d: nothing to crop', $id ) );
				continue;
			}

			foreach ( $jobs as $job ) {
				WP_CLI::log( sprintf( '#%d: %s (%dx%d)', $id, $job['name'], $job['width'], $job['height'] ) );
			}
			$total += count( $jobs );
		}

		WP_CLI::success( sprintf( '%d image(s), %d API call(s) planned. Nothing was spent.', count( $ids ), $total ) );
	}

	/**
	 * Total API calls SizeCatalog would plan across every attachment, for
	 * the pre-run confirmation prompt.
	 *
	 * @param array<int, int>    $ids   Attachment IDs.
	 * @param array<int, string> $sizes Resolved size names.
	 * @return int
	 */
	private function planned_calls( array $ids, array $sizes ): int {
		$registered = wp_get_registered_image_subsizes();
		$total      = 0;

		foreach ( $ids as $id ) {
			$total += count( $this->jobs_for( $id, $sizes, $registered ) );
		}

		return $total;
	}

	/**
	 * SizeCatalog's jobs for one attachment, or an empty array when it has
	 * no usable metadata yet.
	 *
	 * @param int                  $id         Attachment ID.
	 * @param array<int, string>   $sizes      Resolved size names.
	 * @param array<string, mixed> $registered wp_get_registered_image_subsizes() map.
	 * @return array<int, array{name: string, width: int, height: int, file: string}>
	 */
	private function jobs_for( int $id, array $sizes, array $registered ): array {
		$metadata = wp_get_attachment_metadata( $id );
		if ( ! is_array( $metadata ) ) {
			return [];
		}

		return SizeCatalog::jobs( $sizes, $registered, $metadata );
	}

	/**
	 * Crop every attachment, stopping the whole run on quota exhaustion.
	 *
	 * The override/restore pair is wrapped in try/finally so the filter is
	 * ALWAYS removed — including if something inside the loop throws —
	 * instead of relying on every call site remembering to clean up. The
	 * halt message is only raised (via WP_CLI::error(), which exits by
	 * default and does not run pending finally blocks) after the finally
	 * has already restored the saved sizes, so a halted run can never
	 * leave the override attached.
	 *
	 * @param array<int, int>    $ids   Attachment IDs.
	 * @param array<int, string> $sizes Resolved size names.
	 * @return void
	 */
	private function run( array $ids, array $sizes ): void {
		add_action(
			'lw_img_upload_failed',
			static function ( string $file, string $error ): void {
				WP_CLI::warning( sprintf( '%s: %s', $file, $error ) );
			},
			10,
			2
		);

		$override     = self::override_sizes( $sizes );
		$cropped      = 0;
		$failed       = 0;
		$processed    = 0;
		$halt_message = null;

		try {
			$cropper = new ThumbnailCropper();

			foreach ( $ids as $id ) {
				$summary = $cropper->crop( $id );

				++$processed;
				$cropped += $summary['cropped'];
				$failed  += $summary['failed'];

				WP_CLI::log( sprintf( '#%d: %d cropped, %d failed', $id, $summary['cropped'], $summary['failed'] ) );

				if ( $summary['halted'] ) {
					$halt_message = sprintf(
						'API quota exhausted — run halted after %d of %d image(s), %d crop(s) applied.',
						$processed,
						count( $ids ),
						$cropped
					);
					break;
				}
			}
		} finally {
			self::restore_sizes( $override );
		}

		if ( null !== $halt_message ) {
			WP_CLI::error( $halt_message );
		}

		WP_CLI::success( sprintf( 'Done. %d image(s) processed, %d crop(s) applied, %d failed.', $processed, $cropped, $failed ) );
	}

	/**
	 * Temporarily substitute the saved smart-crop sizes for this process,
	 * so ThumbnailCropper (which reads Options directly) honours --sizes.
	 * Nothing is written to the database: the filter is request-scoped and
	 * undone by restore_sizes() once the run finishes.
	 *
	 * Public and static (rather than a private instance helper) so the
	 * override/restore pair — the part of this class with real behavior —
	 * is directly unit-testable without a WP-CLI bootstrap.
	 *
	 * @param array<int, string> $sizes Resolved size names.
	 * @return callable The filter callback, to pass to restore_sizes().
	 */
	public static function override_sizes( array $sizes ): callable {
		$callback = static function ( $value ) use ( $sizes ) {
			if ( is_array( $value ) ) {
				$value['smartcrop_sizes'] = $sizes;
			}
			return $value;
		};

		add_filter( 'option_' . Options::OPTION_NAME, $callback );
		Options::clear_cache();

		return $callback;
	}

	/**
	 * Undo override_sizes().
	 *
	 * @param callable $callback Filter callback returned by override_sizes().
	 * @return void
	 */
	public static function restore_sizes( callable $callback ): void {
		remove_filter( 'option_' . Options::OPTION_NAME, $callback );
		Options::clear_cache();
	}
}
