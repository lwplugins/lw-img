# Changelog

## [1.6.0] - 2026-08-17

### Changed
- Display name is now **LW Image** (plugin header, admin page title, menu item, Media Library column, attachment box) — slugs, text domain, and the repository stay `lw-img`

### Added
- Redesigned General tab: status-first connection hero (Connected / Not connected / Error pill, API key field with show/hide toggle, Test connection), account tiles — balance shows "Unlimited" during the open beta, free-tier gauge with progress bar, and this site's own optimization total linking to Stats — plus a "current defaults" strip (output format, level, EXIF, backups, bulk speed) whose chips jump to the tab where each setting lives
- First-run onboarding: with no API key the General tab shows a three-step guide (get a key, upload as usual, bulk-optimize the library) and a reversibility note
- Redesigned Stats tab: savings hero with a before/after bar, tiles (share of the Media Library, average saving per image, backup folder), a Biggest wins top-5 list from the plugin's own size meta, the leftover-backup notice as a warning card, and an empty state that points to the Bulk tab
- Redesigned Upload tab: master auto-convert toggle with a pipeline strip (Upload → Convert → Back up → Thumbnails), settings grouped into Conversion / Size limits / Skip & exclude / After conversion, segmented controls for output format and level, toggle switches, a file-size range row, and clickable exclusion-pattern examples; turning auto-convert off dims the sections
- Redesigned Backup tab: master toggle with a lifecycle strip (Original saved → backup folder → Restorable → Cleaned up after N days), live storage tiles, retention presets (7/30/90 days, 1 year, Forever), a restore guide, and a red warning card while backups are off — backups stay on by default
- Redesigned Tester tab: verdict hero (all-green "ready for bulk optimization" or issue counts), a needs-attention block that lifts warnings and criticals above the sections with copyable fix commands (e.g. the InnoDB conversion), and section cards whose headers show each group's worst status
- Admin UI uses icons (Dashicons/SVG) everywhere — no emoji

## [1.5.0] - 2026-08-17

### Added
- Stats tab: optimized image count, total storage saved with ratio, LW Img backup folder size, and detection of leftover backup folders from other optimizers (e.g. a previously installed ShortPixel) with size and file count — cached for an hour with a refresh button
- Redesigned Bulk tab: progress dashboard with a segmented bar (optimized/skipped/failed composition), semantic stat tiles, elapsed/speed/ETA/saved-so-far row, a live "Now processing" line, a recent-activity feed with result chips, skip-reason chips, count-aware action buttons, and a summary banner when a run finishes
- Skip-reason breakdown on the Bulk tab and in `wp lw-img status` (e.g. "file missing" for ghost attachment records)
- `wp lw-img status` also reports storage saved and backup folder sizes
- Batched content URL rewrite during bulk runs: rewrite pairs of many images are applied in one pass, so a batch costs the same table scans as a single image did before (on large databases this was the dominant per-image cost)
- Parallel-safe queue claiming (MySQL advisory lock + claim meta with expiry): several `wp lw-img optimize --all` workers, the cron worker, and poll assists can drain the same queue without double-processing — running multiple CLI workers in parallel is now the fastest path for huge libraries
- CLI optimize progress is mirrored into an active background job, so the Bulk tab dashboard stays live while CLI workers run
- Processing speed setting (gentle / normal / fast) on the Bulk tab and as `wp lw-img optimize --speed=...` — controls batch size, pause between images, and cron tick spacing
- CPU load guard: long-running workers back off in 5-second steps while the server's 1-minute load average exceeds the profile's share of the CPU cores, so bulk runs cannot starve the site
- Quota halt: when the API reports the balance is exhausted (402 / insufficient_balance), the run stops immediately instead of stamping every remaining image as failed — pending images stay queued and resume after a top-up
- Tester tab: environment checks with status pills — database engine per core table (MyISAM/Aria tables get a conversion hint: with table-level locking a bulk run can freeze the whole site), PHP version and memory, cURL, Imagick/GD with WebP/AVIF thumbnail support, uploads/backup writability, free disk space, WP-Cron mode and loopback reachability, HelloImg API reachability, and active competitor optimizers; cached for 10 minutes with a re-run button

### Fixed
- On hosts with `DISABLE_WP_CRON` and a system-cron runner the background worker now uses a much larger per-tick budget under WP-CLI (20 minutes instead of 15 seconds, with in-process cache hygiene to keep memory flat), so runs no longer crawl in short bursts between cron passes
- JSON-escaped URLs in post_content (e.g. Gutenberg block attributes) are now rewritten too, not only in page-builder meta
- HTTP 429 / rate-limit responses are classified as transient failures, so they are retried automatically instead of being stamped permanent
- A lost bulk tick is automatically re-scheduled while a run is active (self-heal on init)
- While the Bulk tab is open, the status poll itself processes a short burst when the run has stalled — runs keep moving even on hosts whose cron loopback request fails

## [1.4.0] - 2026-08-17

### Added
- Slow-job handling: when the API returns 408 with a poll URL, the job is polled for a bounded time instead of failing — slow optimizations complete instead of erroring
- Transient/permanent failure classification (based on the documented HelloImg error codes): network errors, timeouts, and 5xx responses are marked transient
- Automatic retry pass: at the end of a background bulk run, transiently-failed images are re-queued once automatically; permanent failures still wait for an explicit "Retry failed"
- Lossless optimization level (pixel-perfect) in settings, re-optimize actions, and CLI
- Request timeout setting on the General tab (5–120 s)

## [1.3.0] - 2026-08-17

### Added
- Content URL rewrite now covers every meta table (post, comment, term, user) and options too — the same serialization-aware replace, transients skipped
- Old-URL 301 redirect: requests for a converted image's old URL (external links, sent newsletters, search engines) are permanently redirected to the new file instead of 404 — sub-size URLs are matched by width; enabled by default, can be turned off in the Upload tab

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
