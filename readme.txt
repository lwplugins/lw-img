=== LW Img ===
Contributors: lwplugins
Tags: image optimization, webp, image compression, performance, helloimg
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight image optimization — auto-convert WordPress uploads to WebP via the HelloImg API. No bloat, no upsell.

== Description ==

**Note:** LW Img is under active development and not yet recommended for production sites.

LW Img is a lightweight image optimization plugin that converts non-WebP uploads to WebP automatically using the HelloImg API. The original format is replaced — sub-sizes (thumbnails) are generated from the WebP source, so a single API call optimizes every variant.

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
* Content URL rewrite on bulk convert/restore (post content + page-builder data, serialization-aware)
* Media Library savings column, "Optimize now" / "Restore original" row actions, attachment info box, Compare, Re-optimize
* Exclusion patterns (wildcard filename/path rules) and min/max file size limits
* WP-CLI: wp lw-img status / optimize / restore / requeue

**Roadmap:**

* Smart Crop integration
* LW Site Manager Abilities API integration

== Installation ==

1. Upload `lw-img` to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Get an API key at [dashboard.helloimg.io/api-keys](https://dashboard.helloimg.io/api-keys)
4. Go to LW Plugins → Img and paste your API key
5. Upload images — they'll be converted to WebP automatically

== Frequently Asked Questions ==

= Does this keep the original JPEG / PNG file? =

The Media Library file is replaced by the WebP version, and WordPress sub-sizes (thumbnails) are generated from the WebP source. By default the original is kept as a backup in wp-content/uploads/lw-img-backups/ and can be restored any time from the Media Library ("Restore original"). Backups are cleaned up after the configured retention period (30 days by default, 0 = keep forever). Backups are never deleted on uninstall.

= What happens if my HelloImg balance runs out? =

The upload proceeds with the original format. No upload ever fails because of LW Img.

= Does this work with images already in the media library? =

Yes — use the Bulk tab (or `wp lw-img optimize --all`). The run happens in the background via WP-Cron, so you can close the browser; note WP-Cron only fires while the site receives traffic, so for libraries with thousands of images the command line is the guaranteed path. Converting renames the file (photo.jpg becomes photo.webp) and references in post content and page-builder data (Elementor/Bricks JSON in postmeta) are rewritten automatically — widgets and options are not covered, and images can always be excluded or restored.

= Is there a free tier? =

Yes. HelloImg includes 1,000 images/month free. After that, $0.001 per image.

== Changelog ==

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
