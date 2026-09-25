<?php
/**
 * The bulk dashboard's status object.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

/**
 * Pure shaping of the job record and library counts. The current run's
 * counters ("run") and the all-time library totals ("library") are kept
 * apart, so a tile never changes meaning mid-run.
 */
final class BulkStatusView {

	/**
	 * Seconds without progress after which a running job counts as stalled
	 * (the poll assist takes over from there).
	 */
	public const STALL_SECONDS = 15;

	/**
	 * Build the status object.
	 *
	 * @param array<string, mixed> $job     BulkJob record ([] when none).
	 * @param array<string, mixed> $context library (pending, optimized, skipped, failed, skip_reasons), gate, speed, lock_active, next_tick.
	 * @param int                  $now     Current Unix time.
	 * @return array<string, mixed>
	 */
	public static function build( array $job, array $context, int $now ): array {
		$state   = self::state( $job );
		$running = BulkJob::STATE_RUNNING === $state;
		$updated = (int) ( $job['updated_at'] ?? 0 );

		return [
			'state'   => $state,
			'halt'    => self::halt( $job, $state ),
			'run'     => [] === $job ? null : self::run( $job, $running, $now ),
			'recent'  => self::recent( $job ),
			'library' => $context['library'] ?? [],
			'speed'   => [
				'profile'  => (string) ( $context['speed'] ?? 'normal' ),
				'profiles' => Throttle::SPEEDS,
			],
			'lock'    => [
				'active'       => ! empty( $context['lock_active'] ),
				'stalled'      => $running && $now - $updated > self::STALL_SECONDS,
				'idle_seconds' => $running ? max( 0, $now - $updated ) : 0,
				'next_tick'    => ! empty( $context['next_tick'] ) ? (int) $context['next_tick'] : null,
			],
			'gate'    => $context['gate'] ?? BulkGate::describe( [] ),
			'now'     => $now,
			'notice'  => null,
		];
	}

	/**
	 * Public state name.
	 *
	 * @param array<string, mixed> $job Job record.
	 * @return string idle|running|done|cancelled|halted
	 */
	public static function state( array $job ): string {
		$state = (string) ( $job['state'] ?? '' );
		$known = [ BulkJob::STATE_RUNNING, BulkJob::STATE_DONE, BulkJob::STATE_CANCELLED, BulkJob::STATE_HALTED ];

		return in_array( $state, $known, true ) ? $state : 'idle';
	}

	/**
	 * Current-run counters and timing.
	 *
	 * @param array<string, mixed> $job     Job record.
	 * @param bool                 $running Whether the run is in progress.
	 * @param int                  $now     Current Unix time.
	 * @return array<string, mixed>
	 */
	private static function run( array $job, bool $running, int $now ): array {
		$total     = max( 0, (int) ( $job['total'] ?? 0 ) );
		$optimized = (int) ( $job['optimized'] ?? 0 );
		$skipped   = (int) ( $job['skipped'] ?? 0 );
		$failed    = (int) ( $job['failed'] ?? 0 );
		$processed = $optimized + $skipped + $failed;
		$pending   = max( 0, $total - $processed );
		$started   = (int) ( $job['started_at'] ?? 0 );
		$finished  = (int) ( $job['finished_at'] ?? 0 );
		$end       = $running || $finished <= 0 ? $now : $finished;
		$elapsed   = $started > 0 ? max( 0, $end - $started ) : 0;
		$bytes_in  = (int) ( $job['bytes_in'] ?? 0 );
		$saved     = (int) ( $job['bytes_saved'] ?? 0 );

		return [
			'total'         => $total,
			'processed'     => $processed,
			'optimized'     => $optimized,
			'skipped'       => $skipped,
			'failed'        => $failed,
			'pending'       => $pending,
			'percent'       => $total > 0 ? round( min( 100, 100 * $processed / $total ), 1 ) : 0.0,
			'bytes_in'      => $bytes_in,
			'bytes_saved'   => $saved,
			'saved_percent' => $bytes_in > 0 ? round( 100 * $saved / $bytes_in, 1 ) : 0.0,
			'started_at'    => $started,
			'updated_at'    => (int) ( $job['updated_at'] ?? 0 ),
			'finished_at'   => $finished,
			'elapsed'       => $elapsed,
			'eta'           => $running && $processed > 0 && $pending > 0 ? (int) round( $pending * $elapsed / $processed ) : null,
			'current'       => (string) ( $job['current'] ?? '' ),
			'retried'       => ! empty( $job['retried'] ),
		];
	}

	/**
	 * Why the run halted, when it did.
	 *
	 * @param array<string, mixed> $job   Job record.
	 * @param string               $state Public state.
	 * @return array{reason: string, message: string, detail: string}|null
	 */
	private static function halt( array $job, string $state ): ?array {
		if ( BulkJob::STATE_HALTED !== $state ) {
			return null;
		}

		$halt   = is_array( $job['halt'] ?? null ) ? $job['halt'] : [];
		$reason = (string) ( $halt['reason'] ?? '' );

		return [
			'reason'  => $reason,
			'message' => HaltReason::message( $reason ),
			'detail'  => (string) ( $halt['detail'] ?? '' ),
		];
	}

	/**
	 * The activity feed, newest first.
	 *
	 * @param array<string, mixed> $job Job record.
	 * @return array<int, array{ts: int, label: string, result: string, detail: string}>
	 */
	private static function recent( array $job ): array {
		$recent = [];

		foreach ( is_array( $job['recent'] ?? null ) ? $job['recent'] : [] as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$recent[] = [
				'ts'     => (int) ( $entry['ts'] ?? 0 ),
				'label'  => (string) ( $entry['label'] ?? '' ),
				'result' => (string) ( $entry['result'] ?? '' ),
				'detail' => (string) ( $entry['detail'] ?? '' ),
			];
		}

		return $recent;
	}
}
