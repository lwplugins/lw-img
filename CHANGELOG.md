# Changelog

## [1.2.0] - 2026-08-16

### Added
- Respect other optimizers' work: images already optimized by ShortPixel, TinyPNG (Tinify), or Imagify (detected via their own postmeta, verified against each plugin's source) are skipped by bulk and on-demand optimization, and the Media Library shows which plugin manages them
- Competitor warning: when another image optimizer is active (ShortPixel, TinyPNG, Imagify, Smush, EWWW), a dismissible admin notice suggests deactivating one — warning only, nothing is blocked; the notice returns if the set of active optimizers changes
- `lw_img_competitor_plugins` filter to extend the competitor list

## [1.1.0] - 2026-08-16

### Added
- Original image backup: before an upload is replaced with the optimized version, the original is copied to `wp-content/uploads/lw-img-backups/` (enabled by default)
- Restore original: "Restore original" row action in the Media Library — moves the original back, removes the optimized files, and regenerates thumbnails
- Backup retention: daily cleanup task deletes backups older than the configured number of days (default 30; 0 = keep forever); backups are also removed when their attachment is deleted
- Bulk optimize built for large libraries: background WP-Cron worker (runs with the browser closed), per-attempt status meta making runs resumable, persistent job record with live progress, Retry failed / Re-scan skipped re-queue actions
- Content URL rewrite on bulk convert and restore: references in post content and page-builder data (postmeta, serialization-aware, JSON-escaped variants included) are updated to the new file URLs
- WP-CLI commands: `wp lw-img status`, `wp lw-img optimize [<id>...|--all] [--limit] [--dry-run]`, `wp lw-img restore <id>...`, `wp lw-img requeue [--failed] [--skipped]`
- Media Library integration: "LW Img" savings column, "Optimize now" row action, attachment-screen info box (savings, level, EXIF, date, thumbnail count), Compare page (backup vs optimized), Re-optimize from backup at another level
- Min file size exclusion (skip tiny images)
- Exclusion patterns: wildcard filename/path rules that keep matching uploads unconverted
- Output format setting: convert to WebP (default) or AVIF
- Resize on upload/bulk: optional max width/height (proportional downscale, never upscales)
- Animated GIFs are now converted to animated WebP (frames and timing preserved); the skip option remains available
- Size guard: if the converted file would not be smaller than the original, the original is kept
- Account panel is labelled "Preview data" while HelloImg billing returns placeholder figures
- Backup and Bulk settings tabs
- `lw_img_restored` action and `restored` event log status

## [1.0.0] - 2026-08-16

### Added
- Initial release
- Auto-convert non-WebP uploads to WebP via the HelloImg API
- Settings page under the LW Plugins menu (API key, optimization level, EXIF, skip rules)
- Connection test against HelloImg `/v1/account` endpoint
- Graceful fallback when the API is unreachable, the key is invalid, or the balance is exhausted
- Upload event log (last 200 events) with pagination and clear action
- Unit test suite (PHPUnit + Brain Monkey), PHPStan level 5, PHPCS (WPCS) — all wired into CI
