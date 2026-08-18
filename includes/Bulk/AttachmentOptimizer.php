<?php
/**
 * Optimizes a single existing Media Library attachment.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\ApiException;
use LightweightPlugins\Img\Api\Client;
use LightweightPlugins\Img\Api\OptimizeRequest;
use LightweightPlugins\Img\Compat\CompetitorRegistry;
use LightweightPlugins\Img\Db\ImageRepository;
use LightweightPlugins\Img\Media\AttachmentRebuilder;
use LightweightPlugins\Img\Media\RewriteBuffer;
use LightweightPlugins\Img\Media\UrlPairs;
use LightweightPlugins\Img\Media\UrlRewriter;
use LightweightPlugins\Img\Upload\AnimatedGifProbe;
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

	/**
	 * Per-run optimization level override (used by re-optimize).
	 *
	 * @var string|null
	 */
	private ?string $level_override;

	/**
	 * Optional shared rewrite buffer: bulk runs batch content rewrites
	 * across images instead of scanning every table per image.
	 *
	 * @var RewriteBuffer|null
	 */
	private ?RewriteBuffer $rewrite_buffer;

	public function __construct(
		?ConvertibleDetector $detector = null,
		?FileSwapper $swapper = null,
		?AttachmentRebuilder $rebuilder = null,
		?string $level_override = null,
		?RewriteBuffer $rewrite_buffer = null
	) {
		$this->detector       = $detector ?? new ConvertibleDetector();
		$this->swapper        = $swapper ?? new FileSwapper();
		$this->rebuilder      = $rebuilder ?? new AttachmentRebuilder();
		$this->level_override = $level_override;
		$this->rewrite_buffer = $rewrite_buffer;
	}

	/**
	 * Optimize one attachment.
	 *
	 * Every attempt stamps the attachment with a StatusMeta outcome, which
	 * removes it from the pending queue until it is explicitly re-queued.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array{result: string, detail: string, bytes_in?: int, bytes_saved?: int, halt?: bool} Outcome and detail; halt=true means the run must stop (API quota exhausted).
	 */
	public function optimize( int $attachment_id ): array {
		if ( ImageRepository::is_optimized( $attachment_id ) ) {
			return $this->finish( $attachment_id, self::RESULT_SKIPPED, 'already optimized' );
		}

		$owner = CompetitorRegistry::managed_by( $attachment_id );
		if ( null !== $owner ) {
			return $this->finish( $attachment_id, self::RESULT_SKIPPED, 'already optimized by ' . $owner );
		}

		$file = (string) get_attached_file( $attachment_id );
		$mime = (string) get_post_mime_type( $attachment_id );

		if ( '' === $file || ! file_exists( $file ) ) {
			return $this->finish( $attachment_id, self::RESULT_SKIPPED, 'file missing' );
		}

		if ( ! $this->detector->should_convert_on_demand( $file, $mime ) ) {
			return $this->finish( $attachment_id, self::RESULT_SKIPPED, 'skip rules apply' );
		}

		try {
			return $this->convert( $attachment_id, $file, $mime );
		} catch ( ApiException $e ) {
			do_action( 'lw_img_upload_failed', $file, $e->getMessage() );

			if ( $e->is_quota() ) {
				// Out of credit: leave the image unstamped (it is fine and
				// stays pending) and tell the caller to halt the whole run.
				return [
					'result' => self::RESULT_FAILED,
					'detail' => $e->getMessage(),
					'halt'   => true,
				];
			}

			return $this->finish( $attachment_id, self::RESULT_FAILED, $e->getMessage(), $e->is_transient() );
		} catch ( Throwable $e ) {
			do_action( 'lw_img_upload_failed', $file, $e->getMessage() );
			return $this->finish( $attachment_id, self::RESULT_FAILED, $e->getMessage() );
		}
	}

	/**
	 * Stamp the outcome and build the result array.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $result        Outcome constant.
	 * @param string $detail        Human-readable detail.
	 * @param bool   $transient     Whether a failure looks transient.
	 * @return array{result: string, detail: string}
	 */
	private function finish( int $attachment_id, string $result, string $detail, bool $transient = false ): array {
		StatusMeta::write( $attachment_id, $result, $detail, $transient );

		return [
			'result' => $result,
			'detail' => $detail,
		];
	}

	/**
	 * Convert the file and rewire the attachment.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $file          Absolute path of the current main file.
	 * @param string $mime          Mime type before conversion.
	 * @return array{result: string, detail: string, bytes_in?: int, bytes_saved?: int}
	 */
	private function convert( int $attachment_id, string $file, string $mime ): array {
		$override = ( 'image/gif' === $mime && AnimatedGifProbe::is_animated( $file ) )
			? OptimizeRequest::FORMAT_WEBP
			: null;

		$request = OptimizeRequest::from_options( $file, $override, $this->level_override );
		$result  = ( new Client() )->optimize( $request );

		if ( ! $result->is_smaller() ) {
			do_action( 'lw_img_upload_skipped', $file, 'optimized result not smaller' );
			return $this->finish( $attachment_id, self::RESULT_SKIPPED, 'result not smaller' );
		}

		$old_url  = (string) wp_get_attachment_url( $attachment_id );
		$old_meta = wp_get_attachment_metadata( $attachment_id );

		$swapped    = $this->swapper->swap( $file, $old_url, $result );
		$backup_rel = $swapped['lw_img_backup'] ?? null;

		$this->rebuilder->replace_main_file( $attachment_id, (string) $swapped['file'], false );

		$pairs = UrlPairs::build(
			$old_url,
			UrlPairs::sizes_map( $old_meta ),
			(string) wp_get_attachment_url( $attachment_id ),
			UrlPairs::sizes_map( wp_get_attachment_metadata( $attachment_id ) )
		);

		if ( null !== $this->rewrite_buffer ) {
			$this->rewrite_buffer->add( $pairs );
		} else {
			( new UrlRewriter() )->rewrite( $pairs );
		}

		AttachmentMetaWriter::write_meta(
			$attachment_id,
			$result->original_size,
			$result->new_size,
			$result->percent,
			$result->job_id,
			is_string( $backup_rel ) ? $backup_rel : '',
			$request->level,
			$request->keep_exif
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

		$outcome                = $this->finish( $attachment_id, self::RESULT_OPTIMIZED, sprintf( '-%.1f%%', $result->percent ) );
		$outcome['bytes_in']    = $result->original_size;
		$outcome['bytes_saved'] = max( 0, $result->original_size - $result->new_size );

		return $outcome;
	}
}
