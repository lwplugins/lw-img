# LW Image

**Note:** LW Image is under active development and not yet recommended for use on production sites.

Lightweight image optimization for WordPress — auto-convert uploads to WebP via the [HelloImg](https://helloimg.io) API. No bloat, no upsell, no tracking.

## What it does

When a non-WebP image is uploaded to WordPress, LW Image sends it to the HelloImg API, gets back a WebP version, and replaces the original file **before** WordPress generates sub-sizes. One API call per upload — every thumbnail is built from the optimized WebP source.

- JPEG / PNG / GIF / HEIC / TIFF / BMP → WebP or AVIF (output-format setting)
- Already-WebP / AVIF uploads are skipped (no API call, no credit used)
- Animated GIFs → animated WebP, frames and timing preserved (optional skip)
- Optional resize on upload (max width/height, never upscales)
- Size guard: the original is kept when the converted file would not be smaller
- API failure → original kept (nothing ever fails because of LW Image)
- Original backups (`uploads/lw-img-backups/`, on by default), restorable from the Media Library — thumbnails are regenerated on restore
- Backup retention: daily cleanup after the configured days (default 30, `0` = keep forever)

## Bulk & scale

- Bulk-optimize the existing Media Library in the background (WP-Cron worker, resumable runs)
- Built for large libraries: parallel-safe queue claiming means several `wp lw-img optimize --all` workers can drain the queue at once
- Processing-speed profiles (gentle / normal / fast) with a CPU load guard, so a bulk run never starves the site
- Content URL rewrite on bulk convert/restore — post content and page-builder data, serialization-aware — plus a 301 redirect from old image URLs
- WP-CLI: `wp lw-img status` / `optimize` / `restore` / `requeue`

## Admin

A single tabbed screen under **LW Plugins → Image**:

- **General** — connection status, account, and current defaults at a glance
- **Stats** — total savings, biggest wins, backup folder size, and leftover backup folders from other optimizers
- **Upload** — auto-conversion settings grouped by question
- **Bulk** — a live progress dashboard (segmented bar, speed, ETA, activity feed)
- **Backup** — lifecycle, storage, retention presets, and how to restore
- **Tester** — environment checks (database engines, WebP support, cron loopback, disk space, API reachability) so hosting problems surface before a bulk run trips over them
- **Log** — filterable event feed of the last 200 upload events

Other integrations: Media Library savings column, "Optimize now" / "Restore original" row actions, an attachment info box, a Compare view, and re-optimize at another level. Images already optimized by ShortPixel, TinyPNG, or Imagify are recognized and left untouched.

## Install

```bash
composer require lwplugins/lw-img
```

Or download the latest release ZIP from the [Releases](https://github.com/lwplugins/lw-img/releases) page.

After activation: **LW Plugins → Image** → paste your HelloImg API key. HelloImg is in open beta — any key value enables conversion for now.

## Configuration

| Setting | Default | Notes |
|---|---|---|
| Output format | `webp` | `webp` (widest support) or `avif` |
| Optimization level | `normal` | `lossless` / `normal` / `aggressive` / `ultra` |
| Keep EXIF | off | Smaller files, and drops camera/GPS metadata |
| Skip already-WebP | on | Saves credits |
| Skip animated GIF | off | Converted to animated WebP by default |
| Backup originals | on | Restorable from the Media Library |
| Backup retention | 30 days | `0` = keep forever |
| Bulk speed | `normal` | `gentle` / `normal` / `fast` |

## Requirements

- WordPress 6.0+
- PHP 8.0+
- An image editor (Imagick or GD) with WebP support — the Tester tab checks this for you

## Notes on backups

Original backups live in `wp-content/uploads/lw-img-backups/`. The plugin drops an `index.php` and an `.htaccess` there to prevent directory listing and PHP execution. On **Apache** this also blocks direct access; on **nginx** the `.htaccess` has no effect, so a determined visitor could still reach a backup file by its URL — a guessable-name hardening is planned. Backups are never deleted on uninstall.

## Sponsor

Development is sponsored by [GoBird](https://gobird.io):

<a href="https://gobird.io"><img src=".github/gobird.svg" alt="GoBird" height="32"></a>

## License

GPL-2.0-or-later
