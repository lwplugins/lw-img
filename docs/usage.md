# Usage

## The upload pipeline

When a non-WebP image is uploaded, LW Image intercepts it **before**
WordPress generates sub-sizes: the file goes to the HelloImg API once,
the optimized WebP (or AVIF) replaces the original, and every thumbnail
is then built from the optimized file. One upload = one API call.

What never converts:

- Already-WebP/AVIF uploads (with *Skip already-WebP* on — saves credits)
- Files matching a "skip entirely" pattern rule, or outside the size limits
- Anything when no API key is configured — the plugin then changes
  nothing at all
- Animated GIFs when *Skip animated GIF* is on (otherwise they become
  animated WebP with frames and timing preserved)

Pattern rules apply to uploads and bulk runs alike (a skip rule also
keeps the file out of smart crop) — the Bulk tab shows which Upload
settings a run will use. Thumbnails are regenerated from the
converted file after every conversion.

If the API call fails, the original upload is kept untouched. Nothing
ever breaks because of LW Image.

### WordPress 7.1 browser uploads

On WordPress 7.1 over HTTPS, supported browsers generate thumbnails
themselves and sideload them one by one. LW Image recognizes this flow:
the main file still converts once, the browser is told (via the
`image_editor_output_format` map, active only on 7.1 with a configured
key) to produce thumbnails in your output format locally, and the
per-thumbnail sideloads are never sent to the API. Still one API call
per upload.

## Bulk optimizing the existing library

The **Bulk** tab (or `wp lw-img optimize --all`) walks every
unoptimized image in the background: WP-Cron worker, resumable,
parallel-safe (several CLI workers can drain the same queue), with
speed profiles (gentle / normal / fast) and a CPU load guard.

References to converted files — post content, page-builder data, options,
serialized meta — are rewritten automatically, and a 301 redirect covers
the old image URLs.

A run **halts immediately** (images stay pending, nothing is stamped)
when the API reports the account out of credit, or when the API key is
removed or rejected mid-run. Starting a run requires a working key — the
Start action live-checks it first — and requires working old-URL
redirects: on servers that answer missing image files themselves (nginx
without an index.php fallback for uploads), the 301 safety net for
converted images' old URLs can never run, so the bulk run refuses to
start. The Tester tab detects this and shows the copyable nginx fix —
see [Web server configuration for old-URL
redirects](#web-server-configuration-for-old-url-redirects) for the
per-server details; new uploads are unaffected either way.

Already-optimized images are never touched again: changing the output
format later does not retroactively re-convert anything. Re-processing
is always explicit (row action, requeue, or CLI).

## Web server configuration for old-URL redirects

The 301 safety net only works when a request for a **missing** image
file reaches WordPress. Whether it does depends on the web server — the
Tester tab's *Old-image redirects* check tells you where you stand, and
bulk optimize refuses to start until it passes.

### Apache

Nothing to add. The standard WordPress `.htaccess` already sends every
request for a non-existent file to `index.php`:

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
```

If the check still fails on Apache, that block has been removed or
overridden (custom rules that answer image extensions directly, or
`AllowOverride None` without an equivalent vhost config) — restore the
standard block.

### nginx

nginx answers static-file misses itself by default, so WordPress never
sees them. Add this to the site's server block:

```nginx
location ~* ^/wp-content/uploads/.*\.(png|jpe?g|gif|bmp|tiff?)$ {
    try_files $uri /index.php?$args;
}
```

Existing files are still served directly by nginx; only misses fall
through. Two placement notes: nginx uses the **first matching regex
location**, so this block must appear *before* any generic static-asset
location (`location ~* \.(jpg|png|css|js)$ { expires max; }` and the
like), and if that generic block sets cache headers you want to keep,
copy them into this one. Reload nginx afterwards.

### LiteSpeed

LiteSpeed Enterprise reads the standard WordPress `.htaccess`, so as on
Apache there is nothing to add. On **OpenLiteSpeed**, make sure rewrite
rules and ".htaccess auto load" are enabled for the virtual host
(Rewrite → Enable Rewrite + Auto Load from .htaccess), then restart —
without those, OpenLiteSpeed behaves like unconfigured nginx and
swallows the misses.

### CDN caveat

A CDN in front of the site (Cloudflare and similar) may have **cached
the 404s** from before the fix. After the server change, purge the
cache for the affected URLs (or `/wp-content/uploads/*`), or the old
responses keep being served until they expire.

## Backups and restore

Originals are backed up to `wp-content/uploads/lw-img-backups/` (on by
default) and kept for the configured retention (default 30 days, `0` =
forever). Restore from the Media Library row action or `wp lw-img
restore <id>` — the original comes back, thumbnails are regenerated, and
URL rewrites are reverted.

## Smart crop (opt-in)

For hard-cropped thumbnail sizes you select, LW Image can re-crop around
the subject instead of the centre, via the API (one call per size). It
applies to new uploads while enabled; for existing images use
`wp lw-img smartcrop`. Restore reverts to stock WordPress thumbnails.

Two things that are by design, not bugs:

- A square image gets no crop jobs — its aspect ratio already matches
  square sizes, so there is nothing to crop away.
- Bulk optimize and thumbnail regenerators never smart-crop; only real
  uploads (and the CLI command) do.

## API key in wp-config.php

Instead of the settings field, the key can be defined in code:

```php
define( 'LW_IMG_API_KEY', 'himg_...' );
```

The constant wins over the stored option and is never written to the
database — useful for staging/production configs and for keeping the
secret out of DB dumps. The settings field then shows where the key
comes from instead of an editable value.

## Hooks

Filters:

| Hook | Purpose |
|---|---|
| `lw_img_should_convert` | Final veto on converting a file: `(bool $convert, string $file_path, string $mime_type)` |
| `lw_img_dashboard_url` | Replace the HelloImg dashboard URL shown in the admin (white-labeling) |
| `lw_img_optimize_request_args` | Filter the API request payload before it is sent: `(array $args, string $file_path)` — runs for uploads and for bulk / on-demand conversions |
| `lw_img_competitor_plugins` | Extend the list of recognized other-optimizer plugins |

Actions (fired by the plugin, useful for logging/monitoring):

| Hook | Fires when |
|---|---|
| `lw_img_upload_skipped` | A file was deliberately not converted: `(string $file, string $reason, array $context)` — `$context` is always passed (may be an empty array); may hold `attachment_id`, `original_size`, `new_size` |
| `lw_img_upload_failed` | A conversion or crop attempt failed: `(string $file, string $reason, array $context)` — `$context` is always passed (may be an empty array); may hold `attachment_id` |
| `lw_img_restored` | An attachment was restored from backup: `(int $attachment_id, string $file)` |
