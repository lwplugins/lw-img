<?php
/**
 * Site-wide optimization and disk-usage statistics.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Stats;

defined( 'ABSPATH' ) || exit;

use FilesystemIterator;
use LightweightPlugins\Img\Backup\BackupStore;
use LightweightPlugins\Img\Db\ImageRepository;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Aggregates saved bytes from the attachment meta and measures backup
 * storage on disk — including originals left behind by other optimizers,
 * so users learn about forgotten files even when that plugin is gone.
 * Two shapes are covered: backup folders (ShortPixel, Imagify, EWWW) and
 * originals kept beside each file (Swift Performance, Smush).
 *
 * The savings figures are cached for an hour. The leftover scan is not on
 * that clock: it walks the whole uploads tree, and its result only changes
 * when someone deletes those files, so it runs once and then only when the
 * user explicitly refreshes.
 */
final class SiteStats {

	public const REFRESH_ACTION = 'lw_img_refresh_stats';

	/**
	 * Rescanning the uploads tree is a separate, deliberately explicit
	 * action: it is the expensive one, and it is the only thing on this tab
	 * that never happens on its own.
	 */
	public const RESCAN_ACTION = 'lw_img_rescan_leftovers';

	private const CACHE_KEY = 'lw_img_stats';

	/**
	 * Where the last leftover scan is kept (an option, not a transient: it
	 * is only redone on request).
	 */
	public const LEFTOVERS_OPTION = 'lw_img_leftovers';

	/**
	 * Optimizers whose leftovers we know how to find, in report order. Named
	 * here so the UI can say what was checked when it finds nothing.
	 *
	 * @var array<int, string>
	 */
	public const KNOWN_SOURCES = [ 'ShortPixel', 'Imagify', 'EWWW', 'Swift Performance', 'Smush' ];

	/**
	 * Bounds for the uploads-wide leftover scan (see suffix_stats()).
	 *
	 * Measured on a 1.29M-file uploads tree: the walk runs at roughly a
	 * million entries per second with a warm dentry cache, so these bounds
	 * let a normal library finish while still capping a pathological one.
	 * The wall-clock budget is the real safety net (cold cache, slow or
	 * network storage); the entry cap just keeps the loop finite.
	 */
	private const SCAN_MAX_ENTRIES = 2000000;
	private const SCAN_MAX_SECONDS = 10;

	/**
	 * Hook the refresh action.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::REFRESH_ACTION, [ self::class, 'refresh' ] );
		add_action( 'admin_post_' . self::RESCAN_ACTION, [ self::class, 'rescan' ] );
	}

	/**
	 * Collect (or serve cached) statistics.
	 *
	 * @param bool $fresh Skip the cache.
	 * @return array<string, mixed>
	 */
	public static function get( bool $fresh = false ): array {
		$scan = self::stored_leftovers( $fresh );

		if ( ! $fresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				$cached['leftovers']  = $scan['sources'];
				$cached['scanned_at'] = $scan['scanned_at'];
				return $cached;
			}
		}

		$stats               = self::collect();
		$stats['leftovers']  = $scan['sources'];
		$stats['scanned_at'] = $scan['scanned_at'];

		set_transient( self::CACHE_KEY, $stats, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * The last leftover scan's result, scanning once if none was stored.
	 *
	 * Leftovers only change when someone deletes those files, so re-walking
	 * the uploads tree on a timer would be pure waste — and that walk is by
	 * far the most expensive thing on this tab. The scan therefore runs once
	 * and is then kept until the user explicitly asks for a new one, which
	 * is also why it lives in an option instead of an expiring transient.
	 *
	 * @param bool $fresh Force a new scan.
	 * @return array{sources: array<string, array<string, mixed>>, scanned_at: int}
	 */
	public static function stored_leftovers( bool $fresh = false ): array {
		$stored = get_option( self::LEFTOVERS_OPTION );

		if ( ! $fresh && is_array( $stored ) && isset( $stored['sources'] ) ) {
			return [
				'sources'    => (array) $stored['sources'],
				'scanned_at' => (int) ( $stored['scanned_at'] ?? 0 ),
			];
		}

		$scan = [
			'sources'    => self::leftovers( (string) wp_get_upload_dir()['basedir'] ),
			'scanned_at' => time(),
		];

		update_option( self::LEFTOVERS_OPTION, $scan, false );

		return $scan;
	}

	/**
	 * Clear the cache and go back to the Stats tab.
	 *
	 * @return void
	 */
	public static function refresh(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'lw-img' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( self::REFRESH_ACTION );

		// Only the savings figures: the leftover scan has its own action so
		// this stays cheap and the expensive one is never triggered by
		// accident.
		delete_transient( self::CACHE_KEY );

		wp_safe_redirect( admin_url( 'admin.php?page=lw-img#stats' ) );
		exit;
	}

	/**
	 * Re-walk the uploads tree for leftovers, then go back to the Stats tab.
	 *
	 * @return void
	 */
	public static function rescan(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'lw-img' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( self::RESCAN_ACTION );

		self::stored_leftovers( true );

		wp_safe_redirect( admin_url( 'admin.php?page=lw-img#stats' ) );
		exit;
	}

	/**
	 * The share of bytes saved, as a percentage.
	 *
	 * @param int $original  Total original bytes.
	 * @param int $optimized Total optimized bytes.
	 * @return float
	 */
	public static function savings_percent( int $original, int $optimized ): float {
		if ( $original <= 0 ) {
			return 0.0;
		}

		return max( 0.0, ( 1 - $optimized / $original ) * 100 );
	}

	/**
	 * Recursive size and file count of a directory.
	 *
	 * @param string $path Absolute directory path.
	 * @return array{bytes: int, files: int}
	 */
	public static function dir_stats( string $path ): array {
		$bytes = 0;
		$files = 0;

		if ( is_dir( $path ) ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() ) {
					$bytes += (int) $file->getSize();
					++$files;
				}
			}
		}

