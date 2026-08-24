# Products page (post ID 61) hero banner — CSS snippet backups

Snapshots of the full content of the WPCode CSS snippet (custom post type
`wpcode`, post ID 483) that styles the Products page (`/products/`,
post ID 61), taken immediately before and after each change to the
`.page-hero` banner rule.

Convention matches `backups/page57-home-widget/README.md`: capture the
live `post_content` first (read-only) and commit it here as
`YYYY-MM-DD_before-<change-name>.css` *before* running the write. After a
successful, verified write, also commit the resulting content as
`YYYY-MM-DD_after-<change-name>.css`.

## Restoring from a backup

1. Copy the chosen `.css` file's content byte-for-byte.
2. Use it as the `post_content` payload in a guarded STEP A/B/C/D fix
   script, following the pattern in
   `assets/luna-import/fix-products-hero-banner.php` (byte-length +
   sha256 staleness gate, race-condition guard, full read-back
   verification, WPCode snippet-cache rebuild) — do not write it directly
   without those guards.

## Current snapshots

| File | sha256 | Bytes | Notes |
|---|---|---|---|
| `2026-08-24_before-hero-banner-swap.css` | `e76a109ed726a843f56fc91614f0ee42a95761cdab70d878fa075b435c98ebed` | 12562 | Live content immediately before swapping the `.page-hero` banner image and adding the Ken-Burns zoom effect. |
| `2026-08-24_after-hero-banner-swap.css` | `68bb925ee15d5d87f60895d8dd42f373f547197e18859eab8ba37d878f85ee09` | 12882 | Live content after the swap, confirmed via the fix script's own STEP C read-back verification. `.page-hero` now uses a `::before` pseudo-element (background image + gradient overlay) animated with `@keyframes lpHeroKB`, matching the Home page hero's Ken-Burns effect. Only the banner image and its effect changed — no text or other CSS rules were touched. |
