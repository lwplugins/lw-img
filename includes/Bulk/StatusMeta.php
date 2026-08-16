<?php
/**
 * Per-attachment attempt status — the bulk queue's source of truth.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

/**
 * Every optimization attempt stamps the attachment with a status meta;
 * the pending-query excludes stamped attachments, which makes bulk runs
 * resumable and prevents unprocessable images from being retried forever.
 */
final class StatusMeta {

	public const META_STATUS = '_lw_img_status';
	public const META_DETAIL = '_lw_img_status_detail';

	public const OPTIMIZED = 'optimized';
	public const SKIPPED   = 'skipped';
	public const FAILED    = 'failed';

	/**
	 * Stamp an attachment with the outcome of an attempt.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $status        One of the status constants.
	 * @param string $detail        Human-readable reason/detail.
	 * @return void
	 */
	public static function write( int $attachment_id, string $status, string $detail ): void {
		update_post_meta( $attachment_id, self::META_STATUS, $status );
		update_post_meta( $attachment_id, self::META_DETAIL, $detail );
	}

	/**
	 * Remove the stamp so the attachment becomes pending again.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public static function clear( int $attachment_id ): void {
		delete_post_meta( $attachment_id, self::META_STATUS );
		delete_post_meta( $attachment_id, self::META_DETAIL );
	}

	/**
	 * Re-queue every attachment currently stamped with the given status.
	 *
	 * @param string $status Status value to clear (skipped or failed).
	 * @return int Number of attachments re-queued.
	 */
	public static function requeue_by_status( string $status ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- targeted id-list lookup; results are acted on immediately via meta API (which handles caches).
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
				self::META_STATUS,
				$status
			)
		);

		foreach ( $ids as $id ) {
			self::clear( (int) $id );
		}

		return count( $ids );
	}

	/**
	 * Counts of stamped attachments grouped by status.
	 *
	 * @return array<string, int>
	 */
	public static function counts(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- aggregate stats query; no meta-API equivalent, shown on demand only.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value AS status, COUNT(*) AS total FROM {$wpdb->postmeta} WHERE meta_key = %s GROUP BY meta_value",
				self::META_STATUS
			)
		);

		$counts = [
			self::OPTIMIZED => 0,
			self::SKIPPED   => 0,
			self::FAILED    => 0,
		];

		foreach ( $rows as $row ) {
			$counts[ (string) $row->status ] = (int) $row->total;
		}

		return $counts;
	}
}
