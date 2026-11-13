# Tasks: Consistent single-product pages

Plan: [`tasks/plan.md`](plan.md). Spec: [`SPEC.md`](../SPEC.md).
Every task also meets [the Definition of Done](../.claude/references/definition-of-done.md).

## Task 1: Baseline capture and check script

**Description:** A Playwright script that, for the 13 EN + 13 ES product URLs (taken from the WooCommerce Store API, not hardcoded), records HTTP status, the visible copy (title, price, short description, tabs), computed tokens (background, text colour, fonts) and desktop and 390 px screenshots. It also reads the design tokens from the live /products/ page. Run it once now to save the **baseline** that later runs are compared against.

**Acceptance criteria:**
- [x] `--baseline` writes `output/product-pages/baseline.json` and screenshots (`output/` is already gitignored)
- [x] `--mode=test|on` compares against the baseline: copy diff, token match with /products/, no `#D4AF37` background on any element, no horizontal scroll at 390 px
- [x] `--cart` adds Lipstick (shade product) and Eye Pencil (no shades) to the cart in EN and ES and checks the cart count

**Verification:**
- [x] Baseline run: 26 × HTTP 200, 26 copy records, /products/ tokens captured
- [x] Comparing the baseline against itself reports 0 differences
- [x] Manual check: open 2 screenshots and confirm they show the real page

