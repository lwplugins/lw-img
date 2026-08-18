<?php
/**
 * Site-wide optimization and disk-usage statistics.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Stats;

use FilesystemIterator;
use LightweightPlugins\Img\Backup\BackupStore;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Aggregates saved bytes from the attachment meta and measures backup
 * storage on disk — including originals left behind by other optimizers,
 * so users learn about forgotten files even when that plugin is gone.
 * Two shapes are covered: a backup folder (ShortPixel's
 * <uploads>/ShortpixelBackups) and originals kept beside each file
 * (Swift Performance's "<name>.swift-original"). Results are cached for
 * an hour; the Stats tab offers a refresh.
 */
final class SiteStats {

	public const REFRESH_ACTION = 'lw_img_refresh_stats';

	private const CACHE_KEY = 'lw_img_stats';

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
	}

	/**
	 * Collect (or serve cached) statistics.
	 *
	 * @param bool $fresh Skip the cache.
	 * @return array<string, mixed>
	 */
	public static function get( bool $fresh = false ): array {
		if ( ! $fresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$stats = self::collect();
		set_transient( self::CACHE_KEY, $stats, HOUR_IN_SECONDS );

		return $stats;
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

		delete_transient( self::CACHE_KEY );

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
	 * Size and count of files ending in a given suffix, anywhere under a root.
	 *
	 * Unlike a leftover backup *folder*, some optimizers keep the original
	 * next to the optimized file (Swift Performance wrote "<file>.swift-original"),
	 * so finding those means walking the whole uploads tree. That is expensive
	 * on large libraries, so the walk is bounded by an entry count and a wall
	 * clock budget; when it stops early the result is flagged partial and the
	 * UI says so rather than reporting a wrong total as fact.
	 *
	 * @param string $root     Absolute directory to walk.
	 * @param string $suffix   Case-insensitive filename suffix (e.g. '.swift-original').
	 * @param string $skip_dir Absolute directory to skip (our own backups).
	 * @return array{bytes: int, files: int, partial: bool}
	 */
	public static function suffix_stats( string $root, string $suffix, string $skip_dir = '' ): array {
		$bytes   = 0;
		$files   = 0;
		$entries = 0;
		$partial = false;

		if ( ! is_dir( $root ) ) {
			return [
				'bytes'   => 0,
				'files'   => 0,
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

			if ( ! self::has_suffix( $path, $suffix ) || ! $file->isFile() ) {
				continue;
			}

			$bytes += (int) $file->getSize();
			++$files;
		}

		return [
			'bytes'   => $bytes,
			'files'   => $files,
			'partial' => $partial,
		];
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
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- aggregate SUM over meta values; no meta-API equivalent, result is transient-cached by the caller.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS cnt, SUM(CAST(o.meta_value AS UNSIGNED)) AS orig_sum, SUM(CAST(n.meta_value AS UNSIGNED)) AS new_sum
				 FROM {$wpdb->postmeta} o
				 INNER JOIN {$wpdb->postmeta} n ON n.post_id = o.post_id AND n.meta_key = %s
				 WHERE o.meta_key = %s",
				'_lw_img_new_size',
				'_lw_img_original_size'
			)
		);

		$original  = (int) ( $row->orig_sum ?? 0 );
		$optimized = (int) ( $row->new_sum ?? 0 );
		$uploads   = (string) wp_get_upload_dir()['basedir'];

		$leftovers = [];

		// Path verified against the ShortPixel source: <uploads>/ShortpixelBackups.
		$shortpixel = self::dir_stats( $uploads . '/ShortpixelBackups' );
		if ( $shortpixel['files'] > 0 ) {
			$leftovers['ShortPixel'] = array_merge(
				$shortpixel,
				[
					'path'    => 'uploads/ShortpixelBackups',
					'partial' => false,
				]
			);
		}

		// Swift Performance kept the pre-optimization original beside the file
		// as "<name>.swift-original" instead of in a folder of its own.
		$swift = self::suffix_stats( $uploads, '.swift-original', ( new BackupStore() )->root() );
		if ( $swift['files'] > 0 ) {
			$leftovers['Swift Performance'] = array_merge(
				$swift,
				[ 'path' => 'uploads/**/*.swift-original' ]
			);
		}

		return [
			'count'         => (int) ( $row->cnt ?? 0 ),
			'original'      => $original,
			'optimized'     => $optimized,
			'saved'         => max( 0, $original - $optimized ),
			'percent'       => self::savings_percent( $original, $optimized ),
			'backup'        => self::dir_stats( ( new BackupStore() )->root() ),
			'leftovers'     => $leftovers,
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
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- top-N over the plugin's own meta; result is transient-cached by the caller.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.post_id, CAST(o.meta_value AS UNSIGNED) AS orig, CAST(n.meta_value AS UNSIGNED) AS new_size
				 FROM {$wpdb->postmeta} o
				 INNER JOIN {$wpdb->postmeta} n ON n.post_id = o.post_id AND n.meta_key = %s
				 WHERE o.meta_key = %s
				 ORDER BY CAST(o.meta_value AS UNSIGNED) - CAST(n.meta_value AS UNSIGNED) DESC
				 LIMIT %d",
				'_lw_img_new_size',
				'_lw_img_original_size',
				$limit
			)
		);

		$wins = [];
		foreach ( $rows as $win ) {
			$file   = (string) get_post_meta( (int) $win->post_id, '_wp_attached_file', true );
			$wins[] = [
				'file'     => '' !== $file ? basename( $file ) : '#' . (int) $win->post_id,
				'original' => (int) $win->orig,
				'new'      => (int) $win->new_size,
			];
		}

		return $wins;
	}
}
