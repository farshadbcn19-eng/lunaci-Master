# Spec: Consistent single-product pages (WooCommerce)

Status: **Approved 2026-11-13** (delivery: mu-plugin). Revised the same day: reference corrected to the live /products/ page (see Revision 1).
Date: 2026-11-13

> **Revision 1 (planning):** the repo file `pages/products.html` is **not** what is live at /products/. The live page is a different Elementor HTML widget (sections *The LUNACI Portfolio*, *Signature Pieces*, *Crafted with Intention*). It uses `#0B0B0B` + `#D4AF37` and already links to product and category pages. So the reference is the **live** page, and scope item 2 (fixing `/shop/#id` links) is dropped because the problem does not exist live.

## Objective

Every LUNACI product page (`/product/<slug>/`) should look like it belongs to the
/products/ page visitors come from. **Nothing leaves the current design:** no new
visual language, no new copy. This work only brings the product pages in line with
what already exists.

**User:** a visitor who clicks a product on /products/ (or arrives from search) and
needs to choose a shade and add it to the cart.

**Why now:** live product pages use the default WooCommerce/Hello Elementor
template. Only partial fixes have been layered on (tab colors, gold, fonts in
`lunaci-perf.php` items 4, 6 and 7). Every product card on /products/ links to
`/shop/#<id>`, never to the product's own page. *(Revision 1: true only for the stale repo file; the live page already links correctly.)*

### Current state (checked 2026-11-13; not guessed)

