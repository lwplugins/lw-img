<?php
/**
 * Admin-post actions controlling the bulk run.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

/**
 * Start / cancel the background run and re-queue skipped or failed items.
 * All actions are nonce- and capability-checked and redirect back to the
 * Bulk tab.
 */
final class JobHandlers {

	public const ACTION_START  = 'lw_img_bulk_start';
	public const ACTION_CANCEL = 'lw_img_bulk_cancel';
	public const ACTION_RETRY  = 'lw_img_bulk_retry';
	public const ACTION_RESCAN = 'lw_img_bulk_rescan';

	/**
	 * Hook the admin-post actions.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_START, [ self::class, 'start' ] );
		add_action( 'admin_post_' . self::ACTION_CANCEL, [ self::class, 'cancel' ] );
		add_action( 'admin_post_' . self::ACTION_RETRY, [ self::class, 'retry_failed' ] );
		add_action( 'admin_post_' . self::ACTION_RESCAN, [ self::class, 'rescan_skipped' ] );
	}

	/**
	 * Nonce-protected URL for one of the actions.
	 *
	 * @param string $action Action constant.
	 * @return string
	 */
	public static function url( string $action ): string {
		return wp_nonce_url(
			add_query_arg( 'action', $action, admin_url( 'admin-post.php' ) ),
			$action
		);
	}

	/**
	 * Start the background run.
	 *
	 * @return void
	 */
	public static function start(): void {
		self::authorize( self::ACTION_START );

		if ( ! BulkJob::is_running() ) {
			BulkJob::start( ( new UnoptimizedQuery() )->count() );
			BackgroundWorker::kick();
		}

		self::back();
	}

	/**
	 * Cancel the background run.
	 *
	 * @return void
	 */
	public static function cancel(): void {
		self::authorize( self::ACTION_CANCEL );

		if ( BulkJob::is_running() ) {
			BulkJob::finish( BulkJob::STATE_CANCELLED );
		}
		BackgroundWorker::unschedule();

		self::back();
	}

	/**
	 * Re-queue failed attachments.
	 *
	 * @return void
	 */
	public static function retry_failed(): void {
		self::authorize( self::ACTION_RETRY );
		StatusMeta::requeue_by_status( StatusMeta::FAILED );
		self::back();
	}

	/**
	 * Re-queue skipped attachments (after settings changes).
	 *
	 * @return void
	 */
	public static function rescan_skipped(): void {
		self::authorize( self::ACTION_RESCAN );
		StatusMeta::requeue_by_status( StatusMeta::SKIPPED );
		self::back();
	}

	/**
	 * Capability + nonce check shared by all actions.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function authorize( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'lw-img' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirect back to the Bulk tab.
	 *
	 * @return void
	 */
	private static function back(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=lw-img#bulk' ) );
		exit;
	}
}
