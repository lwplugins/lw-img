<?php
/**
 * The Stats tab's data.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Pure shaping of SiteStats figures: savings hero, tiles, biggest wins and
 * the leftovers other optimizers left on disk.
 */
final class StatsView {

	/**
	 * Seconds the savings figures are cached.
	 */
	public const CACHE_TTL = 3600;

	/**
	 * Build the response.
	 *
	 * @param array<string, mixed> $stats          SiteStats::get() result.
	 * @param int                  $retention_days Backup retention setting.
	 * @return array<string, mixed>
	 */
	public static function build( array $stats, int $retention_days ): array {
		$count    = (int) ( $stats['count'] ?? 0 );
		$saved    = (int) ( $stats['saved'] ?? 0 );
		$library  = (int) ( $stats['library_total'] ?? 0 );
		$backup   = is_array( $stats['backup'] ?? null ) ? $stats['backup'] : [];
		$original = (int) ( $stats['original'] ?? 0 );

		return [
			'count'           => $count,
			'original'        => $original,
			'optimized'       => (int) ( $stats['optimized'] ?? 0 ),
			'saved'           => $saved,
			'percent'         => round( (float) ( $stats['percent'] ?? 0 ), 1 ),
			'average'         => $count > 0 ? (int) round( $saved / $count ) : 0,
			'library_total'   => $library,
			'library_percent' => $library > 0 ? round( min( 100, 100 * $count / $library ), 1 ) : 0.0,
			'backup'          => [
				'bytes' => (int) ( $backup['bytes'] ?? 0 ),
				'files' => (int) ( $backup['files'] ?? 0 ),
			],
			'retention_days'  => $retention_days,
			'wins'            => self::wins( is_array( $stats['wins'] ?? null ) ? $stats['wins'] : [] ),
			'generated_at'    => (int) ( $stats['generated_at'] ?? 0 ),
			'cache_ttl'       => self::CACHE_TTL,
			'leftovers'       => self::leftovers(
				is_array( $stats['leftovers'] ?? null ) ? $stats['leftovers'] : [],
				(int) ( $stats['scanned_at'] ?? 0 )
			),
		];
	}

	/**
	 * Biggest wins with their saving.
	 *
	 * @param array<int, mixed> $wins SiteStats wins.
	 * @return array<int, array{file: string, original: int, new: int, saved: int, percent: float}>
	 */
	private static function wins( array $wins ): array {
		$rows = [];

		foreach ( $wins as $win ) {
			if ( ! is_array( $win ) ) {
				continue;
			}

			$original = (int) ( $win['original'] ?? 0 );
			$new      = (int) ( $win['new'] ?? 0 );
			$rows[]   = [
				'file'     => (string) ( $win['file'] ?? '' ),
				'original' => $original,
				'new'      => $new,
				'saved'    => max( 0, $original - $new ),
				'percent'  => round( SiteStats::savings_percent( $original, $new ), 1 ),
			];
		}

		return $rows;
	}

	/**
	 * Leftovers as a list with totals.
	 *
	 * @param array<string, mixed> $sources    Leftovers by source name.
	 * @param int                  $scanned_at Unix time of the scan (0 = never).
	 * @return array<string, mixed>
	 */
	private static function leftovers( array $sources, int $scanned_at ): array {
		$list    = [];
		$bytes   = 0;
		$files   = 0;
		$partial = false;

		foreach ( $sources as $name => $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}

			$item = [
				'name'    => (string) $name,
				'path'    => (string) ( $source['path'] ?? '' ),
				'type'    => (string) ( $source['type'] ?? 'folder' ),
				'bytes'   => (int) ( $source['bytes'] ?? 0 ),
				'files'   => (int) ( $source['files'] ?? 0 ),
				'partial' => ! empty( $source['partial'] ),
			];

			$bytes  += $item['bytes'];
			$files  += $item['files'];
			$partial = $partial || $item['partial'];
			$list[]  = $item;
		}

		return [
			'sources'       => $list,
			'total_bytes'   => $bytes,
			'total_files'   => $files,
			'partial'       => $partial,
			'scanned_at'    => $scanned_at > 0 ? $scanned_at : null,
			'known_sources' => SiteStats::KNOWN_SOURCES,
		];
	}
}
