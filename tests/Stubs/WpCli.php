<?php
/**
 * Minimal WP_CLI double for unit tests (WP-CLI is not loaded).
 *
 * Records the output calls instead of printing them.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

// phpcs:ignoreFile -- test double mirroring the WP-CLI class name.

class WP_CLI {

	/**
	 * Recorded calls as [ method, message ] pairs.
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	public static array $calls = [];

	public static function success( string $message ): void {
		self::$calls[] = [ 'success', $message ];
	}

	public static function log( string $message ): void {
		self::$calls[] = [ 'log', $message ];
	}

	public static function warning( string $message ): void {
		self::$calls[] = [ 'warning', $message ];
	}
}