		return [
			'bytes' => $bytes,
			'files' => $files,
		];
	}

	/**
	 * Find originals other optimizers left *beside* the optimized file.
	 *
	 * Some optimizers keep no backup folder: Swift Performance saved
	 * "<file>.swift-original" and Smush saves "<name>.bak.<ext>" right next
	 * to the image (both verified against their source). Finding those means
	 * walking the whole uploads tree, so the tree is walked exactly once and
	 * every matcher is tested per entry. The walk is bounded by an entry
	 * count and a wall-clock budget; when it stops early the result is
	 * flagged partial and the UI says so rather than reporting a truncated
	 * total as fact.
	 *
	 * @param string $root     Absolute directory to walk.
	 * @param string $skip_dir Absolute directory to skip (our own backups).
	 * @return array{sources: array<string, array{bytes: int, files: int}>, partial: bool}
	 */
	public static function stray_stats( string $root, string $skip_dir = '' ): array {
		$sources = [];
		$entries = 0;
		$partial = false;

		if ( ! is_dir( $root ) ) {
			return [
				'sources' => [],
				'partial' => false,
			];
		}

		// Resolve both sides once so the per-file prefix test below is exact
		// (the iterator yields paths built from $root, unresolved), instead of
		// paying a realpath() syscall per entry.
		$real_root = realpath( $root );
		$root      = false === $real_root ? $root : $real_root;

		if ( '' !== $skip_dir ) {
			$real_skip = realpath( $skip_dir );
			$skip_dir  = false === $real_skip ? $skip_dir : $real_skip;
		}

		$deadline = time() + self::SCAN_MAX_SECONDS;

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);

		foreach ( $iterator as $file ) {
			++$entries;

			// The clock is only consulted periodically: at a million entries
			// per second a per-entry time() call is pure overhead.
			if ( $entries > self::SCAN_MAX_ENTRIES || ( 0 === $entries % 5000 && time() > $deadline ) ) {
				$partial = true;
				break;
			}

			$path = (string) $file->getPathname();

			if ( '' !== $skip_dir && str_starts_with( $path, $skip_dir ) ) {
				continue;
			}

			$source = self::classify_stray( $path );

			if ( null === $source || ! $file->isFile() ) {
				continue;
			}

			if ( ! isset( $sources[ $source ] ) ) {
				$sources[ $source ] = [
					'bytes' => 0,
					'files' => 0,
				];
			}

			$sources[ $source ]['bytes'] += (int) $file->getSize();
			++$sources[ $source ]['files'];
		}

		return [
			'sources' => $sources,
			'partial' => $partial,
		];
	}

	/**
	 * Originals other optimizers left on disk, by source.
	 *
	 * Two shapes exist and both are covered: dedicated backup folders, and
	 * originals dropped beside each image. All paths were verified against
	 * each plugin's own source.
	 *
	 * @param string $uploads Absolute uploads basedir.
	 * @return array<string, array{bytes: int, files: int, path: string, type: string, partial: bool}>
	 */
	private static function leftovers( string $uploads ): array {
		$leftovers = [];

		$folders = [
			// ShortPixel: <uploads>/ShortpixelBackups.
			'ShortPixel' => [ $uploads . '/ShortpixelBackups', 'uploads/ShortpixelBackups' ],
			// Imagify media library: <uploads>/backup (inc/functions/attachments.php).
			'Imagify'    => [ $uploads . '/backup', 'uploads/backup' ],
			// EWWW local backups live in wp-content, not uploads (classes/class-backup.php).
			'EWWW'       => [ WP_CONTENT_DIR . '/image-backup', 'wp-content/image-backup' ],
		];

		foreach ( $folders as $name => [ $absolute, $label ] ) {
			$stats = self::dir_stats( $absolute );
			if ( $stats['files'] > 0 ) {
				$leftovers[ $name ] = array_merge(
					$stats,
					[
						'path'    => $label,
						'type'    => 'folder',
						'partial' => false,
					]
				);
			}
		}

		// Imagify's custom-folders backup sits at the site root, outside uploads.
		$imagify_root = self::dir_stats( trailingslashit( ABSPATH ) . 'imagify-backup' );
		if ( $imagify_root['files'] > 0 ) {
			$leftovers['Imagify (custom folders)'] = array_merge(
				$imagify_root,
				[
					'path'    => 'imagify-backup',
					'type'    => 'folder',
					'partial' => false,
				]
			);
		}

		// Optimizers that drop the original beside the file need one walk of
		// the uploads tree; every matcher is tested during that single pass.
		$stray = self::stray_stats( $uploads, ( new BackupStore() )->root() );

		$patterns = [
			'Swift Performance' => 'uploads/**/*.swift-original',
			'Smush'             => 'uploads/**/*.bak.<ext>',
		];

		foreach ( (array) $stray['sources'] as $name => $stats ) {
			$leftovers[ $name ] = array_merge(
				$stats,
				[
					'path'    => $patterns[ $name ] ?? 'uploads',
					'type'    => 'beside',
					'partial' => (bool) $stray['partial'],
				]
			);
		}

		return $leftovers;
	}

	/**
	 * Which optimizer left this file behind, if any.
	 *
	 * Patterns verified against each plugin's source:
	 * - Swift Performance: "<file>.swift-original"
	 * - Smush: "<name>.bak.<ext>" (core/backups/class-backups.php), restricted
	 *   to image extensions so an unrelated "notes.bak.txt" is not counted.
	 *
	 * @param string $path Absolute file path.
	 * @return string|null Source label, or null when the file is not a leftover.
	 */
	public static function classify_stray( string $path ): ?string {
		if ( self::has_suffix( $path, '.swift-original' ) ) {
			return 'Swift Performance';
		}

		foreach ( [ '.bak.jpg', '.bak.jpeg', '.bak.png', '.bak.gif', '.bak.webp' ] as $bak ) {
			if ( self::has_suffix( $path, $bak ) ) {
				return 'Smush';
			}
		}

		return null;
	}

	/**
	 * Case-insensitive "filename ends with this suffix" test.
	 *
	 * @param string $path   Path or filename.
	 * @param string $suffix Suffix to match (e.g. '.swift-original').
	 * @return bool
	 */
	public static function has_suffix( string $path, string $suffix ): bool {
		$length = strlen( $suffix );

		if ( 0 === $length || strlen( $path ) <= $length ) {
			return false;
		}

		return 0 === substr_compare( $path, $suffix, -$length, $length, true );
	}

	/**
	 * Gather everything.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect(): array {
		// One aggregate over the plugin's own table; this used to be a
		// self-join across the site's postmeta table.
		$totals = ImageRepository::savings();

		$original  = $totals['original'];
		$optimized = $totals['optimized'];

		// Leftovers are deliberately not collected here — they come from the
		// stored scan (see stored_leftovers()) so this hourly refresh never
		// re-walks the uploads tree.
		return [
			'count'         => $totals['count'],
			'original'      => $original,
			'optimized'     => $optimized,
			'saved'         => max( 0, $original - $optimized ),
			'percent'       => self::savings_percent( $original, $optimized ),
			'backup'        => self::dir_stats( ( new BackupStore() )->root() ),
			'leftovers'     => [],
			'library_total' => self::library_total(),
			'wins'          => self::biggest_wins(),
			'generated_at'  => time(),
		];
	}

	/**
	 * Total number of image attachments in the Media Library.
	 *
	 * @return int
	 */
	private static function library_total(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- single aggregate; result is transient-cached by the caller.
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type LIKE 'image/%'"
		);
	}

	/**
	 * The images with the largest absolute savings.
	 *
	 * @param int $limit Number of rows.
	 * @return array<int, array{file: string, original: int, new: int}>
	 */
	private static function biggest_wins( int $limit = 5 ): array {
		$wins = [];

		foreach ( ImageRepository::biggest_wins( $limit ) as $win ) {
			$file   = (string) get_post_meta( $win['attachment_id'], '_wp_attached_file', true );
			$wins[] = [
				'file'     => '' !== $file ? basename( $file ) : '#' . $win['attachment_id'],
				'original' => $win['orig_size'],
				'new'      => $win['new_size'],
			];
		}

		return $wins;
	}
}
