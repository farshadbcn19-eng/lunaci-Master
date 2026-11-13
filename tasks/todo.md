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
- [ ] Nothing is printed when the mode is off, on non-product pages, in admin, REST or Elementor preview (handled by the reused helper)
- [ ] If `lunaci_perf_mode_active` doesn't exist, the plugin does nothing
- [ ] No HTML or copy changes

**Verification:**
- [ ] `php -l mu-plugins/lunaci-product-page.php` passes
- [ ] Small self-check: a PHP snippet with WordPress functions stubbed covers on, off, non-product page and missing helper

**Dependencies:** None
**Files likely touched:** `mu-plugins/lunaci-product-page.php`
**Estimated scope:** XS

## Task 3: Deploy workflow with backup and live check

**Description:** `.github/workflows/deploy-product-page.yml`, cloned from `deploy-wc-style.yml`: full backup (database, mu-plugins, .htaccess, current mode values), deploy the file, set `lunaci_product_page_mode`, purge LiteSpeed, then a live check for the `lunaci-product-page` marker.

**Acceptance criteria:**
- [ ] Inputs `test` / `on` / `off`; same `concurrency` group as the other Hostinger deploys
- [ ] The run stops if the backup fails or is incomplete
- [ ] Live check covers 2 EN + 2 ES product pages, `/products/`, `/cart/` and `/`: marker only on product pages; in `test` mode absent for visitors

**Verification:**
- [ ] The YAML parses (`python3 -c "import yaml…"`); the diff against `deploy-wc-style.yml` shows only the intended changes
- [ ] **Checkpoint A run (with your OK):** workflow `test` → "ALL CHECKS PASSED"; T1 script reports 0 differences

**Dependencies:** T2
**Files likely touched:** `.github/workflows/deploy-product-page.yml`
**Estimated scope:** S

## Checkpoint A: pipeline proven
- [ ] Test-mode deploy green; visitors unchanged; 0 differences from the baseline
- [ ] Review with you before any styling

## Task 4: Layout and typography

**Description:** CSS for the top of the page: gallery and summary grid, product title, price, short description, breadcrumb. Values taken from the /products/ tokens captured in T1.

**Acceptance criteria:**
- [ ] Fonts, colours and spacing match /products/ (script token check)
- [ ] Gold used only for text and rules, never as a fill
- [ ] Copy diff 0 on all 26 URLs

**Verification:**
- [ ] Test-mode deploy + `node scripts/check-product-pages.mjs --mode=test`
- [ ] Manual check: desktop screenshot of Lipstick in EN and ES next to /products/

**Dependencies:** Checkpoint A
**Files likely touched:** `mu-plugins/lunaci-product-page.php`
**Estimated scope:** S

## Task 5: Purchase controls

**Description:** CSS for the shade dropdown and its label, the "Clear" link, quantity box, Add to Cart (transparent with a gold border, as in `lunaci-perf.php` item 7), the in-stock message and the selected-variation price.

**Acceptance criteria:**
- [ ] `--cart` passes for Lipstick and Eye Pencil, in EN and ES
- [ ] Add to Cart is visible without scrolling sideways at 390 px; touch targets are at least 44 px
- [ ] Visible keyboard focus on the dropdown, quantity and button

**Verification:**
- [ ] Test-mode deploy + `--mode=test --cart`
- [ ] Manual check: keyboard-only walkthrough on one product

**Dependencies:** T4
**Files likely touched:** `mu-plugins/lunaci-product-page.php`
**Estimated scope:** S

## Task 6: Below the fold and mobile

**Description:** Related products and upsells cards in the /products/ card style, the reviews block, then a mobile pass at 390 px. The tabs are already styled (`lunaci-perf.php` item 6) and stay as they are.

**Acceptance criteria:**
- [ ] Related and upsell cards match /products/ (tokens, gold text only)
- [ ] No horizontal scroll at 390 px on all 26 URLs
- [ ] Item 6 tab styling still applies, without conflict

**Verification:**
- [ ] Test-mode deploy + full `--mode=test --cart`
- [ ] Manual check: mobile screenshots for 3 products in EN and ES

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
