<?php
/**
 * Main Plugin class.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img;

use LightweightPlugins\Img\Admin\SettingsPage;
use LightweightPlugins\Img\Backup\AttachmentDeleteCleanup;
use LightweightPlugins\Img\Backup\RestoreHandler;
use LightweightPlugins\Img\Backup\RetentionCleaner;
use LightweightPlugins\Img\Bulk\BackgroundWorker;
use LightweightPlugins\Img\Bulk\JobHandlers;
use LightweightPlugins\Img\Bulk\OptimizeHandler;
use LightweightPlugins\Img\Bulk\ReoptimizeHandler;
use LightweightPlugins\Img\Bulk\StatusEndpoint;
use LightweightPlugins\Img\CLI\Commands as CLICommands;
use LightweightPlugins\Img\Compat\CompetitorNotice;
use LightweightPlugins\Img\Health\HealthReport;
use LightweightPlugins\Img\Log\ClearHandler;
use LightweightPlugins\Img\Log\EventLog;
use LightweightPlugins\Img\Media\ComparePage;
use LightweightPlugins\Img\Media\InfoMetabox;
use LightweightPlugins\Img\Media\NotFoundRedirect;
use LightweightPlugins\Img\Media\RowActions;
use LightweightPlugins\Img\Media\SavingsColumn;
use LightweightPlugins\Img\Stats\SiteStats;
use LightweightPlugins\Img\Upload\UploadInterceptor;

/**
 * Wires up plugin components and hooks.
 */
final class Plugin {

	public function __construct() {
		$this->init_hooks();
		$this->init_components();
	}

	private function init_hooks(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );
	}

	private function init_components(): void {
		EventLog::register();
		RetentionCleaner::register();
		AttachmentDeleteCleanup::register();
		BackgroundWorker::register();
		NotFoundRedirect::register();
		new UploadInterceptor();

		if ( is_admin() ) {
			CompetitorNotice::register();
			SiteStats::register();
			HealthReport::register();
			ClearHandler::register();
			RestoreHandler::register();
			OptimizeHandler::register();
			ReoptimizeHandler::register();
			JobHandlers::register();
			StatusEndpoint::register();
			RowActions::register();
			SavingsColumn::register();
			InfoMetabox::register();
			ComparePage::register();
			new SettingsPage();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'lw-img', CLICommands::class );
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'lw-img',
			false,
			dirname( plugin_basename( LW_IMG_FILE ) ) . '/languages'
		);
	}
}
