# WP-CLI

All commands live under `wp lw-img`. They use the same pipeline, gates,
and logging as the admin — anything the CLI does shows up in the Log tab
and the Bulk dashboard, live.

## `wp lw-img status`

Queue and savings overview: pending / optimized / skipped / failed
counts, total savings, and the connection state.

## `wp lw-img optimize`

```bash
wp lw-img optimize 123 456                  # specific attachments
wp lw-img optimize --all                    # everything pending
wp lw-img optimize --all --limit=500        # cap this invocation
wp lw-img optimize --all --speed=fast       # gentle | normal | fast
wp lw-img optimize --all --dry-run          # show what would happen
```

`--all` claims work from the shared queue with concurrency-safe
claiming — run several workers in parallel and the Bulk tab follows
along. The run refuses to process without a working API key, and halts
(leaving images pending) if the key is rejected or the account runs out
of credit mid-run. It also refuses when the web server swallows uploads
404s (old URLs of converted images would 404 instead of redirecting —
the Tester tab has the nginx fix); pass `--skip-redirect-check` to run
anyway.

## `wp lw-img restore`

```bash
wp lw-img restore 123 456
```

Puts the backed-up originals back, regenerates thumbnails, and reverts
URL rewrites.

## `wp lw-img requeue`

```bash
wp lw-img requeue --failed              # retry failures
wp lw-img requeue --failed --skipped    # also re-evaluate skips (after settings changes)
```

Clears the outcome stamps so the next `optimize` run picks the images up
again.

## `wp lw-img leftovers`

```bash
wp lw-img leftovers                     # stored scan results
wp lw-img leftovers --rescan            # walk the uploads tree again
wp lw-img leftovers --format=json       # table | csv | json | yaml | count
```

Measures backup folders and sidecar originals left behind by other
optimizer plugins (dedicated backup dirs and beside-the-image patterns
alike). Read-only — LW Image never deletes them.

## `wp lw-img doctor`

```bash
wp lw-img doctor                 # every Tester check, fresh, as a table
wp lw-img doctor --format=json   # table | csv | json | yaml | count
```

The Tester tab's environment checks from the terminal — database
engines, image-editor support, cron loopback, old-image redirects (with
the nginx fix printed when it fails), disk space, API reachability.
Exits non-zero when any check is critical, so it can sit in monitoring.

## `wp lw-img smartcrop`

```bash
wp lw-img smartcrop 123 456                          # re-crop specific images
wp lw-img smartcrop --all --yes                      # whole library, no prompt
wp lw-img smartcrop --all --sizes=thumbnail --dry-run  # preview the API cost first
```

Re-crops the selected hard-cropped thumbnail sizes around the subject.
Works independently of the upload-time smart-crop toggle, so it can
retrofit an existing library — and it is the remedy when a WordPress 7.1
browser upload never sent its finalize request and a scheduled crop was
left behind. Square images are skipped by design (nothing to crop).
