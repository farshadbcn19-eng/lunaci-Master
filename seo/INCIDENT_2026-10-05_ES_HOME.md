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
- **Remaining risk (outside the scripts):** if the English front page is saved in Elementor, or its translation is refreshed in WPML, WPML can rebuild the Spanish front page from English in the same way. This happens because the Spanish strings are not registered as WPML string translations. Until that is fixed, edit the Spanish front page directly, and check `/es/` after any edit to the English front page.
