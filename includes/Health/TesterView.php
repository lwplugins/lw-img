<?php
/**
 * The Tester tab's data.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Pure shaping of a HealthReport: the verdict with counts per status, the
 * needs-attention rows (criticals first, each naming its section, with a
 * copyable fix when there is one) and the section cards.
 */
final class TesterView {

	/**
	 * Seconds the report is cached.
	 */
	public const CACHE_TTL = 600;

	/**
	 * Build the response.
	 *
	 * @param array<string, mixed> $report HealthReport::get() result.
	 * @return array<string, mixed>
	 */
	public static function build( array $report ): array {
		$sections = is_array( $report['sections'] ?? null ) ? $report['sections'] : [];
		$counts   = [];

		foreach ( [ 'critical', 'warning', 'ok', 'info' ] as $status ) {
			$counts[ $status ] = HealthReport::count_status( $sections, $status );
		}

		$verdict = 'ok';
		if ( $counts['critical'] > 0 ) {
			$verdict = 'critical';
		} elseif ( $counts['warning'] > 0 ) {
			$verdict = 'warning';
		}

		return [
			'generated_at' => (int) ( $report['generated_at'] ?? 0 ),
			'cache_ttl'    => self::CACHE_TTL,
			'verdict'      => [ 'status' => $verdict ] + $counts,
			'attention'    => self::attention( $sections ),
			'sections'     => self::sections( $sections ),
		];
	}

	/**
	 * Section labels, in display order.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return [
			'database'    => __( 'Database', 'lw-img' ),
			'environment' => __( 'PHP & image processing', 'lw-img' ),
			'filesystem'  => __( 'Filesystem', 'lw-img' ),
			'cron'        => __( 'Background processing', 'lw-img' ),
			'redirects'   => __( 'Old-URL redirects', 'lw-img' ),
			'api'         => __( 'API & plugins', 'lw-img' ),
		];
	}

	/**
	 * Warning and critical rows, criticals first.
	 *
	 * @param array<string, mixed> $sections Report sections.
	 * @return array<int, array<string, mixed>>
	 */
	private static function attention( array $sections ): array {
		$critical = [];
		$warning  = [];

		foreach ( $sections as $id => $rows ) {
			foreach ( is_array( $rows ) ? $rows : [] as $row ) {
				$item = self::row( (array) $row ) + [ 'section' => (string) $id ];

				if ( 'critical' === $item['status'] ) {
					$critical[] = $item;
				} elseif ( 'warning' === $item['status'] ) {
					$warning[] = $item;
				}
			}
		}

		return array_merge( $critical, $warning );
	}

	/**
	 * Section cards, known sections first in display order.
	 *
	 * @param array<string, mixed> $sections Report sections.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sections( array $sections ): array {
		$labels = self::labels();
		$order  = array_unique( array_merge( array_keys( $labels ), array_map( 'strval', array_keys( $sections ) ) ) );
		$cards  = [];

		foreach ( $order as $id ) {
			if ( ! isset( $sections[ $id ] ) || ! is_array( $sections[ $id ] ) ) {
				continue;
			}

			$rows    = array_map( static fn ( $row ): array => self::row( (array) $row ), array_values( $sections[ $id ] ) );
			$cards[] = [
				'id'       => $id,
				'label'    => $labels[ $id ] ?? $id,
				'status'   => HealthReport::worst_status( $rows ),
				'critical' => count( array_filter( $rows, static fn ( array $row ): bool => 'critical' === $row['status'] ) ),
				'warning'  => count( array_filter( $rows, static fn ( array $row ): bool => 'warning' === $row['status'] ) ),
				'rows'     => $rows,
			];
		}

		return $cards;
	}

	/**
	 * One check row, typed; fix is null when there is none.
	 *
	 * @param array<string, mixed> $row Check row.
	 * @return array{label: string, status: string, message: string, fix: string|null}
	 */
	private static function row( array $row ): array {
		$fix = isset( $row['fix'] ) ? (string) $row['fix'] : '';

		return [
			'label'   => (string) ( $row['label'] ?? '' ),
			'status'  => (string) ( $row['status'] ?? 'info' ),
			'message' => (string) ( $row['message'] ?? '' ),
			'fix'     => '' !== $fix ? $fix : null,
		];
	}
}
