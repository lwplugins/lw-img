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
	 * Nginx block that lets missing image requests under uploads fall
	 * through to WordPress while existing files are still served directly.
	 *
	 * A `^~` prefix location on purpose: it beats every regex location, so
	 * it also works when an earlier include (a typical static-asset block
	 * with `try_files $uri =404`) already matches image extensions. Inside
	 * a `^~` block the outer regex locations no longer apply, which is why
	 * the cache headers and the .php denial are repeated here.
	 *
	 * @param string $uploads_path Uploads URL path without a trailing slash
	 *                             (`/wp-content/uploads`, `/app/uploads` on Bedrock).
	 * @return string
	 */
	public static function nginx_fix( string $uploads_path ): string {
		return 'location ^~ ' . $uploads_path . "/ {\n"
			. "    location ~* \\.(?:jpe?g|png|gif|bmp|tiff?)$ {\n"
			. "        expires max; access_log off; log_not_found off;\n"
			. "        add_header Cache-Control \"public\";\n"
			. "        try_files \$uri /index.php\$is_args\$args;\n"
			. "    }\n"
			. "    location ~* \\.(?:css|js|ico|svg|woff2?|ttf|eot|webp|avif|mp4|webm|pdf)$ {\n"
			. "        expires max; access_log off; log_not_found off;\n"
			. "        add_header Cache-Control \"public\";\n"
			. "        try_files \$uri =404;\n"
			. "    }\n"
			. "    location ~* \\.php$ { deny all; }\n"
			. "    try_files \$uri =404;\n"
			. '}';
	}

	/**
	 * The check rows for the health report.
	 *
	 * Reads the same cached verdict the Bulk tab's start gate does: the
	 * Bulk panel renders before the Tester on the settings page, so a live
	 * probe here could show "OK" right next to a stale blocked start.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function rows(): array {
		return [ self::classify( RedirectProbe::cached(), self::uploads_path() ) ];
	}

	/**
	 * URL path of the uploads directory, without a trailing slash.
	 *
	 * @return string `/wp-content/uploads` when it cannot be derived.
	 */
	public static function uploads_path(): string {
		$path = wp_parse_url( (string) ( wp_get_upload_dir()['baseurl'] ?? '' ), PHP_URL_PATH );
		$path = is_string( $path ) ? trim( $path, '/' ) : '';

		return '' === $path ? '/wp-content/uploads' : '/' . $path;
	}

	/**
	 * Turn a probe outcome into a check row.
	 *
	 * @param bool|null $works        Probe result (null = could not verify).
	 * @param string    $uploads_path Uploads URL path, see uploads_path().
	 * @return array<string, string>
	 */
	public static function classify( ?bool $works, string $uploads_path = '/wp-content/uploads' ): array {
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
				'message' => __( 'Your web server answers missing image files itself, so WordPress never sees those requests and old URLs of bulk-converted images return 404 instead of redirecting. Bulk optimize will not start until this is fixed. For nginx, add the block below to the site config (a prefix location — a plain regex location would lose to earlier static-asset rules).', 'lw-img' ),
				'fix'     => self::nginx_fix( $uploads_path ),
			];
		}

		return [
			'label'   => $label,
			'status'  => 'info',
			'message' => sprintf(
				/* translators: %s: uploads URL path, e.g. /wp-content/uploads/. */
				__( 'Could not verify — the site was unable to request itself. Check manually that a missing image URL under %s shows a WordPress 404 page, not the web server\'s own.', 'lw-img' ),
				$uploads_path . '/'
			),
		];
	}
}
