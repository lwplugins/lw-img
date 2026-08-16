# LW Img

> ⚠️ **Under active development** — this plugin is not yet recommended for use on production sites.

Lightweight image optimization for WordPress — auto-convert uploads to WebP via the [HelloImg](https://helloimg.io) API.

## What it does

When a non-WebP image is uploaded to WordPress, LW Img sends it to the HelloImg API, gets back a WebP version, and replaces the original file **before** WordPress generates sub-sizes. One API call per upload — every thumbnail is built from the optimized WebP source.

- JPEG / PNG / HEIC / TIFF / BMP → WebP
- Already-WebP / AVIF → skipped
- Animated GIFs → skipped
- API failure → original kept (graceful fallback)
- Originals are backed up (`uploads/lw-img-backups/`, on by default) and restorable from the Media Library — thumbnails are regenerated on restore
- Backup retention: daily cleanup after the configured days (default 30, `0` = keep forever)

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

## License

GPL-2.0-or-later
