# Incident: Spanish front page showed the English hero (2026-10-05)

## What happened
- **17:56:10 UTC:** the first Spanish About apply (run 37352091965) changed the slug of post 680 with `wp_update_post()`. That fires WordPress and WPML save hooks. WPML then rebuilt post 772, the Spanish front page, from the English original and created revision 839. The Spanish front page copy is not stored in WPML string translations; it lived only in post 772's own Elementor data.
- The rollback of that run restored only the posts the script itself wrote (680, 770, 771), so post 772 was not restored.
- `/es/` kept rendering Spanish from Elementor's element cache until a later cache clear. From then on, it showed the English hero.
- It was found at about 18:29 while preparing the tagline change, by comparing a live snapshot with one from about 17:50.

## Recovery
- **Diagnostic:** post 772 was the only post affected; the post list since 17:50 shows only 680/838 and 772/839.
- **Restore (run 37356876347):**
  - Source: post 772's `_elementor_data`, post content and modified date from the full database backup taken at 17:55:58 UTC, two seconds before the incident. MySQL itself loaded those rows.
  - Before writing, a fresh full backup was taken and the post-incident values were saved to JSON.
  - The restored widget is byte-identical to the backup. The live check confirmed the Spanish hero, the Spanish menu, and the English home unchanged.

## Prevention
- Every script since that run writes `_elementor_data`, slugs and snippet code directly to the database, with no `wp_update_post()` or `update_post_meta()`, and verifies each write byte for byte.

## Permanent fix (2026-10-06, run 37440020554, verified by run 37440524590)
1. **Root cause removed (WPML string translations).**
   - For the four English/Spanish Elementor pairs, the WPML page-builder string now holds the live English widget as its original. The live Spanish widget is stored as its complete Spanish translation (status 10).
   - The pairs are Home 57 → `/es/` 772, About Us 59 → `/es/sobre-nosotros/` 680, Contact 60 → `/es/contacto/` 770, and Product 61 → `/es/productos/` 771 (strings 125, 126, 136, 137).
   - WPML's own package objects, the ones its page-builder integration uses when it rebuilds a translation, now return the Spanish value for all four strings. Each value is byte-identical to the live Spanish page.
   - Before the fix, none of the four strings had a Spanish translation, so any WPML rebuild produced English.
2. **Safety net (`lunaci-seo.php` item 9, option `lunaci_es_guard`).**
   - Any write of mostly-English `_elementor_data` to those four Spanish pages is refused, whatever the source, and logged in option `lunaci_es_guard_log`.
   - The guard runs last on `update_post_metadata` / `add_post_metadata`. Spanish edits, including saves from the Elementor editor, go through.
   - **Controlled test:** `update_post_meta()` of the English About data onto post 680 was refused, and the page stayed byte-identical. One log entry from that test remains, dated 2026-10-06 09:01 UTC.

## Maintenance
- After editing a Spanish page directly in Elementor, re-run `protect-es-pages` in apply mode. That re-registers the new Spanish text, so a later WPML rebuild uses the latest version and not an older Spanish one. The guard already blocks English in every case.
- If a new English/Spanish Elementor page pair is added, add it to `$pairs` in `assets/luna-import/wpml-es-protect.php` and to `lunaci_seo_es_guard_posts()`.
- To undo the fix, run `protect-es-pages` in rollback mode. It restores the WPML strings and turns the guard off.
