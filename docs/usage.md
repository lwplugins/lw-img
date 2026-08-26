# Usage

## The upload pipeline

When a non-WebP image is uploaded, LW Image intercepts it **before**
WordPress generates sub-sizes: the file goes to the HelloImg API once,
the optimized WebP (or AVIF) replaces the original, and every thumbnail
is then built from the optimized file. One upload = one API call.

What never converts:

- Already-WebP/AVIF uploads (with *Skip already-WebP* on — saves credits)
- Files matching your exclusion patterns, or outside the size limits
- Anything when no API key is configured — the plugin then changes
  nothing at all
- Animated GIFs when *Skip animated GIF* is on (otherwise they become
  animated WebP with frames and timing preserved)

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
Start action live-checks it first.

Already-optimized images are never touched again: changing the output
format later does not retroactively re-convert anything. Re-processing
is always explicit (row action, requeue, or CLI).

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

## Hooks

Filters:

| Hook | Purpose |
|---|---|
| `lw_img_should_convert` | Final veto on converting a file: `(bool $convert, string $file_path, string $mime_type)` |
| `lw_img_dashboard_url` | Replace the HelloImg dashboard URL shown in the admin (white-labeling) |
| `lw_img_optimize_request_args` | Filter the API request payload before it is sent: `(array $args, string $file_path)` |
| `lw_img_competitor_plugins` | Extend the list of recognized other-optimizer plugins |

Actions (fired by the plugin, useful for logging/monitoring):

| Hook | Fires when |
|---|---|
| `lw_img_upload_skipped` | A file was deliberately not converted: `(string $file, string $reason)` |
| `lw_img_upload_failed` | A conversion or crop attempt failed: `(string $file, string $reason)` |
| `lw_img_restored` | An attachment was restored from backup: `(int $attachment_id, string $file)` |
