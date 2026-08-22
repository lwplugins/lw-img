=== LW Image ===
Contributors: lwplugins
Tags: image optimization, webp, image compression, performance, helloimg
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight image optimization — auto-convert WordPress uploads to WebP via the HelloImg API. No bloat, no upsell.

== Description ==

LW Image is a lightweight image optimization plugin that converts non-WebP uploads to WebP automatically using the HelloImg API. The original format is replaced — sub-sizes (thumbnails) are generated from the WebP source, so a single API call optimizes every variant.

**Features:**

* Auto-convert JPEG / PNG / GIF / HEIC / TIFF / BMP uploads to WebP or AVIF on upload
* Already-WebP / AVIF uploads are skipped (no API call, no credit usage)
* Animated GIFs become animated WebP (frames and timing preserved; optional skip)
* Optional resize on upload (max width/height, never upscales)
* Size guard: the original is kept if the converted file would not be smaller
* Graceful fallback — if the API is unreachable, the original upload is kept
* Original image backup (on by default) with configurable retention — restore any optimized image from the Media Library, thumbnails are regenerated automatically
* Three optimization levels: normal, aggressive, ultra
* Optional EXIF preservation
* Free tier: 1,000 images/month via HelloImg

* Bulk optimize the existing Media Library in the background (WP-Cron worker, resumable, or WP-CLI for huge libraries)
* Smart crop (opt-in): re-crop selected hard-cropped thumbnail sizes around the subject instead of the centre — you pick the sizes and see the per-upload API cost before enabling
* Built for scale: parallel-safe queue claiming, processing-speed profiles (gentle/normal/fast), and a CPU load guard so a bulk run never starves the site
* Content URL rewrite on bulk convert/restore (post content + page-builder data, serialization-aware) plus a 301 redirect from old image URLs
* Tabbed admin: General, Stats, Upload, Bulk, Backup, Tester, and Log
* Tester tab: environment checks (database table engines, WebP thumbnail support, cron loopback, disk space, API reachability) that catch hosting problems before a bulk run
* Stats tab: total savings, biggest wins, backup folder size, and originals left behind by other optimizers (ShortPixel backup folders and Swift Performance .swift-original files)
* Recognizes images already optimized by ShortPixel, TinyPNG, Imagify, Smush, or EWWW and leaves them untouched
* Media Library savings column, "Optimize now" / "Restore original" row actions, attachment info box, Compare, Re-optimize
* Exclusion patterns (wildcard filename/path rules) and min/max file size limits
* WP-CLI: wp lw-img status / optimize / restore / requeue / leftovers

**Roadmap:**

* LW Site Manager Abilities API integration

== Installation ==

