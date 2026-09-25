<?php
/**
 * Whether a bulk run may start, and why not.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

/**
 * Pure decision behind the Start button: the screen shows the reasons,
 * and the start route enforces the same rules with live checks.
 */
final class BulkGate {

	public const RUNNING         = 'running';
	public const NO_KEY          = 'no_key';
	public const REDIRECTS       = 'redirects';
	public const NOTHING_PENDING = 'nothing_pending';

	/**
	 * Reasons that block a start, most important first.
	 *
	 * @param bool      $running      Whether a run is in progress.
	 * @param bool      $key_set      Whether an API key is configured.
	 * @param bool|null $redirects_ok Redirect probe verdict (null = could not verify, which does not block).
	 * @param int       $pending      Images waiting to be optimized.
	 * @return array<int, string>
	 */
	public static function reasons( bool $running, bool $key_set, ?bool $redirects_ok, int $pending ): array {
		if ( $running ) {
			return [ self::RUNNING ];
		}

		$reasons = [];

		if ( ! $key_set ) {
			$reasons[] = self::NO_KEY;
		}

		if ( false === $redirects_ok ) {
			$reasons[] = self::REDIRECTS;
		}

		if ( $pending <= 0 ) {
			$reasons[] = self::NOTHING_PENDING;
		}

		return $reasons;
	}

	/**
	 * The gate as the screen needs it.
	 *
	 * @param array<int, string> $reasons Reason codes.
	 * @return array{can_start: bool, reasons: array<int, array{code: string, message: string}>}
	 */
	public static function describe( array $reasons ): array {
		return [
			'can_start' => [] === $reasons,
			'reasons'   => array_map(
				static fn ( string $code ): array => [
					'code'    => $code,
					'message' => self::message( $code ),
				],
				$reasons
			),
		];
	}

	/**
	 * Human-readable reason.
	 *
	 * @param string $code Reason code.
	 * @return string
	 */
	public static function message( string $code ): string {
		switch ( $code ) {
			case self::RUNNING:
				return __( 'A run is already in progress.', 'lw-img' );
			case self::NO_KEY:
				return __( 'Set your API key on the General tab first.', 'lw-img' );
			case self::REDIRECTS:
				return __( 'This server answers missing image files itself, so old URLs of converted images would return 404 instead of redirecting — external links and search results would break. Fix the web server first (see the Tester tab).', 'lw-img' );
			default:
				return __( 'Nothing to optimize: every image in the Media Library has been processed. Retry failed or re-scan skipped images to queue them again.', 'lw-img' );
		}
	}
}
