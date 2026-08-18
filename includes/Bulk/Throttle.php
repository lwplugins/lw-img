<?php
/**
 * Paces bulk processing so it cannot starve the rest of the server.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Options;

/**
 * Three speed profiles (batch size, pause between images, gap between
 * cron ticks) plus a CPU load guard: long-running workers back off while
 * the 1-minute load average exceeds the profile's share of the cores.
 */
final class Throttle {

	public const SPEEDS = [ 'gentle', 'normal', 'fast' ];

	private const PROFILES = [
		'gentle' => [
			'batch'       => 2,
			'pause_ms'    => 2000,
			'tick_gap'    => 10,
			'load_factor' => 0.7,
		],
		'normal' => [
			'batch'       => 5,
			'pause_ms'    => 500,
			'tick_gap'    => 3,
			'load_factor' => 1.0,
		],
		'fast'   => [
			'batch'       => 10,
			'pause_ms'    => 0,
			'tick_gap'    => 1,
			'load_factor' => 1.5,
		],
	];

	/**
	 * Process-wide speed override (e.g. the CLI --speed flag).
	 *
	 * @var string|null
	 */
	private static ?string $override = null;

	/**
	 * Cached CPU core count.
	 *
	 * @var int|null
	 */
	private static ?int $cores = null;

	/**
	 * Force a speed for this process, ignoring the saved option.
	 *
	 * @param string|null $speed One of SPEEDS, or null to clear.
	 * @return void
	 */
	public static function force( ?string $speed ): void {
		self::$override = in_array( $speed, self::SPEEDS, true ) ? $speed : null;
	}

	/**
	 * Effective speed for this process.
	 *
	 * @return string
	 */
	public static function speed(): string {
		if ( null !== self::$override ) {
			return self::$override;
		}

		$saved = (string) Options::get( 'bulk_speed', 'normal' );

		return in_array( $saved, self::SPEEDS, true ) ? $saved : 'normal';
	}

	/**
	 * Items to claim per batch.
	 *
	 * @param string|null $speed Speed override; null uses the effective speed.
	 * @return int
	 */
	public static function batch_size( ?string $speed = null ): int {
		return self::PROFILES[ $speed ?? self::speed() ]['batch'];
	}

	/**
	 * Pause between two images in milliseconds.
	 *
	 * @param string|null $speed Speed override; null uses the effective speed.
	 * @return int
	 */
	public static function pause_ms( ?string $speed = null ): int {
		return self::PROFILES[ $speed ?? self::speed() ]['pause_ms'];
	}

	/**
	 * Seconds between two cron ticks.
	 *
	 * @param string|null $speed Speed override; null uses the effective speed.
	 * @return int
	 */
	public static function tick_gap( ?string $speed = null ): int {
		return self::PROFILES[ $speed ?? self::speed() ]['tick_gap'];
	}

	/**
	 * 1-minute load average above which processing backs off.
	 *
	 * @param int|null    $cores CPU core count; null detects.
	 * @param string|null $speed Speed override; null uses the effective speed.
	 * @return float
	 */
	public static function load_limit( ?int $cores = null, ?string $speed = null ): float {
		$factor = self::PROFILES[ $speed ?? self::speed() ]['load_factor'];

		return max( 2.0, ( $cores ?? self::cpu_cores() ) * $factor );
	}

	/**
	 * Detected CPU core count (Linux /proc/cpuinfo; conservative fallback).
	 *
	 * @return int
	 */
	public static function cpu_cores(): int {
		if ( null !== self::$cores ) {
			return self::$cores;
		}

		$count = 0;
		if ( is_readable( '/proc/cpuinfo' ) ) {
			$lines = (array) file( '/proc/cpuinfo' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file -- reading a kernel pseudo-file, not site content.
			$count = count( (array) preg_grep( '/^processor\s/', $lines ) );
		}

		self::$cores = $count > 0 ? $count : 2;

		return self::$cores;
	}

	/**
	 * Whether the current load exceeds the limit.
	 *
	 * @param float|null $load 1-minute load average; null reads the system's.
	 * @return bool False when the platform cannot report load.
	 */
	public static function is_overloaded( ?float $load = null ): bool {
		if ( null === $load ) {
			if ( ! function_exists( 'sys_getloadavg' ) ) {
				return false;
			}
			$avg  = sys_getloadavg();
			$load = is_array( $avg ) ? (float) $avg[0] : 0.0;
		}

		return $load > self::load_limit();
	}

	/**
	 * Sleep the configured pause between two images.
	 *
	 * @return void
	 */
	public static function pause(): void {
		$ms = self::pause_ms();

		if ( $ms > 0 ) {
			usleep( $ms * 1000 );
		}
	}

	/**
	 * Back off briefly while the server is overloaded (long workers only).
	 *
	 * Only active under WP-CLI or a real cron request — a browser-driven
	 * assist burst must respond quickly, so it never waits here. The wait
	 * is capped at 30 seconds: in containers the load average often
	 * reflects the whole host, so the guard must only slow processing
	 * down, never starve it.
	 *
	 * @param int $deadline Unix timestamp after which waiting stops.
	 * @return void
	 */
	public static function wait_for_headroom( int $deadline ): void {
		$is_worker = ( defined( 'WP_CLI' ) && WP_CLI ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() );

		if ( ! $is_worker ) {
			return;
		}

		$wait_until = min( $deadline, time() + 30 );

		while ( time() + 5 <= $wait_until && self::is_overloaded() ) {
			sleep( 5 );
		}
	}
}
