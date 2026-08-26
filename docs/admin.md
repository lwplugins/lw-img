# Admin screen

Everything lives on a single tabbed page: **LW Plugins → Image**. All
tabs share one settings form — the *Save Changes* button at the bottom
saves every tab at once, and any save clears the Tester's cached report.

## General

The connection hero shows whether the API key works, with the key field,
a *Save key* button, a show/hide toggle, and *Test connection*. (Testing
with an edited key saves it first, so a rotated key is never lost.)

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
| Exclusion patterns | — | Wildcard patterns on file name or path; matching files are never sent to the API |
| MIME types | JPEG, PNG, HEIC/HEIF, TIFF, BMP, GIF | Which types enter the pipeline |
| Smart crop | off | Enable + pick the hard-cropped sizes; the per-upload API cost is shown before you commit |

## Bulk

A live dashboard for background runs: segmented progress bar, elapsed /
speed / ETA, and an activity feed. Controls: start (disabled until a
working API key is set), cancel, *Retry N failed*, *Re-scan N skipped*
(after settings changes), and the speed profile (gentle / normal /
fast).

## Backup

The backup lifecycle at a glance: whether backups are on, storage tiles,
retention presets (default 30 days, `0` = keep forever), and how to
restore. Backups live in `wp-content/uploads/lw-img-backups/` and are
never deleted on uninstall.

## Tester

Environment checks with a verdict hero and a needs-attention list with
copyable fix commands: database table engines (MyISAM warnings), WebP /
AVIF thumbnail support, cron loopback, disk space, and API
reachability. Results are cached for ten minutes; the *Run tests again*
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
