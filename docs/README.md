# LW Image documentation

LW Image converts WordPress uploads to WebP (or AVIF) through the
[HelloImg](https://helloimg.io) API — one API call per image, with the
original backed up and every reference rewritten. No bloat, no upsell,
no tracking.

| Guide | What it covers |
|---|---|
| [Usage](usage.md) | How the plugin works day to day: the upload pipeline, bulk optimization, backups and restore, smart crop, and the hooks integrators can use |
| [Admin](admin.md) | Every tab of the settings screen and the Media Library integrations |
| [WP-CLI](cli.md) | All `wp lw-img` commands with options and examples |

## The short version

1. Install (`composer require lwplugins/lw-img` or the release ZIP) and
   activate.
2. Paste your HelloImg API key on **LW Plugins → Image → General**. Keys
   come from [app.helloimg.io](https://app.helloimg.io/).
3. New uploads convert automatically. For the existing Media Library,
   run a bulk optimize from the **Bulk** tab or `wp lw-img optimize --all`.

Requirements: WordPress 6.0+, PHP 8.0+, and an image editor (Imagick or
GD) with WebP support — the **Tester** tab checks all of this for you.
