# LW Img

> ⚠️ **Under active development** — this plugin is not yet recommended for use on production sites.

Lightweight image optimization for WordPress — auto-convert uploads to WebP via the [HelloImg](https://helloimg.io) API.

## What it does

When a non-WebP image is uploaded to WordPress, LW Img sends it to the HelloImg API, gets back a WebP version, and replaces the original file **before** WordPress generates sub-sizes. One API call per upload — every thumbnail is built from the optimized WebP source.

- JPEG / PNG / GIF / HEIC / TIFF / BMP → WebP or AVIF (output format setting)
- Already-WebP / AVIF → skipped
- Animated GIFs → animated WebP, frames and timing preserved (optional skip)
- Optional resize (max width/height, never upscales)
- Size guard: original kept when the converted file would not be smaller
- API failure → original kept (graceful fallback)
- Originals are backed up (`uploads/lw-img-backups/`, on by default) and restorable from the Media Library — thumbnails are regenerated on restore
- Backup retention: daily cleanup after the configured days (default 30, `0` = keep forever)
- Bulk optimize the existing Media Library in the background (WP-Cron worker, resumable runs; `wp lw-img optimize --all` for huge libraries)
- Content URL rewrite on bulk convert/restore — post content + page-builder data (serialization-aware)
- Media Library savings column, "Optimize now" / "Restore original" row actions, attachment info box, Compare, Re-optimize at another level
- Wildcard exclusion patterns (`*-original.jpg`, `2026/08/*`) + min/max file size limits
- WP-CLI: `wp lw-img status` / `optimize` / `restore` / `requeue`

## Install

```bash
composer require lwplugins/lw-img
```

Or download the latest release ZIP from GitHub.

After activation: **LW Plugins → Img** → paste your HelloImg API key.

## Configuration

| Setting | Default | Notes |
|---|---|---|
| Optimization level | `normal` | `normal` / `aggressive` / `ultra` |
| Keep EXIF | off | Smaller files, plus privacy |
| Skip already-WebP | on | Saves credits |
| Skip animated GIF | on | Animation would be lost |

## Sponsor

Development is sponsored by [GoBird](https://gobird.io):

<a href="https://gobird.io"><img src=".github/gobird.svg" alt="GoBird" height="32"></a>

## License

GPL-2.0-or-later