1. Upload `lw-img` to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Get an API key at [dashboard.helloimg.io/api-keys](https://dashboard.helloimg.io/api-keys)
4. Go to LW Plugins → Image and paste your API key
5. Upload images — they'll be converted to WebP automatically

== Frequently Asked Questions ==

= Does this keep the original JPEG / PNG file? =

The Media Library file is replaced by the WebP version, and WordPress sub-sizes (thumbnails) are generated from the WebP source. By default the original is kept as a backup in wp-content/uploads/lw-img-backups/ and can be restored any time from the Media Library ("Restore original"). Backups are cleaned up after the configured retention period (30 days by default, 0 = keep forever). Backups are never deleted on uninstall.

= What happens if my HelloImg balance runs out? =

The upload proceeds with the original format. No upload ever fails because of LW Image.

= Does this work with images already in the media library? =

Yes — use the Bulk tab (or `wp lw-img optimize --all`). The run happens in the background via WP-Cron, so you can close the browser; note WP-Cron only fires while the site receives traffic, so for libraries with thousands of images the command line is the guaranteed path. Converting renames the file (photo.jpg becomes photo.webp) and references in post content and page-builder data (Elementor/Bricks JSON in postmeta) are rewritten automatically — widgets and options are not covered, and images can always be excluded or restored.

= What does smart crop cost? =

One extra API call per selected size per upload — the Upload tab shows the exact multiplier as you pick sizes. Sizes that keep the aspect ratio never appear in the list: nothing is cut off them, so a plain resize gives the same result. If a crop fails, the normal WordPress thumbnail simply stays in place.

= Is there a free tier? =

Yes. HelloImg includes 1,000 images/month free. After that, $0.001 per image.

== Changelog ==

= 1.8.0 =
* New: smart crop — opt-in subject-aware re-cropping of the hard-cropped thumbnail sizes you select, on new uploads. Runs in the background, never blocks an upload, and shows its per-upload API cost before you enable it
* New: per-size failures keep the WordPress thumbnail; a quota error stops the remaining sizes

= 1.7.2 =
* Change: LW Image is out of pre-release. The "under active development" notice is gone from the readme, the README and every future release note

= 1.7.1 =
* Update: Tested up to WordPress 7.1.

= 1.7.0 =
* Security: the redirect for missing images could be made to run an expensive, unindexed database lookup by anyone requesting a non-existent image URL — it is now rate-limited
* Security: file names are stripped of characters that could break out of the API request's headers, the optimized download is verified to be an image before it replaces anything, and every plugin file now refuses to run when loaded directly
* Change: per-image records moved from postmeta into the plugin's own table — one row per image instead of eleven, so the bulk queue and the Stats totals no longer search the site-wide meta table
* Fix: the Media Library left skipped images blank, so you could not tell whether anything had happened. Images we skipped because the optimized file was no smaller now show a green 0% and "already as small as it gets"; other skips and failures show their reason, and the action reads "Try again" once there is a result
* Note: the plugin is still pre-release and there is no upgrade path from the old layout — images recorded by an earlier version re-enter the queue
* Note: backup paths stay in postmeta, and uninstall now drops the plugin's table

= 1.6.2 =
* New: Stats tab finds leftover originals from ShortPixel, Imagify, EWWW, Swift Performance and Smush — both backup folders and the originals some of them save next to each image (.swift-original, .bak.jpg) — so you can reclaim that disk space
* New: wp lw-img leftovers — the same report on the command line (--rescan, --format=json)
* New: images already optimized by Smush or EWWW are recognized and left untouched (previously only ShortPixel, TinyPNG and Imagify were)
* Note: the uploads scan is bounded on very large libraries; when it stops early the total is shown as "at least"

= 1.6.1 =
* Security: prevent cross-extension overwrite — a converted upload can no longer overwrite an unrelated attachment's file (wp_unique_filename on the target)
* Security: stop PHP object injection in the content URL rewrite — foreign serialized meta/option rows are decoded with objects disallowed and left untouched if they contain any
* Security: pin the slow-job poll URL to the API host (SSRF) and contain backup file paths against directory traversal
* Security: protect the backup folder (index.php + .htaccess) and stop autoloading the API key option
* Fix: "Log cleared" and "Settings saved" confirmations now actually appear
* Fix: numeric settings are clamped server-side; invalid non-array option writes no longer fatal
* Accessibility: every settings field has an accessible name again, and the tab rail shows a keyboard focus ring
* Hardening: guard against a bulk-queue SQL error on empty mime types, a CPU-detection TypeError, and a white screen if the remote plugin registry is malformed
* Docs: refreshed README and repository metadata for the LW Image name

= 1.6.0 =
* Change: display name is now LW Image (slugs and text domain stay lw-img)
* New: Redesigned Stats tab — savings hero with before/after bar, biggest-wins list, leftover-backup warning card, and an empty state
* New: Redesigned Upload tab — master toggle with pipeline strip, grouped settings, segmented controls, and clickable exclusion examples
* New: Redesigned Backup tab — lifecycle strip, live storage tiles, retention presets, restore guide, and an off-state warning
* New: Redesigned Tester tab — verdict hero, needs-attention block with copyable fix commands, and per-section worst-status cards
* New: Redesigned Log tab — status filter chips with counts, filename search, feed-style rows, and reload-free pagination
* New: Redesigned General tab — connection status pill with the API key field and show/hide toggle, account tiles (balance shows Unlimited during the open beta, free-tier gauge, this site's optimization total), a "current defaults" strip linking to each setting's tab, and a three-step onboarding when no key is set

= 1.5.0 =
* New: Stats tab — total savings, backup folder size, and leftover backup folders from other optimizers (e.g. ShortPixel)
* New: Redesigned Bulk tab — progress dashboard with segmented bar, stat tiles, elapsed/speed/ETA, live activity feed, and a finish summary
* New: Skip-reason breakdown on the Bulk tab and in wp lw-img status
* New: Batched content URL rewrite during bulk runs — large-database bulk optimization is several times faster
* New: Parallel-safe queue claiming — multiple wp lw-img optimize --all workers can drain the queue together; CLI progress shows up live on the Bulk tab
* New: Processing speed setting (gentle / normal / fast) with a CPU load guard — bulk runs back off while the server is busy and can never starve the site
* New: Quota halt — an exhausted API balance stops the run instead of failing every remaining image; the queue resumes after a top-up
* New: Tester tab — environment checks (database table engines, WebP thumbnail support, cron loopback, disk space, API reachability and more) so hosting problems surface before a bulk run trips over them
* Fix: much larger background worker budget under WP-CLI system cron (DISABLE_WP_CRON hosts no longer crawl)
* Fix: lost bulk ticks are re-scheduled automatically while a run is active
* Fix: with the Bulk tab open, a stalled run is kept moving by the status poll itself (works even when the host's cron loopback fails)
* Fix: JSON-escaped URLs in post_content (Gutenberg block attributes) are rewritten too
* Fix: HTTP 429 rate-limit responses are retried automatically instead of failing permanently

= 1.4.0 =
* New: Slow jobs are polled instead of failing (API 408 + poll URL handling)
* New: Transient failures (timeouts, network, 5xx) are retried automatically once at the end of a bulk run
* New: Lossless optimization level
* New: Request timeout setting on the General tab

= 1.3.0 =
* New: Content URL rewrite extended to all meta tables (post/comment/term/user) and options
* New: Old-URL 301 redirect — requests for a converted image's old URL are redirected to the new file instead of 404 (default on)

= 1.2.0 =
* New: Images already optimized by ShortPixel, TinyPNG (Tinify), or Imagify are recognized and left untouched
* New: Dismissible warning when another image optimizer plugin is active (ShortPixel, TinyPNG, Imagify, Smush, EWWW)
* New: lw_img_competitor_plugins filter

= 1.1.0 =
* New: Original image backup before conversion (on by default), stored in wp-content/uploads/lw-img-backups/
* New: "Restore original" row action in the Media Library — restores the original and regenerates thumbnails
* New: Backup retention — daily cleanup of backups older than the configured days (default 30, 0 = forever); backups are removed with their attachment
* New: Bulk optimize for large Media Libraries — background WP-Cron worker (keeps running with the browser closed), resumable runs, Retry failed / Re-scan skipped
* New: Content URL rewrite on bulk convert/restore — post content and page-builder data are updated to the new file URLs (serialization-aware)
* New: WP-CLI commands — wp lw-img status / optimize / restore / requeue
* New: "LW Img" savings column, "Optimize now" row action, attachment info box, Compare page, and Re-optimize at another level
* New: Min file size exclusion
* New: Exclusion patterns (wildcard filename/path rules)
* New: Output format setting — WebP (default) or AVIF
* New: Resize on upload/bulk — optional max width/height, never upscales
* New: Animated GIF to animated WebP conversion (skip option remains)
* New: Size guard — the original is kept if the converted file would not be smaller
* New: Backup and Bulk settings tabs
* New: lw_img_restored action and "restored" event log status

= 1.0.0 =
* New: Initial release
* New: Auto-convert non-WebP uploads to WebP via HelloImg API
* New: Settings page under LW Plugins menu (API key, optimization level, EXIF, skip rules)
* New: Connection test against HelloImg /v1/account endpoint
* New: Graceful fallback when the API is unreachable, the key is invalid, or the balance is exhausted
* New: Upload event log (last 200 events) with pagination and clear action
