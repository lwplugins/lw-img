<?php
/**
 * Filtering and paging of the event log.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Log;

defined( 'ABSPATH' ) || exit;

/**
 * Pure slice of the ring buffer for the Log tab: filter by event type and
 * a file-name search, count per type (over the whole log, like the filter
 * chips always did), then page.
 */
final class LogQuery {

	public const DEFAULT_PER_PAGE = 25;
	public const MAX_PER_PAGE     = 100;

	/**
	 * Event types, in chip order.
	 *
	 * @var array<int, string>
	 */
	public const TYPES = [
		EventLog::STATUS_CONVERTED,
		EventLog::STATUS_SKIPPED,
		EventLog::STATUS_FAILED,
		EventLog::STATUS_RESTORED,
	];

	/**
	 * Run the query.
	 *
	 * @param array<int, mixed> $entries  Log entries, newest first.
	 * @param int               $page     1-based page.
	 * @param int               $per_page Entries per page.
	 * @param string            $type     Event type, or '' / 'all' for every type.
	 * @param string            $search   File-name substring (case-insensitive).
	 * @return array{entries: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int, counts: array<string, int>}
	 */
	public static function run( array $entries, int $page, int $per_page, string $type, string $search ): array {
		$entries  = array_values( array_filter( $entries, 'is_array' ) );
		$type     = in_array( $type, self::TYPES, true ) ? $type : '';
		$needle   = strtolower( trim( $search ) );
		$per_page = $per_page > 0 ? min( self::MAX_PER_PAGE, $per_page ) : self::DEFAULT_PER_PAGE;

		$matches = [];
		foreach ( $entries as $index => $entry ) {
			if ( self::matches( $entry, $type, $needle ) ) {
				$matches[] = $entry + [ 'id' => $index ];
			}
		}

		$total = count( $matches );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$page  = min( max( 1, $page ), $pages );

		return [
			'entries'  => array_slice( $matches, ( $page - 1 ) * $per_page, $per_page ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => $pages,
			'counts'   => self::counts( $entries ),
		];
	}

	/**
	 * Whether an entry passes the filters.
	 *
	 * @param array<string, mixed> $entry  Log entry.
	 * @param string               $type   Event type or ''.
	 * @param string               $needle Lower-case search or ''.
	 * @return bool
	 */
	private static function matches( array $entry, string $type, string $needle ): bool {
		if ( '' !== $type && ( $entry['status'] ?? '' ) !== $type ) {
			return false;
		}

		if ( '' === $needle ) {
			return true;
		}

		$file = strtolower( basename( (string) ( $entry['file'] ?? '' ) ) );

		return str_contains( $file, $needle );
	}

	/**
	 * Entries per type, plus "all".
	 *
	 * @param array<int, array<string, mixed>> $entries Log entries.
	 * @return array<string, int>
	 */
	private static function counts( array $entries ): array {
		$counts = [ 'all' => count( $entries ) ] + array_fill_keys( self::TYPES, 0 );

		foreach ( $entries as $entry ) {
			$status = (string) ( $entry['status'] ?? '' );
			if ( isset( $counts[ $status ] ) && 'all' !== $status ) {
				++$counts[ $status ];
			}
		}

		return $counts;
	}
}
