<?php
/**
 * Optimizes a single existing Media Library attachment.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

use LightweightPlugins\Img\Api\ApiException;
use LightweightPlugins\Img\Api\Client;
use LightweightPlugins\Img\Api\OptimizeRequest;
use LightweightPlugins\Img\Media\AttachmentRebuilder;
use LightweightPlugins\Img\Upload\AttachmentMetaWriter;
use LightweightPlugins\Img\Upload\ConvertibleDetector;
use LightweightPlugins\Img\Upload\FileSwapper;
use Throwable;

/**
 * Runs the upload pipeline against an already-attached image: convert via
 * the API, back up / swap the main file, rebuild thumbnails, write meta.
 */
final class AttachmentOptimizer {

	public const RESULT_OPTIMIZED = 'optimized';
	public const RESULT_SKIPPED   = 'skipped';
	public const RESULT_FAILED    = 'failed';

	/**
	 * Decides whether the file is convertible.
	 *
	 * @var ConvertibleDetector
	 */
	private ConvertibleDetector $detector;

	/**
	 * Swaps the main file for the optimized version.
	 *
	 * @var FileSwapper
	 */
	private FileSwapper $swapper;

	/**
	 * Rebuilds attachment metadata after the swap.
	 *
	 * @var AttachmentRebuilder
	 */
	private AttachmentRebuilder $rebuilder;

	public function __construct(
		?ConvertibleDetector $detector = null,
		?FileSwapper $swapper = null,
		?AttachmentRebuilder $rebuilder = null
	) {
		$this->detector  = $detector ?? new ConvertibleDetector();
		$this->swapper   = $swapper ?? new FileSwapper();
		$this->rebuilder = $rebuilder ?? new AttachmentRebuilder();
	}

	/**
	 * Optimize one attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array{result: string, detail: string} Outcome and a human-readable detail.
	 */
	public function optimize( int $attachment_id ): array {
		if ( get_post_meta( $attachment_id, '_lw_img_optimized', true ) ) {
			return [
				'result' => self::RESULT_SKIPPED,
				'detail' => 'already optimized',
			];
		}

		$file = (string) get_attached_file( $attachment_id );
		$mime = (string) get_post_mime_type( $attachment_id );

		if ( '' === $file || ! file_exists( $file ) ) {
			return [
				'result' => self::RESULT_SKIPPED,
				'detail' => 'file missing',
			];
		}

		if ( ! $this->detector->should_convert_on_demand( $file, $mime ) ) {
			return [
				'result' => self::RESULT_SKIPPED,
				'detail' => 'skip rules apply',
			];
		}

		try {
			return $this->convert( $attachment_id, $file, $mime );
		} catch ( ApiException $e ) {
			do_action( 'lw_img_upload_failed', $file, $e->getMessage() );
			return [
				'result' => self::RESULT_FAILED,
				'detail' => $e->getMessage(),
			];
		} catch ( Throwable $e ) {
			do_action( 'lw_img_upload_failed', $file, $e->getMessage() );
			return [
				'result' => self::RESULT_FAILED,
				'detail' => $e->getMessage(),
			];
		}
	}

	/**
	 * Convert the file and rewire the attachment.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $file          Absolute path of the current main file.
	 * @param string $mime          Mime type before conversion.
	 * @return array{result: string, detail: string}
	 */
	private function convert( int $attachment_id, string $file, string $mime ): array {
		$result = ( new Client() )->optimize( OptimizeRequest::from_options( $file ) );

		if ( ! $result->is_smaller() ) {
			do_action( 'lw_img_upload_skipped', $file, 'optimized result not smaller' );
			return [
				'result' => self::RESULT_SKIPPED,
				'detail' => 'result not smaller',
			];
		}

		$swapped    = $this->swapper->swap( $file, (string) wp_get_attachment_url( $attachment_id ), $result );
		$backup_rel = $swapped['lw_img_backup'] ?? null;

		$this->rebuilder->replace_main_file( $attachment_id, (string) $swapped['file'], false );

		AttachmentMetaWriter::write_meta(
			$attachment_id,
			$result->original_size,
			$result->new_size,
			$result->percent,
			$result->job_id,
			is_string( $backup_rel ) ? $backup_rel : ''
		);

		do_action(
			'lw_img_upload_converted',
			$file,
			(string) $swapped['file'],
			[
				'original_size' => $result->original_size,
				'new_size'      => $result->new_size,
				'percent'       => $result->percent,
				'job_id'        => $result->job_id,
				'mime'          => $mime,
				'mime_to'       => (string) ( $swapped['type'] ?? '' ),
			]
		);

		return [
			'result' => self::RESULT_OPTIMIZED,
			'detail' => sprintf( '-%.1f%%', $result->percent ),
		];
	}
}
