# Admin screen

Everything lives on a single tabbed page: **LW Plugins → Image**. All
tabs share one settings form — the *Save Changes* button at the bottom
saves every tab at once, and any save clears the Tester's cached report.

## General

The connection hero shows whether the API key works, with the key field,
a *Save key* button, a show/hide toggle, and *Test connection*. (Testing
with an edited key saves it first, so a rotated key is never lost.)

The key can also come from wp-config.php (`LW_IMG_API_KEY`) — the field
then shows that instead of an editable value. Clicking *Test connection*
reports the result explicitly (plan name on success, the exact error on
failure).

With a working key, the **Account** tiles show live data from the API:

- **Plan** — your plan name and monthly limit (or "no monthly limit")
- **This month** — images optimized and bytes saved via the API for the
  key's domain this month (with a usage bar on limited plans)
- **Optimized on this site** — this install's own totals (the API counts
  the whole key, so the two numbers legitimately differ)

Without a key, the tab shows onboarding steps instead. Below the tiles,
chips summarize the current defaults and jump to the tab that owns them.

## Stats

Total savings with a before/after bar, the biggest wins, the backup
folder's size, and **Leftovers from other optimizers** — backup folders
and sidecar originals that ShortPixel-style plugins left behind. LW Image
only measures these; it never deletes them.

## Upload

The auto-conversion pipeline, grouped by question:

| Setting | Default | Notes |
|---|---|---|
| Auto-convert uploads | on | The master toggle |
| Output format | `webp` | or `avif` |
| Optimization level | `normal` | `lossless` / `normal` / `aggressive` / `ultra` |
| Keep EXIF | off | Off drops camera/GPS metadata |
| Max width / height | 0 / 0 | Resize on upload; `0` = no limit, never upscales |
| Skip already-WebP | on | Saves credits |
| Skip animated GIF | off | Off converts to animated WebP |
| Max / min file size | 10 MB / 0 KB | Files outside the range are skipped |
| Pattern rules | — | Wildcard pattern + action: skip entirely, keep original dimensions, use a specific level, keep EXIF. Every matching rule applies; skip wins; first level rule wins |
| MIME types | JPEG, PNG, HEIC/HEIF, TIFF, BMP, GIF | Which types enter the pipeline |
| Smart crop | off | Enable + pick the hard-cropped sizes; the per-upload API cost is shown before you commit |

The default level is **normal** — on photos it is visually transparent
(measured ~41 dB PSNR on a real photo, no difference at 100% view)
while cutting sizes roughly in half. **Lossless** (WebP's VP8L mode) is
there for pixel-perfect needs: it shrinks PNGs and graphics
substantially, but a losslessly re-encoded JPEG photo usually comes
back *larger*, so the size guard keeps the original and the image is
skipped.

## Bulk

A live dashboard for background runs: segmented progress bar, elapsed /
speed / ETA, and an activity feed. Controls: start (disabled until a
working API key is set), cancel, *Retry N failed*, *Re-scan N skipped*
(after settings changes), and the speed profile (gentle / normal /
fast). The *This run uses* card shows the Upload-tab settings the next
run will apply (level, output, resize, pattern rules, EXIF, backup).
The Pending / Optimized / Skipped / Failed tiles link to the Media
Library, pre-filtered to that status via the **LW Image** status
dropdown.

## Backup

The backup lifecycle at a glance: whether backups are on, storage tiles,
retention presets (default 30 days, `0` = keep forever), and how to
restore. Backups live in `wp-content/uploads/lw-img-backups/` and are
never deleted on uninstall.

## Tester

Environment checks with a verdict hero and a needs-attention list with
copyable fix commands: database table engines (MyISAM warnings), WebP /
AVIF thumbnail support, cron loopback, old-image redirects (whether
missing-image requests reach WordPress — with the nginx fix when they
do not), disk space, and API reachability. Results are cached for ten minutes; the *Run tests again*
button and any settings save refresh them.

## Log

The last 200 pipeline events (converted / skipped / failed with reasons
and savings), with filter chips, a search box, and client-side paging.

## Media Library integrations

- An **LW Img** column with the per-image savings
- Row actions: **Optimize now** and **Restore original**
- An attachment info box, a Compare view, and re-optimize at a different
  level
- Images already optimized by another optimizer plugin are recognized
  and left untouched
- In list view, an **LW Image** status dropdown filters attachments by
  optimized / skipped / failed / not yet processed
