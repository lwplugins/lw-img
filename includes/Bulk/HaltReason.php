<?php
/**
 * Why a bulk run stopped on its own.
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Bulk;

defined( 'ABSPATH' ) || exit;

use LightweightPlugins\Img\Api\ApiException;

/**
 * The failures that stop a whole run instead of failing one image — every
 * further request would fail the same way, and the images themselves are
 * fine. The reason is stored on the job so the dashboard can say why the
 * run ended, instead of silently dropping back to the start screen.
 */
final class HaltReason {

	public const NO_KEY = 'no_key';
	public const QUOTA  = 'quota';
	public const AUTH   = 'auth';

	/**
	 * The halt reason an API error stands for, or null when it only fails
	 * the one image.
	 *
	 * @param ApiException $error API error.
	 * @return string|null
	 */
	public static function from_exception( ApiException $error ): ?string {
		if ( $error->is_quota() ) {
			return self::QUOTA;
		}

		if ( $error->is_auth() ) {
			return self::AUTH;
		}

		return null;
	}

	/**
	 * Human-readable explanation, translated for the current viewer.
	 *
	 * @param string $reason Reason code.
	 * @return string
	 */
	public static function message( string $reason ): string {
		switch ( $reason ) {
			case self::NO_KEY:
				return __( 'The run stopped because no API key is configured. Add the key on the General tab, then start again.', 'lw-img' );
			case self::QUOTA:
				return __( 'The run stopped because your HelloImg account has no credit left. Top up or change your plan, then start again — the remaining images are still queued.', 'lw-img' );
			case self::AUTH:
				return __( 'The run stopped because the API rejected the key (revoked, replaced, or issued for another site). Check the key on the General tab, then start again.', 'lw-img' );
			default:
				return __( 'The run stopped because of an API error. The remaining images are still queued.', 'lw-img' );
		}
	}
}
