=== LW Img ===
Contributors: lwplugins
Tags: image optimization, webp, image compression, performance, helloimg
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight image optimization — auto-convert WordPress uploads to WebP via the HelloImg API. No bloat, no upsell.

== Description ==

**Note:** LW Img is under active development and not yet recommended for production sites.

LW Img is a lightweight image optimization plugin that converts non-WebP uploads to WebP automatically using the HelloImg API. The original format is replaced — sub-sizes (thumbnails) are generated from the WebP source, so a single API call optimizes every variant.

**Features (v1.0.0):**

* Auto-convert JPEG / PNG / HEIC / TIFF / BMP uploads to WebP on upload
* Already-WebP / AVIF uploads are skipped (no API call, no credit usage)
* Animated GIFs are skipped (animation would be lost)
* Graceful fallback — if the API is unreachable, the original upload is kept
* Three optimization levels: normal, aggressive, ultra
* Optional EXIF preservation
* Free tier: 1,000 images/month via HelloImg

**Roadmap:**

* Bulk optimize for the existing media library
* Smart Crop integration
* AI-generated alt text
* LW Site Manager Abilities API integration
* WP-CLI commands

== Installation ==

1. Upload `lw-img` to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Get an API key at [dashboard.helloimg.io/api-keys](https://dashboard.helloimg.io/api-keys)
4. Go to LW Plugins → Img and paste your API key
5. Upload images — they'll be converted to WebP automatically

== Frequently Asked Questions ==

= Does this keep the original JPEG / PNG file? =

No. The original is replaced by the WebP version. WordPress sub-sizes (thumbnails) are then generated from the WebP source.

= What happens if my HelloImg balance runs out? =

The upload proceeds with the original format. No upload ever fails because of LW Img.

= Does this work with images already in the media library? =

Not yet — v1.0.0 only converts new uploads. Bulk optimize is on the roadmap.

= Is there a free tier? =

Yes. HelloImg includes 1,000 images/month free. After that, $0.001 per image.

== Changelog ==

= 1.0.0 =
* New: Initial release
* New: Auto-convert non-WebP uploads to WebP via HelloImg API
* New: Settings page under LW Plugins menu (API key, optimization level, EXIF, skip rules)
* New: Connection test against HelloImg /v1/account endpoint
* New: Graceful fallback when the API is unreachable, the key is invalid, or the balance is exhausted
* New: Upload event log (last 200 events) with pagination and clear action
