<?php
/**
 * `wp lw-img list` — attachments by LW Image status.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\CLI;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Db\ImageRepository;
use WP_CLI;

/**
 * List attachments the plugin has optimized, skipped or failed on.
 */
final class ListCommand {

	private const STATUSES = [
		ImageRepository::STATUS_OPTIMIZED,
		ImageRepository::STATUS_SKIPPED,
		ImageRepository::STATUS_FAILED,
	];

	private const FIELDS = [ 'id', 'file', 'status', 'detail', 'orig_size', 'new_size' ];

	/**
	 * List attachments by LW Image status.
	 *
	 * ## OPTIONS
	 *
	 * --status=<status>
	 * : optimized, skipped or failed.
	 *
	 * [--limit=<number>]
	 * : Max rows (default 100).
	 *
	 * [--format=<format>]
	 * : table (default), csv, json, yaml, or count.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-img list --status=skipped
	 *     wp lw-img list --status=failed --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$status = sanitize_key( (string) ( $assoc_args['status'] ?? '' ) );
		if ( ! self::valid_status( $status ) ) {
			WP_CLI::error( 'Pass --status=optimized, --status=skipped or --status=failed.' );
		}

		$limit  = max( 1, (int) ( $assoc_args['limit'] ?? 100 ) );
		$format = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		WP_CLI\Utils\format_items( $format, self::rows( ImageRepository::list_by_status( $status, $limit ) ), self::FIELDS );
	}

	/**
	 * Whether a status is one this command accepts.
	 *
	 * @param string $status Status to check.
	 * @return bool
	 */
	public static function valid_status( string $status ): bool {
		return in_array( $status, self::STATUSES, true );
	}

	/**
	 * Shape repository rows for format_items().
	 *
	 * @param array<int, array<string, mixed>> $records Repository rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rows( array $records ): array {
		$rows = [];

		foreach ( $records as $record ) {
			$id     = (int) ( $record['attachment_id'] ?? 0 );
			$rows[] = [
				'id'        => $id,
				'file'      => basename( (string) get_attached_file( $id ) ),
				'status'    => (string) ( $record['status'] ?? '' ),
				'detail'    => (string) ( $record['detail'] ?? '' ),
				'orig_size' => (int) ( $record['orig_size'] ?? 0 ),
				'new_size'  => (int) ( $record['new_size'] ?? 0 ),
			];
		}

		return $rows;
	}
}