**Notes (done 2026-11-13):**
- Above-the-fold shots (`*-fold.png`) were added: a full-page capture can blank the mobile product photo, even though it shows on the live site (checked).
- The cart check uses explicit ES slugs (`pintalabios`, `lapiz-de-ojos-lunaci`), because the Store API lists the languages in different orders.
- Baseline finding: every product page has background `#0D0D0D`, /products/ has `#0B0B0B` (that is T4's job). Product body text is gold, 20 px, family name `Raleway` (aliased to Helvetica LUNACI).

**Dependencies:** None
**Files likely touched:** `scripts/check-product-pages.mjs`
**Estimated scope:** S

## Task 2: mu-plugin skeleton with mode switch and marker

**Description:** `mu-plugins/lunaci-product-page.php` prints `<style id="lunaci-product-page">` on `is_product()` pages when `lunaci_perf_mode_active('lunaci_product_page_mode')` is true. A header comment states the purpose, the modes and the rollback. It holds one harmless placeholder rule, so the deploy can be proven before any visible change.

**Acceptance criteria:**
- [x] Nothing is printed when the mode is off, on non-product pages, in admin, REST or Elementor preview (handled by the reused helper)
- [x] If `lunaci_perf_mode_active` doesn't exist, the plugin does nothing
- [x] No HTML or copy changes

**Verification:**
- [x] `php -l mu-plugins/lunaci-product-page.php` passes
- [x] Small self-check: a PHP snippet with WordPress functions stubbed covers on, off, non-product page and missing helper

**Notes (done 2026-11-13):**
- Prints at `wp_head` priority 1001, after `lunaci-perf.php` items 6 and 7 (999 and 1000).
- Self-check: `php scripts/test-product-page-plugin.php` covers 6 cases. A mutation test showed that without the `function_exists` guard, a missing `lunaci-perf.php` would cause a site-wide fatal error.

**Dependencies:** None
**Files likely touched:** `mu-plugins/lunaci-product-page.php`
**Estimated scope:** XS

## Task 3: Deploy workflow with backup and live check

**Description:** `.github/workflows/deploy-product-page.yml`, cloned from `deploy-wc-style.yml`: full backup (database, mu-plugins, .htaccess, current mode values), deploy the file, set `lunaci_product_page_mode`, purge LiteSpeed, then a live check for the `lunaci-product-page` marker.

**Acceptance criteria:**
- [x] Inputs `test` / `on` / `off`; same `concurrency` group as the other Hostinger deploys
- [x] The run stops if the backup fails or is incomplete
- [x] Live check covers 2 EN + 2 ES product pages, `/products/`, `/cart/` and `/`: marker only on product pages; in `test` mode absent for visitors

**Verification:**
- [x] The YAML parses (`python3 -c "import yaml…"`); the diff against `deploy-wc-style.yml` shows only the intended changes
- [x] **Checkpoint A run (with your OK):** workflow `test` → "ALL CHECKS PASSED"; T1 script reports 0 differences

**Notes (2026-11-13):**
- Generated from `deploy-wc-style.yml`. The backup also records `lunaci_wc_gold_mode` and `lunaci_product_page_mode`, and stops if `lunaci-perf.php` (the mode helper) is missing on the server.
- Dry run of the live check against the live site (nothing deployed): `off` = ALL PASSED; `on` and `test` = FAIL on the 4 product pages (marker missing). So the check detects both states.
- `workflow_dispatch` only runs once the file is on `main`. Merging deploys nothing: no workflow on `main` triggers on push.

**Dependencies:** T2
**Files likely touched:** `.github/workflows/deploy-product-page.yml`
**Estimated scope:** S

## Checkpoint A: pipeline proven
- [x] Test-mode deploy green; visitors unchanged; 0 differences from the baseline (2026-11-13: PR #562 merged; [run #1](https://github.com/farshadbcn19-eng/lunaci-Master/actions/runs/43461946203) 9/9 live checks; check script: block present on 26/26 pages, 0 copy differences, add to cart 4/4; the only failures are the expected background difference that T4 fixes)
- [ ] Review with you before any styling

## Task 4: Layout and typography

**Description:** CSS for the top of the page: gallery and summary grid, product title, price, short description, breadcrumb. Values taken from the /products/ tokens captured in T1.

**Acceptance criteria:**
- [x] Fonts, colours and spacing match /products/ (script token check)
- [x] Gold used only for text and rules, never as a fill
- [x] Copy diff 0 on all 26 URLs

**Verification:**
- [x] Test-mode deploy + `node scripts/check-product-pages.mjs --mode=test`
- [x] Manual check: desktop screenshot of Lipstick in EN and ES next to /products/

**Done (2026-11-13):** PR #563, [run #2](https://github.com/farshadbcn19-eng/lunaci-Master/actions/runs/43465455748) in `test` mode. Check script: 26/26 pages, background = /products/, 0 copy differences, 0 failed checks, add to cart 4/4. Visitors unchanged.
- Previewed by injecting the CSS into the live EN and ES pages (desktop + 390 px): every target value applies.
- The store WPCode snippet sets an older palette through the `--lk` (#0D0D0D), `--lc` (#F5F0E8) and `--lg` (#C4A35A) variables. On product pages they are pointed at the brand values instead of overriding each rule. `!important` is used only against that snippet's own `!important` rules on the breadcrumb and title.
- Existing bug fixed: up to 768 px the header covered the top 9 px of the breadcrumb.

**Dependencies:** Checkpoint A
**Files likely touched:** `mu-plugins/lunaci-product-page.php`
**Estimated scope:** S

## Task 5: Purchase controls

**Description:** CSS for the shade dropdown and its label, the "Clear" link, quantity box, Add to Cart (transparent with a gold border, as in `lunaci-perf.php` item 7), the in-stock message and the selected-variation price.

**Acceptance criteria:**
- [x] `--cart` passes for Lipstick and Eye Pencil, in EN and ES
- [x] Add to Cart is visible without scrolling sideways at 390 px; touch targets are at least 44 px
- [x] Visible keyboard focus on the dropdown, quantity and button

**Verification:**
- [x] Test-mode deploy + `--mode=test --cart`
- [x] Manual check: keyboard-only walkthrough on one product

**Done (2026-11-13):** PR #564, [run #3](https://github.com/farshadbcn19-eng/lunaci-Master/actions/runs/43485920263) in `test` mode. Backup OK (`pre-product-page-20261113-184705`, 103 tables, 33,476 rows). Check script: 26/26 pages, 0 copy differences, 0 failed checks, add to cart 4/4, no horizontal scroll, no gold fills. Visitors unchanged.

Preview notes (CSS injected into the live pages before deploying):
- Quantity box was white on black; the "Clear" link was theme pink (#CC3366); the shade row had grey boxes. All now in the /products/ style.
- Field borders at gold 60 %: 3.94:1 (WCAG 1.4.11; was 1.41:1). All controls 46 px tall.
- Keyboard only: choosing a shade, visible focus ring, Tab to the button, Enter adds to cart: 8/8 over two runs (EN + ES, shade + simple).
- One row for quantity + button at 320/360/390/768/1024/1440 px, inside the content, no horizontal scroll.
- **T4 correction:** T4's width rule never applied (the theme's `.site-main` rules were more specific; content stayed 1140 px from x = 190). Fixed here with `main.site-main` and the measured /products/ margin formula `8vw + 9px`: within 1 px of /products/ at 390–1920 px.
- Related-products grid minimum `min(260px, 100%)`, so the store snippet's 260 px cards no longer overflow at 320 px.
- Gotcha: the theme animates form fields (`transition`), so values read right after injecting CSS are mid-animation. Wait about 1 s before measuring.

**Dependencies:** T4
**Files likely touched:** `mu-plugins/lunaci-product-page.php`
**Estimated scope:** S

## Task 6: Below the fold and mobile

**Description:** Related products and upsells cards in the /products/ card style, the reviews block, then a mobile pass at 390 px. The tabs are already styled (`lunaci-perf.php` item 6) and stay as they are.

**Acceptance criteria:**
- [x] Related and upsell cards match /products/ (tokens, gold text only)
- [x] No horizontal scroll at 390 px on all 26 URLs
- [x] Item 6 tab styling still applies, without conflict

**Verification:**
- [x] Test-mode deploy + full `--mode=test --cart`
- [x] Manual check: mobile screenshots for 3 products in EN and ES

**Done (2026-11-13):** PR #565, [run #4](https://github.com/farshadbcn19-eng/lunaci-Master/actions/runs/43491440222) in `test` mode. Backup OK (`pre-product-page-20261113-192152`, 103 tables, 33,504 rows). Check script (re-run at 19:38 UTC, after the deploy): 26/26 pages, 0 copy differences, 0 failed checks, add to cart 4/4. Visitors unchanged.

Note: the first automatic check matched `shop_attributes` in the page HTML (a table class), not only in the new CSS, so it may have started before the deploy; it was discarded and re-run.

Preview notes:
- The store snippet fades all below-the-fold text to cream 75 % with `!important`; answered with more specific `!important` rules.
- **Review form was unreadable:** white fields with cream text (about 1.1:1) and a light-grey Submit with cream text. Now dark fields, gold frame, cream text: 17.9:1.
- "Additional information": grey boxes removed; labels are gold captions, values in cream.
- Tab, related-products and reviews headings: white Trade Gothic 300, 32 px (24 px on mobile).
- **Related cards deliberately unchanged** (decision recorded in SPEC): same cards as /shop/.
- Mobile pass: no horizontal scroll in any tab at 320/390/768/1024/1440 px, for 3 products.
- Not in this task: the Spanish "Color" attribute has no value in WooCommerce (data, not CSS).

**Dependencies:** T5
**Files likely touched:** `mu-plugins/lunaci-product-page.php`
**Estimated scope:** S

## Checkpoint B: ready for visitors
- [ ] Every row of the SPEC testing table passes in test mode
- [ ] Before/after screenshots shown to you; you approve `on`

## Task 7: Go live

**Description:** Run the workflow with `on` (after your approval at Checkpoint B), re-run the check script without the test parameter, then run `audit-lighthouse.yml` and `audit-a11y-details.yml` and compare with the latest earlier runs.

**Acceptance criteria:**
- [ ] Live check green for visitors
- [ ] Lighthouse performance and accessibility no lower than before on 2 product pages
- [ ] Rollback documented in the PR: run the workflow with `off`

**Verification:**
- [ ] `node scripts/check-product-pages.mjs --mode=on --cart` passes
- [ ] Audit workflow results linked in the PR

**Dependencies:** Checkpoint B
**Files likely touched:** none in the repo (workflow runs only); PR description
**Estimated scope:** XS

## Checkpoint C: done
- [ ] SPEC success criteria 1–4 met
- [ ] PR reviewed and merged
