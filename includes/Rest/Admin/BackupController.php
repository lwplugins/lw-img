<?php
/**
 * Backup REST controller.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Rest\Admin;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Backup\BackupStore;
use LightweightPlugins\Img\Backup\RetentionCleaner;
use LightweightPlugins\Img\Options;
use LightweightPlugins\Img\Stats\SiteStats;
use LightweightPlugins\Img\Stats\StatsCache;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /admin/backup: folder size and count, retention, and when the
 * cleanup runs next. The backup settings themselves go through
 * /admin/settings.
 */
final class BackupController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		Routes::add( '/admin/backup', [ WP_REST_Server::READABLE => 'get_backup' ], $this );
	}

	/**
	 * The backup overview.
	 *
	 * The folder figures come from the hourly stats cache when it is warm
	 * (the same numbers the Stats tab shows); otherwise the folder is
	 * measured now — the backup folder only, never the uploads-wide scan.
	 *
	 * @return WP_REST_Response
	 */
	public function get_backup(): WP_REST_Response {
		$cached    = StatsCache::peek();
		$folder    = is_array( $cached['backup'] ?? null ) ? $cached['backup'] : SiteStats::dir_stats( ( new BackupStore() )->root() );
		$retention = (int) Options::get( 'backup_retention_days' );
		$next_run  = wp_next_scheduled( RetentionCleaner::HOOK );

		return new WP_REST_Response(
			[
				'enabled'             => (bool) Options::get( 'backup_enabled' ),
				'retention_days'      => $retention,
				'delete_on_uninstall' => (bool) Options::get( 'delete_on_uninstall' ),
				'path'                => 'uploads/' . BackupStore::BACKUP_DIR . '/',
				'bytes'               => (int) ( $folder['bytes'] ?? 0 ),
				'files'               => (int) ( $folder['files'] ?? 0 ),
				'measured_at'         => null !== $cached ? (int) ( $cached['generated_at'] ?? 0 ) : time(),
				'cleanup'             => [
					'mode'             => 0 === $retention ? 'never' : 'daily',
					'next_run'         => false !== $next_run ? (int) $next_run : null,
					'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				],
			]
		);
	}
}
