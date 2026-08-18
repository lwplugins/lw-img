<?php
/**
 * Per-attachment attempt status — the bulk queue's source of truth.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Db\ImageRepository;

/**
 * Every optimization attempt records an outcome, which removes the image
 * from the pending queue until it is explicitly re-queued.
 *
 * The records live in the plugin's own table (see Db\ImageRepository); this
 * class stays as the queue-facing vocabulary the rest of the plugin speaks.
 */
final class StatusMeta {

	public const OPTIMIZED = ImageRepository::STATUS_OPTIMIZED;
	public const SKIPPED   = ImageRepository::STATUS_SKIPPED;
	public const FAILED    = ImageRepository::STATUS_FAILED;

	/**
	 * Record the outcome of an attempt.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $status        One of the status constants.
	 * @param string $detail        Human-readable reason/detail.
	 * @param bool   $transient     Whether a failure looks transient (worth an automatic retry).
	 * @return void
	 */
	public static function write( int $attachment_id, string $status, string $detail, bool $transient = false ): void {
		ImageRepository::save(
			$attachment_id,
			[
				'status'       => $status,
				'detail'       => mb_substr( $detail, 0, 191 ),
				'is_transient' => $transient ? 1 : 0,
				'claimed_at'   => 0,
			]
		);
	}

	/**
	 * Drop the record so the attachment becomes pending again.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public static function clear( int $attachment_id ): void {
		ImageRepository::forget( $attachment_id );
	}

	/**
	 * Re-queue every attachment whose last failure was transient.
	 *
	 * @return int Number of attachments re-queued.
	 */
	public static function requeue_transient(): int {
		$count = ImageRepository::requeue_transient();

		if ( $count > 0 ) {
			delete_option( UnoptimizedQuery::CURSOR_OPTION );
		}

		return $count;
	}

	/**
	 * Re-queue every attachment currently recorded with the given status.
	 *
	 * @param string $status Status value to clear (skipped or failed).
	 * @return int Number of attachments re-queued.
	 */
	public static function requeue_by_status( string $status ): int {
		$count = ImageRepository::requeue_by_status( $status );

		if ( $count > 0 ) {
			delete_option( UnoptimizedQuery::CURSOR_OPTION );
		}

		return $count;
	}

	/**
	 * Skip-reason breakdown (most frequent first).
	 *
	 * @param int $limit Maximum number of reasons.
	 * @return array<string, int> Map of reason => count.
	 */
	public static function skip_reasons( int $limit = 5 ): array {
		return ImageRepository::skip_reasons( $limit );
	}

	/**
	 * Counts of recorded attachments grouped by status.
	 *
	 * @return array<string, int>
	 */
	public static function counts(): array {
		return ImageRepository::counts();
	}
}
