<?php
/**
 * Health check: do old-image redirects have a chance to run?
 *
 * @package LightweightPlugins\Img
 */

declare(strict_types=1);

namespace LightweightPlugins\Img\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Classifies the RedirectProbe outcome into a Tester row. A server that
 * swallows uploads 404s breaks the 301 safety net for bulk-converted
 * images (external links, search engines) — the bulk run refuses to
 * start in that state.
 */
final class RedirectChecks {

	/**
	 * Nginx location block that lets missing image requests fall through
	 * to WordPress while existing files are still served directly.
	 */
	public const NGINX_FIX = 'location ~* ^/wp-content/uploads/.*\.(png|jpe?g|gif|bmp|tiff?)$ { try_files $uri /index.php?$args; }';

	/**
	 * The check rows for the health report.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function rows(): array {
		return [ self::classify( RedirectProbe::works() ) ];
	}

	/**
	 * Turn a probe outcome into a check row.
	 *
	 * @param bool|null $works Probe result (null = could not verify).
	 * @return array<string, string>
	 */
	public static function classify( ?bool $works ): array {
		$label = __( 'Old-image redirects', 'lw-img' );

		if ( true === $works ) {
			return [
				'label'   => $label,
				'status'  => 'ok',
				'message' => __( 'Missing-image requests reach WordPress — old URLs of converted images 301-redirect to the new files.', 'lw-img' ),
			];
		}

		if ( false === $works ) {
			return [
				'label'   => $label,
				'status'  => 'warning',
				'message' => __( 'Your web server answers missing image files itself, so WordPress never sees those requests and old URLs of bulk-converted images return 404 instead of redirecting. Bulk optimize will not start until this is fixed. For nginx, add the block below to the site config.', 'lw-img' ),
				'fix'     => self::NGINX_FIX,
			];
		}

		return [
			'label'   => $label,
			'status'  => 'info',
			'message' => __( 'Could not verify — the site was unable to request itself. Check manually that a missing image URL under /wp-content/uploads/ shows a WordPress 404 page, not the web server\'s own.', 'lw-img' ),
		];
	}
}
