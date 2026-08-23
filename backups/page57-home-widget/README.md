# Page 57 (Home) HTML widget — backups

Snapshots of the full content of the single Elementor HTML Widget
(container `id=bf4109a` / widget `id=9b0a463`) on Page ID 57 (Home),
taken immediately before and after each change to that widget.

Convention: every time this widget's content is about to be changed,
capture the live `settings.html` value first (read-only, via a
`diagnose-*`/`query-*` workflow) and commit it here as
`YYYY-MM-DD_before-<change-name>.html` *before* running the write.
After a successful, verified write, also commit the resulting content
as `YYYY-MM-DD_after-<change-name>.html`.

## Restoring from a backup

1. Copy the chosen `.html` file's content byte-for-byte (do not
   re-encode/re-escape it — it is the raw HTML string, already
   unescaped).
2. Use it as the `new_content` payload in a guarded STEP A/B/C fix
   script, following the pattern in
   `assets/luna-import/fix-page57-origin-section.php` (byte-length
   staleness gate, race-condition guard, full before/after structural
   verification) — do not write it directly without those guards.

## Current snapshots

| File | sha256 | Bytes | Notes |
|---|---|---|---|
| `2026-08-23_before-luna-origin.html` | `07c28625312799ec650b63ee3298b806e2d80697d5812f6698f53cac0fdfb84c` | 24734 | Live content immediately before PR #161 (Luna image src swap + "Our Origin" section). |
| `2026-08-23_after-luna-origin.html` | `3a9873fba5e4955eabdc8461ae80aba3a3ba5d140427687cedd7956051cadfbe` | 26920 | Live content immediately after PR #161, confirmed via the fix workflow's own STEP C read-back verification (`content_matches: PASS`). |
