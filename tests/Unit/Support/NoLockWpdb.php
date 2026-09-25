<?php
/**
 * Minimal $wpdb stand-in for code that takes MySQL advisory locks.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Tests\Unit\Support;

/**
 * Answers every GET_LOCK with "0" (no lock support), which the lock helpers
 * treat as "proceed unlocked" — enough for tests that are not about locking.
 */
final class NoLockWpdb {

	/**
	 * Table prefix (scopes lock names).
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * @param string $sql     Query with placeholders.
	 * @param mixed  ...$args Values (ignored).
	 */
	public function prepare( string $sql, ...$args ): string {
		return $sql;
	}

	/**
	 * @param string $sql Query (ignored).
	 */
	public function get_var( string $sql ): string {
		return '0';
	}
}
