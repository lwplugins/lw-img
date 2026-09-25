<?php
/**
 * Collects the bulk dashboard's status.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Health\RedirectProbe;
use LightweightPlugins\Img\Options;

/**
 * Gathers the job record, library counts, gate and lock state for
 * BulkStatusView. Everything here is cheap enough for a 3-second poll:
 * the job is one option row, the counts one GROUP BY over the plugin's own
 * table, the pending count and the redirect verdict come from caches.
 */
final class BulkStatus {

	/**
	 * The status object.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$job     = BulkJob::get();
		$running = BulkJob::STATE_RUNNING === ( $job['state'] ?? '' );
		$pending = ( new UnoptimizedQuery() )->count();
		$key_set = '' !== trim( (string) Options::get( 'api_key' ) );

		return BulkStatusView::build(
			$job,
			[
				'library'     => self::library( $pending, $running ),
				'gate'        => BulkGate::describe( self::gate_reasons( $running, $key_set, $pending ) ),
				'speed'       => Throttle::speed(),
				'lock_active' => BackgroundWorker::is_locked(),
				'next_tick'   => $running ? wp_next_scheduled( BackgroundWorker::HOOK ) : false,
			],
			time()
		);
	}

	/**
	 * Gate reasons. The redirect probe only runs when it could matter (no
	 * run, key set), and then from its 10-minute cache.
	 *
	 * @param bool $running Whether a run is in progress.
	 * @param bool $key_set Whether a key is configured.
	 * @param int  $pending Cached pending count.
	 * @return array<int, string>
	 */
	private static function gate_reasons( bool $running, bool $key_set, int $pending ): array {
		$redirects = ! $running && $key_set ? RedirectProbe::cached() : null;

		return BulkGate::reasons( $running, $key_set, $redirects, $pending );
	}

	/**
	 * All-time library totals.
	 *
	 * @param int  $pending Cached pending count.
	 * @param bool $running Whether a run is in progress (skip reasons are only shown on the start screen).
	 * @return array<string, mixed>
	 */
	private static function library( int $pending, bool $running ): array {
		$counts  = StatusMeta::counts();
		$reasons = [];

		if ( ! $running ) {
			foreach ( StatusMeta::skip_reasons( 5 ) as $reason => $count ) {
				$reasons[] = [
					'reason' => (string) $reason,
					'count'  => (int) $count,
				];
			}
		}

		return [
			'pending'      => $pending,
			'optimized'    => (int) ( $counts[ StatusMeta::OPTIMIZED ] ?? 0 ),
			'skipped'      => (int) ( $counts[ StatusMeta::SKIPPED ] ?? 0 ),
			'failed'       => (int) ( $counts[ StatusMeta::FAILED ] ?? 0 ),
			'skip_reasons' => $reasons,
		];
	}
}
