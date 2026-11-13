# Implementation Plan: Consistent single-product pages

Spec: [`SPEC.md`](../SPEC.md) (approved 2026-11-13, Revision 1). Tasks: [`tasks/todo.md`](todo.md).

## Overview

A new mu-plugin prints one scoped CSS block on WooCommerce product pages only. It is switched with `lunaci_product_page_mode` (off / test / on) and deployed by a GitHub Actions workflow cloned from `deploy-wc-style.yml`. A Playwright script captures a baseline first and proves afterwards that copy, purchase flow and mobile layout are unchanged and that the design matches the live /products/ page.

## Architecture decisions

- **Separate file `mu-plugins/lunaci-product-page.php`**, not more items in `lunaci-perf.php`. The perf plugin is already 350 lines and covers seven unrelated concerns; a separate file gives its own rollback and its own deploy.
- **Reuse `lunaci_perf_mode_active()`** from `lunaci-perf.php` instead of copying it. mu-plugins load alphabetically (`lunaci-perf` before `lunaci-product-page`). Guarded by `function_exists`: if the helper is ever missing, the layer stays **off**, never on.
- **CSS only, printed as `<style id="lunaci-product-page">`** on `is_product()` pages. The id is the live-check marker, the same pattern as `lunaci-wc-style`. No hooks that change HTML or copy.
- **Design tokens are read from the live /products/ page, not `pages/products.html`**, which is stale (SPEC Revision 1).
- **Every deploy is a manual workflow run that you approve.** I prepare it and trigger it only after your OK, because it changes the live site.

## Dependency graph

```
T1 baseline + check script ─┐
T2 mu-plugin skeleton ──────┼─→ T3 deploy workflow ─→ [CP-A: test-mode deploy]
                            │                              │
                            └──────────────────────────────┼─→ T4 layout + type
                                                           ├─→ T5 purchase controls
                                                           └─→ T6 below-fold + mobile ─→ [CP-B] ─→ T7 go live ─→ [CP-C]
```

T4–T6 all edit the same CSS file, so they are done one after another, not in parallel.

## Task list

### Phase 1: Foundation (pipeline before styling, so failures show up early)
- [x] T1: Baseline capture and check script
- [x] T2: mu-plugin skeleton with mode switch and marker
- [ ] T3: Deploy workflow with backup and live check

### Checkpoint A: pipeline proven
- [ ] Workflow run with `test` (your OK first): marker present on the 13 EN + 13 ES product pages with `?lunaci_perf=1`; absent for visitors; absent on non-product pages
- [ ] Check script reports **0 differences** from the baseline (the skeleton changes nothing visible)

### Phase 2: Styling, one slice per task
- [ ] T4: Layout and typography (gallery and summary, title, price, short description, breadcrumb)
- [ ] T5: Purchase controls (shade dropdown, quantity, Add to Cart, stock and reset messages)
- [ ] T6: Below the fold and mobile (related products, upsells, reviews, 390 px)

### Checkpoint B: ready for visitors
- [ ] Test-mode deploy; check script passes every row of the SPEC testing table
- [ ] Before/after screenshots (desktop + mobile, EN + ES) shown to you, and you approve `on`

### Phase 3: Go live
- [ ] T7: Switch to `on`, run Lighthouse and accessibility audits, confirm rollback

### Checkpoint C: done
- [ ] SPEC success criteria 1–4 met; PR merged

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Theme/Elementor `!important` rules beat the new CSS | Med | Selectors under `body.single-product`; `!important` only where the theme forces it, with a comment (SPEC code style) |
| CSS hides or moves the shade dropdown and breaks add to cart | **High** | T5 is its own task; the check script adds a variable and a simple product to the cart in EN and ES before `on` |
| Spanish pages differ (WPML/WCML labels, longer words) | Med | All 26 URLs in every check; mobile screenshots in ES too |
| LiteSpeed serves cached pages without the CSS | Low | The workflow purges the cache (existing pattern); `?lunaci_perf=1` URLs are cached separately |
| `lunaci-perf.php` is missing or renamed later | Low | `function_exists` guard means the layer stays off, never broken |
| Live /products/ design changes during the work | Low | Tokens captured into the baseline at T1; re-run T1 if /products/ is edited |

## Open questions (carried from the SPEC, out of scope unless you decide otherwise)
- Product names that differ from the official names (copy change)
- `Shade` vs `Color/Shade` attribute labels (WooCommerce data change)
