<?php
/**
 * Start, cancel and re-queue actions of the bulk dashboard.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\Client;
use LightweightPlugins\Img\Health\RedirectProbe;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Settings\SettingsStore;
use Throwable;
use WP_Error;

/**
 * Every action answers with a notice ({type, message}) or a WP_Error that
 * says why nothing happened. Capability checks live in the REST routes.
 */
final class BulkActions {

	/**
	 * Start the background run, after live checks of the key, the redirect
	 * safety net and the queue.
	 *
	 * @return array{type: string, message: string}|WP_Error
	 */
	public static function start() {
		if ( BulkJob::is_running() ) {
			return self::notice( 'info', BulkGate::message( BulkGate::RUNNING ) );
		}

		$key_error = self::key_error();
		if ( null !== $key_error ) {
			return $key_error;
		}

		// A bulk run rewrites public URLs and leans on the 301 safety net
		// for external links. Only an explicit "no" blocks: an unverifiable
		// probe (loopback blocked) must not disable the feature.
		$redirects = RedirectProbe::works();
		RedirectProbe::remember( $redirects );
		if ( false === $redirects ) {
			return new WP_Error( 'lw_img_bulk_redirects', BulkGate::message( BulkGate::REDIRECTS ), [ 'status' => 409 ] );
		}

		$pending = ( new UnoptimizedQuery() )->count( true );
		if ( $pending <= 0 ) {
			return new WP_Error( 'lw_img_bulk_empty', BulkGate::message( BulkGate::NOTHING_PENDING ), [ 'status' => 409 ] );
		}

		BulkJob::start( $pending );
		BackgroundWorker::kick();

		return self::notice(
			'success',
			sprintf(
				/* translators: %s: number of images */
				_n( 'Bulk optimization started: %s image queued.', 'Bulk optimization started: %s images queued.', $pending, 'lw-img' ),
				number_format_i18n( $pending )
			)
		);
	}

	/**
	 * Cancel the background run.
	 *
	 * @return array{type: string, message: string}
	 */
	public static function cancel(): array {
		$was_running = BulkJob::is_running();

		if ( $was_running ) {
			BulkJob::finish( BulkJob::STATE_CANCELLED );
		}
		BackgroundWorker::unschedule();

		return $was_running
			? self::notice( 'success', __( 'The run was cancelled. Images converted so far stay converted.', 'lw-img' ) )
			: self::notice( 'info', __( 'No run is in progress.', 'lw-img' ) );
	}

	/**
	 * Put images with a status back into the queue (does not start a run).
	 *
	 * @param string $status StatusMeta::FAILED or StatusMeta::SKIPPED.
	 * @return array{type: string, message: string, queued: int}
	 */
	public static function requeue( string $status ): array {
		$queued = StatusMeta::requeue_by_status( $status );

		if ( 0 === $queued ) {
			$message = StatusMeta::FAILED === $status
				? __( 'There are no failed images to retry.', 'lw-img' )
				: __( 'There are no skipped images to re-scan.', 'lw-img' );

			return self::notice( 'info', $message ) + [ 'queued' => 0 ];
		}

		$template = BulkJob::is_running()
			/* translators: %s: number of images */
			? _n( '%s image queued — the current run picks it up.', '%s images queued — the current run picks them up.', $queued, 'lw-img' )
			/* translators: %s: number of images */
			: _n( '%s image queued — press Start to optimize it.', '%s images queued — press Start to optimize them.', $queued, 'lw-img' );

		return self::notice( 'success', sprintf( $template, number_format_i18n( $queued ) ) ) + [ 'queued' => $queued ];
	}

	/**
	 * Save the processing speed.
	 *
	 * @param mixed $profile Submitted profile.
	 * @return array{type: string, message: string}|WP_Error
	 */
	public static function set_speed( mixed $profile ) {
		$errors = SettingsStore::save( [ 'bulk_speed' => $profile ] );

		if ( [] !== $errors ) {
			return new WP_Error(
				'lw_img_invalid',
				__( 'Choose gentle, normal or fast.', 'lw-img' ),
				[
					'status' => 400,
					'fields' => [ 'profile' => $errors['bulk_speed'] ?? [] ],
				]
			);
		}

		return self::notice( 'success', __( 'Processing speed saved.', 'lw-img' ) );
	}

	/**
	 * Why the key cannot start a run, or null when it works.
	 *
	 * A live probe, not just non-empty: a revoked key would otherwise halt
	 * the run on its very first image, one worker tick later.
	 *
	 * @return WP_Error|null
	 */
	private static function key_error(): ?WP_Error {
		if ( '' === trim( (string) Options::get( 'api_key' ) ) ) {
			return new WP_Error( 'lw_img_bulk_no_key', BulkGate::message( BulkGate::NO_KEY ), [ 'status' => 400 ] );
		}

		try {
			( new Client() )->get_account();
			return null;
		} catch ( Throwable $e ) {
			return new WP_Error(
				'lw_img_bulk_key',
				sprintf(
					/* translators: %s: error message from the API */
					__( 'The bulk run was not started: the API rejected the key or could not be reached (%s).', 'lw-img' ),
					$e->getMessage()
				),
				[ 'status' => 400 ]
			);
		}
	}

	/**
	 * A notice.
	 *
	 * @param string $type    success|info.
	 * @param string $message Message.
	 * @return array{type: string, message: string}
	 */
	private static function notice( string $type, string $message ): array {
		return [
			'type'    => $type,
			'message' => $message,
		];
	}
}
