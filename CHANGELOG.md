# Changelog

## [1.7.1] - 2026-08-20

### Changed
- Tested up to WordPress 7.1.

## [1.7.0] - 2026-08-18

### Security
- Rate-limit the 404 image redirect's fallback lookup. That query searches postmeta by value, which no index covers — on a 95k-image library it examined ~216,000 rows and cost ~0.11s, and any anonymous request for a missing image path under the uploads directory reached it. A rolling 60-second budget in a single transient now caps how often it can run; the cheap indexed backup-path lookup is untouched, so images we actually converted still redirect. Deliberately one counter rather than a per-path negative cache, which would have written two options rows per probe on sites without an external object cache
- Strip quotes and control characters from the file name embedded in the API request's `Content-Disposition` header. Uploads were already safe (`sanitize_file_name()` removes those), but images optimized from the Media Library take their name from `_wp_attached_file`, which an importer, an FTP drop, or a direct meta write can put a quote or a newline into — enough to close the quoted value and have the rest read as further header lines
- Verify the optimized download is really an image before it replaces anything. The response now has a size ceiling and its leading bytes are checked against the formats the API can return (JPEG, PNG, GIF, WebP, AVIF); anything else aborts the swap with the original still in place. Magic bytes rather than `getimagesizefromstring()`, whose AVIF support depends on the PHP build
- Add a direct-access guard to every PHP file (only the main plugin file had one), so a request straight to a class file cannot produce a path-disclosing fatal
- Enforce `ImageRepository::save()`'s column list instead of asserting it in a comment: the method interpolates the caller's array keys into the statement, so anything outside the schema is now dropped before it can reach SQL

### Changed
- Per-image records now live in the plugin's own `{prefix}lw_img_images` table instead of eleven postmeta rows per image. The bulk queue's "what is still pending" question was an anti-join across the site-wide, EAV-shaped postmeta table; it is now a single join against a table of our own size, with an index built for exactly that question. The savings totals were a self-join over the same table and are now one indexed scan
- `Bulk\StatusMeta` keeps its role as the queue's vocabulary but delegates every read and write to `Db\ImageRepository`, which is the only class that touches the table
- The plugin is still pre-release, so there is no upgrade path from the old meta layout: images recorded by an earlier version are seen as unprocessed and re-enter the queue

### Fixed
- The Media Library column and the attachment box left a skipped image blank, which read as "nothing happened here" and invited people to run it again and again. Both now say what happened: an image skipped because the optimized file came back no smaller shows a green **0%** with "Skipped — already as small as it gets", other skips show their reason, and failures show theirs in red. The row action and the button read "Try again" instead of "Optimize now" once we have a result for that image
- The green used for savings figures now clears 4.5:1 against the admin background (the WordPress success green does not)

### Added
- `Db\Schema` creates the table on activation and whenever the schema version moves

### Removed
- The `_lw_img_status`, `_lw_img_status_detail`, `_lw_img_status_transient`, `_lw_img_claimed`, `_lw_img_optimized`, `_lw_img_original_size`, `_lw_img_new_size`, `_lw_img_savings_pct`, `_lw_img_job_id`, `_lw_img_level`, `_lw_img_keep_exif` and `_lw_img_optimized_at` meta keys. `_lw_img_backup_path` stays in postmeta: it points at files that survive uninstall
- Uninstall drops the plugin's table and clears the new options

## [1.6.2] - 2026-08-18

### Added
- Stats tab now reports leftover originals from every optimizer we know, in both shapes they take. Backup folders: ShortPixel (`uploads/ShortpixelBackups`), Imagify (`uploads/backup` and the site-root `imagify-backup`), EWWW (`wp-content/image-backup` — outside uploads). Originals dropped beside each image: Swift Performance (`<name>.swift-original`) and Smush (`<name>.bak.<ext>`). Every path and pattern was verified against that plugin's own source. Each row shows its location, size and file count, on the Stats tab and in `wp lw-img status`
- The uploads tree is walked exactly once for all beside-the-file patterns, bounded by an entry count and a wall-clock budget; when it stops early the card says "at least X" and explains that the real total is higher
- `wp lw-img leftovers` reports the same findings on the command line, with `--rescan` to walk the tree again and `--format=json|csv|yaml` for scripting (machine formats print no prose). `wp lw-img status` now reads the stored scan instead of forcing a fresh walk on every call
- Smush-optimized images are now recognized and skipped (postmeta `wp-smpro-smush-data`), and EWWW-optimized ones too — EWWW keeps no postmeta, so its own `{prefix}ewwwio_images` table is consulted (indexed lookup; the table-exists probe runs once per request, so sites without EWWW pay nothing)

## [1.6.1] - 2026-08-17

### Security
- Prevent cross-extension overwrite: an optimized target path is now de-duplicated with `wp_unique_filename()`, so converting `photo.jpg` can no longer overwrite (and orphan) an unrelated `photo.webp`
- Stop PHP object injection in the content URL rewrite: foreign serialized meta/option values are decoded with `allowed_classes=false` and rows containing any object are left untouched, so a serialized gadget is never instantiated
- Pin the slow-job poll URL to the API host over HTTPS and fetch it via `wp_safe_remote_get` with redirects disabled and a size cap (SSRF)
- Contain backup file paths: `BackupStore::resolve()` rejects directory traversal and off-root symlinks, making every backup file operation a no-op on hostile `_lw_img_backup_path` values
- Protect the backup folder with an `index.php` and `.htaccess` (no directory listing, no PHP execution; Apache-only, folder-name hardening planned), and create the options row with `autoload=false` so the API key is not held in memory every request

### Fixed
- "Log cleared" redirected to a non-matching tab anchor and rendered into a hidden panel; the success notice now appears (and "Settings saved." is shown via `settings_errors()` on this custom-menu page)
- Numeric settings are clamped server-side (e.g. request timeout 5–120) instead of trusting the field min/max; a non-array option write no longer fatals
- Bulk queue no longer builds `IN ()` when the mime list is empty; CPU detection no longer risks a `count(false)` TypeError; a malformed remote plugin registry no longer white-screens the LW Plugins page

### Accessibility
- Every settings field has an accessible name again (the redesign had replaced `<label for>` with plain spans) via `aria-label` on the API key, size, and retention fields
- Restored the keyboard focus ring on the settings tab rail with a contrast-safe outline

### Changed
- Refreshed README and repository description/topics for the LW Image name

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
- Redesigned Log tab: a control bar (logging toggle + clear), count-badged status filter chips with a filename search, feed-style rows (sizes and saving for conversions, red errors, skip reasons), client-side filtering and pagination without reloads, and an empty state
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
