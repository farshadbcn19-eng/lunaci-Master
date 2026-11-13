# Spec: Consistent single-product pages (WooCommerce)

Status: **Draft, waiting for approval**. Nothing gets built until this spec is approved.
Date: 2026-11-13

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
`/shop/#<id>`, never to the product's own page.

### Current state (checked 2026-11-13; not guessed)

| Item | Finding | Source |
|---|---|---|
| Live products | **13** published (12 with a shade/colour choice, Eye Pencil without). **Shadow and Concealer are not in WooCommerce.** | `wp-json/wc/store/v1/products` |
| Template | `hello-elementor-child`, default WooCommerce single product, theme header plus global `ln-nav`, theme footer | Live product page HTML |
| Reference design | `pages/products.html`: background `#0F0F10`, gold `#D4AF37`, cream `#F7F4EE`, gold as text only, never as a fill | Repo |
| Fonts as rendered live | Montserrat/Raleway are rendered in **Helvetica LUNACI**; Cormorant Garamond in **Trade Gothic LT Std Extended** (font aliasing) | `mu-plugins/lunaci-perf.php` item 4 |
| Already fixed | Tab colours (item 6), gold and no gold fills (item 7), headings in Trade Gothic | `lunaci-perf.php` |
| Links from /products/ | `href="/shop/#' + p.id + '"`: the card never opens the product page | `pages/products.html` |
| Languages | EN and ES (WPML/WCML) | `deploy-wcml-es-variations.yml` and related |

## Scope

**In scope**
1. **One CSS layer for all product pages:** layout, type scale, spacing, price, variation (shade) selector, quantity box, Add to Cart, breadcrumb and related products, matched to the /products/ visual system. It covers all 13 live products, in both EN and ES.
2. **Product links on /products/:** each card points to its own product page instead of `/shop/#id`. This is a link change only, not copy (mapping table below).
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
pages/products.html                             → links only: /shop/#id → /product/<slug>/
.github/workflows/deploy-product-page.yml       → new: backup, deploy, set mode, live check
scripts/check-product-pages.mjs                 → new: Playwright check (screenshots go to output/, gitignored)
SPEC.md                                         → this file
```

## Code style

Follow `lunaci-perf.php`: one PHP function per concern, a comment explaining *why* each rule exists, selectors scoped under `body.single-product`, design values taken from `products.html`:

```php
// Product page price: same cream-on-black and serif-scale as /products/ cards.
// Gold stays text-only (brand rule: never a fill).
$css .= 'body.single-product div.product p.price{color:#F7F4EE;font-family:var(--serif)}';
```

- No new colours, fonts or sizes that don't already exist in `products.html` or the brand book.
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
| /products/ links | Click each of the 13 cards | Lands on the correct product |

## Boundaries

- **Always:** take a full backup before deploying; check in `test` mode before `on`; show before/after screenshots; follow `brand-docs/` and the Locked VAQAR definition.
- **Ask first:** any text change, including product names (see Open Question 2); WooCommerce data changes (attributes, products, prices); switching to `on` after the test check; anything that touches the cart or checkout.
- **Never:** change homepage copy; manipulate product photos (colour, shape or label); use gold as a fill; add paid plugins; touch anything belonging to Bronci.

## Success criteria

1. All 13 live product pages, in EN and ES, use the same fonts, colours and spacing as /products/.
2. Every card on /products/ (except Shadow) opens the right product page.
3. Every row of the testing table passes.
4. Not one word of copy has changed.
5. Switching `lunaci_product_page_mode` to `off` returns the site to exactly its current state (one-step rollback).

## Open questions

1. **Delivery: mu-plugin or WPCode?** `CLAUDE.md` says custom CSS goes through WPCode snippets. Recent store styling (items 6 and 7) was done in a mu-plugin instead, because it gives the off/test/on switch and a one-step rollback. **Recommendation: mu-plugin.** I need your OK to depart from `CLAUDE.md` here.
2. **Product names:** WooCommerce names differ from the 15 official names in `CLAUDE.md` (for example "Eye Liner" vs "Eyeliner Liquid", "Lip Fix" vs "Lip Gloss Matt Fix", "Lipstick" vs "Lipstick Matt Vegan", "Lipgloss Velvet" vs "Lip Velvet"). Renaming them is a copy change, so it stays out of this spec unless you approve it.
3. **Attribute names are inconsistent** (`Shade` on some products, `Color/Shade` on others). That changes the label above the shade dropdown. Fixing it is a WooCommerce data change, out of scope unless approved.
4. **Shadow:** its card on /products/ has no photo and no product page. Keep its current behaviour until the product exists? (Assumed yes.)