| Item | Finding | Source |
|---|---|---|
| Live products | **13** published (12 with a shade/colour choice, Eye Pencil without). **Shadow and Concealer are not in WooCommerce.** | `wp-json/wc/store/v1/products` |
| Template | `hello-elementor-child`, default WooCommerce single product, theme header plus global `ln-nav`, theme footer | Live product page HTML |
| Reference design | **Live /products/** (Elementor HTML widget `lunaci-products-page`): background `#0B0B0B`, gold `#D4AF37` as text and rules only. *(`pages/products.html` in the repo is stale and is not used.)* | Live HTML |
| Fonts as rendered live | Montserrat/Raleway are rendered in **Helvetica LUNACI**; Cormorant Garamond in **Trade Gothic LT Std Extended** (font aliasing) | `mu-plugins/lunaci-perf.php` item 4 |
| Already fixed | Tab colours (item 6), gold and no gold fills (item 7), headings in Trade Gothic | `lunaci-perf.php` |
| Links from /products/ | Live: links to `/product/<slug>/` (4 signature pieces) and `/product-category/*` | Live HTML |
| Languages | EN and ES (WPML/WCML) | `deploy-wcml-es-variations.yml` and related |

## Scope

**In scope**
1. **One CSS layer for all product pages:** layout, type scale, spacing, price, variation (shade) selector, quantity box, Add to Cart, breadcrumb and related products, matched to the /products/ visual system. It covers all 13 live products, in both EN and ES.
2. ~~Product links on /products/~~ *(dropped in Revision 1: already correct live).*
3. **Two products parked:** the layer is written so Shadow and Concealer take on the same look automatically once they exist in WooCommerce. Nothing extra is built for them.

**Out of scope (needs a separate decision)**
- Any product name, description, short description or price.
- Creating the Shadow and Concealer products.
- Turning the shade dropdown into visual swatches (that needs JS and HTML changes).
- The homepage (Page ID 57) and the cart and checkout pages.

### Link mapping /products/ → product page

| `products.html` id | WooCommerce slug |
|---|---|
| lip-pencil | lip-pencil |
| lipstick-matt-vegan | lipstick |
| lip-gloss-matt-fix | lip-fix |
| lip-velvet | lipgloss-velvet |
| eyebrow-pencil | eyebrow-pencil |
| eye-pencil | eye-pencil |
| eyeliner-liquid | eye-liner |
| mascara-volume | mascara-volume |
| mascara-length | mascara-length |
| foundation | foundation |
| compact-powder | compact-powder |
| blusher | blusher |
| nail-polish-long-lasting | nail-polish-long-lasting |
| shadow | *(no product; card keeps its current behaviour)* |

## Tech stack

- WordPress, WooCommerce, Hello Elementor child theme, Elementor **Free**, WPML/WCML. Hosting: Hostinger.
- No new plugins or dependencies.
- **Delivery:** a new mu-plugin `mu-plugins/lunaci-product-page.php`, following the existing `lunaci-perf.php` pattern: CSS printed only when `is_product()` is true, plus an option `lunaci_product_page_mode` = `off` / `test` / `on`. `test` applies the CSS only to URLs ending `?lunaci_perf=1`, so it can be checked live without visitors seeing it. *(See Open Question 1 on mu-plugin vs WPCode.)*

## Commands

```
# Deploy (GitHub Actions, same pattern as deploy-wc-style.yml: full backup, deploy, live check)
Actions → "Deploy - product page template (mu-plugin)" → mode: test | on | off

# Visual and functional check (Playwright is preinstalled in the session)
node scripts/check-product-pages.mjs --mode=test     # all 13 EN and ES URLs, screenshots + assertions
node scripts/check-product-pages.mjs --mode=on

# Existing audits, run after "on"
Actions → audit-lighthouse.yml
Actions → audit-a11y-details.yml
```

## Project structure

```
mu-plugins/lunaci-product-page.php              → new: the CSS layer + mode switch
.github/workflows/deploy-product-page.yml       → new: backup, deploy, set mode, live check
scripts/check-product-pages.mjs                 → new: Playwright check (screenshots go to output/, gitignored)
SPEC.md                                         → this file
```

## Code style

Follow `lunaci-perf.php`: one PHP function per concern, a comment explaining *why* each rule exists, selectors scoped under `body.single-product`, design values taken from the live /products/ page:

```php
// Product page price: same cream-on-black and serif-scale as /products/ cards.
// Gold stays text-only (brand rule: never a fill).
$css .= 'body.single-product div.product p.price{color:#F7F4EE;font-family:var(--serif)}';
```

- No new colours, fonts or sizes that don't already exist on the live /products/ page or in the brand book.
- `!important` only where the theme or WooCommerce already forces a value, with a one-line comment why.
- No HTML or copy changes from PHP, apart from the `products.html` links.

## Testing strategy

There is no test framework in this repo, so verification is a script plus the existing audit workflows:

| What | How | Pass |
|---|---|---|
| Pages load | 13 EN + 13 ES URLs | All HTTP 200 |
| Copy unchanged | Visible text of title, price, descriptions and tabs, compared before and after | 0 differences |
| Design matches | Computed background, text colour and fonts compared with /products/ | Same tokens |
| Gold never used as a fill | Scan computed `background-color` of every element | No `#D4AF37` |
| Purchase still works | One product with shades: choose a shade, add to cart, cart count +1. Plus Eye Pencil (no shade choice). | Both pass |
| Mobile | 390 px viewport screenshots | No horizontal scroll, Add to Cart visible without zooming |
| Speed and accessibility | `audit-lighthouse`, `audit-a11y-details` | No regression against today's scores |

## Boundaries

- **Always:** take a full backup before deploying; check in `test` mode before `on`; show before/after screenshots; follow `brand-docs/` and the Locked VAQAR definition.
- **Ask first:** any text change, including product names (see Open Question 2); WooCommerce data changes (attributes, products, prices); switching to `on` after the test check; anything that touches the cart or checkout.
- **Never:** change homepage copy; manipulate product photos (colour, shape or label); use gold as a fill; add paid plugins; touch anything belonging to Bronci.

## Success criteria

1. All 13 live product pages, in EN and ES, use the same fonts, colours and spacing as /products/.
2. Every row of the testing table passes.
3. Not one word of copy has changed.
4. Switching `lunaci_product_page_mode` to `off` returns the site to exactly its current state (one-step rollback).

## Decisions (2026-11-13)

- **Product names:** all current WooCommerce names are correct and stay as they are (closes Open Question 2).
- **Shadow and Concealer:** stay exactly as they are (not created in WooCommerce, no change on /products/).
- **Related-product cards:** left as the store snippet draws them (the same cards as /shop/ and the category pages), so the product page doesn't depart from the existing store design. Only the 320 px grid fix applies.

## Open questions

1. ~~**Delivery: mu-plugin or WPCode?**~~ **Decided: mu-plugin** (approved 2026-11-13). `CLAUDE.md` says custom CSS goes through WPCode snippets. Recent store styling (items 6 and 7) was done in a mu-plugin instead, because it gives the off/test/on switch and a one-step rollback. **Recommendation: mu-plugin.** I need your OK to depart from `CLAUDE.md` here.
2. **Product names:** WooCommerce names differ from the 15 official names in `CLAUDE.md` (for example "Eye Liner" vs "Eyeliner Liquid", "Lip Fix" vs "Lip Gloss Matt Fix", "Lipstick" vs "Lipstick Matt Vegan", "Lipgloss Velvet" vs "Lip Velvet"). Renaming them is a copy change, so it stays out of this spec unless you approve it.
3. **Attribute names are inconsistent** (`Shade` on some products, `Color/Shade` on others). That changes the label above the shade dropdown. Fixing it is a WooCommerce data change, out of scope unless approved.
4. ~~Shadow card~~ *(moot after Revision 1: the live /products/ page has no Shadow card).*
