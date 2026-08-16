# Changelog

## [1.1.0] - 2026-08-16

### Added
- Original image backup: before an upload is replaced with the optimized version, the original is copied to `wp-content/uploads/lw-img-backups/` (enabled by default)
- Restore original: "Restore original" row action in the Media Library — moves the original back, removes the optimized files, and regenerates thumbnails
- Backup retention: daily cleanup task deletes backups older than the configured number of days (default 30; 0 = keep forever); backups are also removed when their attachment is deleted
- Bulk optimize: convert the existing Media Library from the new Bulk settings tab (batched AJAX run with progress) or via WP-CLI
- WP-CLI commands: `wp lw-img status`, `wp lw-img optimize [<id>...|--all] [--limit] [--dry-run]`, `wp lw-img restore <id>...`
- Media Library integration: "LW Img" savings column and an "Optimize now" row action for unoptimized images
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
