<?php
/**
 * One log entry as the Log tab shows it.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Log;

defined( 'ABSPATH' ) || exit;

/**
 * Every field typed and present (null when the event does not carry it),
 * plus the attachment edit link when the entry names an attachment.
 */
final class LogEntryView {

	/**
	 * Shape one entry.
	 *
	 * @param array<string, mixed> $entry    Log entry (with the "id" LogQuery adds).
	 * @param string|null          $edit_url Attachment edit URL, when known.
	 * @return array<string, mixed>
	 */
	public static function shape( array $entry, ?string $edit_url ): array {
		$attachment = (int) ( $entry['attachment_id'] ?? 0 );

		return [
			'id'            => (int) ( $entry['id'] ?? 0 ),
			'ts'            => (int) ( $entry['ts'] ?? 0 ),
			'status'        => (string) ( $entry['status'] ?? '' ),
			'file'          => (string) ( $entry['file'] ?? '' ),
			'mime'          => (string) ( $entry['mime'] ?? '' ),
			'mime_to'       => isset( $entry['mime_to'] ) ? (string) $entry['mime_to'] : null,
			'size_in'       => isset( $entry['size_in'] ) ? (int) $entry['size_in'] : null,
			'size_out'      => isset( $entry['size_out'] ) ? (int) $entry['size_out'] : null,
			'percent'       => isset( $entry['percent'] ) ? round( (float) $entry['percent'], 1 ) : null,
			'job_id'        => isset( $entry['job_id'] ) ? (string) $entry['job_id'] : null,
			'reason'        => isset( $entry['reason'] ) ? (string) $entry['reason'] : null,
			'error'         => isset( $entry['error'] ) ? (string) $entry['error'] : null,
			'attachment_id' => $attachment > 0 ? $attachment : null,
			'edit_url'      => null !== $edit_url && '' !== $edit_url ? $edit_url : null,
		];
	}
}
