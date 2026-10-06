# SEO re-audit and WPML coverage — 2026-10-06

## Score: 79 / 100 (previous re-audit 69, first audit 49)

| Area | Weight | Score | Evidence |
|---|---|---|---|
| Technical / crawl / index | 20 | 19 | All 50 sitemap URLs return 200. Sitemap is clean, including `/es/sobre-nosotros/`, and cart/checkout/account are excluded. Canonicals are self-referencing. Cached TTFB is 0.36–0.49 s. |
| On-page meta | 15 | 14 | 50/50 titles are unique and ≤60 characters, and descriptions are unique. Two home descriptions are slightly short (EN 101, ES 108); the home page is protected by the hard rule. |
| Content / E-E-A-T | 20 | 14 | Category intros are added, and the Spanish About page is complete. 13 pages are under 250 words (4 categories, 4 EN products, shipping/returns). Concealer and Shadow (2 of 15 SKUs) return 404. |
| International (hreflang / ES) | 10 | 8 | hreflang and `lang` are correct on every page, the Spanish UI is Spanish, and the slugs are Spanish. Gaps: 6 Spanish products have no shade choice, and some WooCommerce texts are untranslated (see below). |
| Structured data | 10 | 7 | Product schema has brand, shipping and return policy, plus Organization and Breadcrumb. GTIN and reviews/aggregateRating are missing. |
| Performance (Lighthouse mobile) | 15 | 10 | Performance scores 75–99. Lab LCP is 1.7–5.9 s (ES About 5.9, products 4.0–5.3), CLS is about 0, and TBT is under 150 ms except the EN home (670 ms). |
| Accessibility / best practices | 10 | 7 | Accessibility scores 87–96: colour contrast, the EN cart link has no name, and the skip link. Best practices is 75 everywhere: third-party cookies from Hostinger Reach `embed.js` and one 404 resource in the console. |

Lighthouse SEO scores 100 on all 8 audited pages (EN and ES home, product, category and About). The ES category page scored 92 in the previous audit.

## WPML: are all translations in place? — No

### In place
- 12 of 13 English pages have a Spanish page. The one without is `All Products` (836); the Spanish "Todos" tab correctly uses `/es/productos/`.
- 13 of 14 English products have a Spanish product. The one without is Shadow (404, not published).
- All 4 real product categories have Spanish translations.
- Page-builder strings for Home, About, Contact and Products are registered as complete Spanish translations (2026-10-06).
- The global menu, footer copyright and taglines are in Spanish on Spanish pages.

### Missing, and customers see it
1. **Product variations (shades): 114 English variations have no Spanish translation.** Six Spanish products are *simple* products with no shade selector, while the English product is variable:

   | English | Spanish |
   |---|---|
   | Lip Fix (6) | Fijador de Labios |
   | Lipgloss Velvet (4) | Brillo de Labios Velvet |
   | Lip Pencil (5) | Delineador de Labios |
   | Compact Powder (4) | Polvo Compacto |
   | Eyebrow Pencil (3) | Lápiz de Cejas |
   | Nail Polish (12, placeholder shades) | Esmalte de Uñas |

   A Spanish customer cannot choose a shade. Lipstick, Foundation and Blusher have variations in both languages, but those variations are not linked as WPML translations.
2. **Colour attribute `pa_color`: 36 terms have no Spanish translation.** Two shade names contain the forbidden word "Perfect" ("Perfect 04", "Perfect 04-V").
3. **WooCommerce strings:**
   - The checkout and registration privacy texts are English only.
   - The 15 account endpoint slugs are untranslated.
   - The email subject and heading fields are blank, which means WooCommerce's own default text is used.
4. **Gutenberg strings for the legal and shipping/returns pages** (privacy 35, terms 31, shipping 4, returns 4) have no Spanish translation. The Spanish pages exist and are Spanish today, but they carry the same WPML-rebuild risk the Elementor pages had until 2026-10-06.

### Missing but not needed (internal or unused)
- Theme `custom_css`, the Elementor Default Kit and Code Snippets / WPCode entries
- The unused block-theme navigation and template parts, and the unused "Main Menu"
- WooCommerce `product_visibility` terms
- The empty legacy categories Uncategorized, Eyes Glow, Lips Bold and Nails Chic (0 products; candidates for deletion)
- AIOSEO option templates, which are placeholders only

## Recommended order
1. Spanish shade selection: link the 114 variations and 36 colour terms through WCML, so the 6 Spanish products become variable with Spanish shade names. Rename the "Perfect" shades.
2. Extend the Spanish-page protection to the legal and shipping/returns pages.
3. Translate the WooCommerce checkout/registration privacy texts and the account endpoints.
4. Accessibility and best-practices fixes: a name for the EN cart link, colour contrast, the skip link, the 404 resource, and the Hostinger Reach cookie.
5. Content: enrich the four thinnest product pages, publish Concealer and Shadow, and add GTIN and reviews to the schema.
6. Performance: LCP on the About and product pages (hero image priority and size).

## Update — item 1 done (2026-10-06, run 37446462902)
- The 57 Spanish variations are linked in WPML to the 57 English variations, and carry WCML's marker. The six Spanish parents are now variable.
- **Live check:** all 9 Spanish variable products show a shade selector with the same shades and prices as English. A Store API add-to-cart of a Spanish variation succeeded (Fijador / PETAL-01).
- "Perfect 04-V" became "Signature 04-V" in both languages, and the `pa_color` term "Perfect 04" became "Signature 04".
- The attribute label displays as "Tono" on Spanish pages (`lunaci-seo.php` item 10).
- **Still open:**
  - The nail polish shades are placeholders ("Shade 01 (placeholder)") in both languages and need the real shade names.
  - WooCommerce's default review-rating labels include "Perfect" / "Perfecto".
  - The 36 `pa_color` terms are untranslated. They are not used by the shade selectors.

## Update — item 3 done (2026-10-06, run 37448098583)
- The WooCommerce checkout and registration privacy notices display in Spanish on Spanish pages (`lunaci-seo.php` item 11, display only). The `[privacy_policy]` link points to `/es/politica-de-privacidad/`.
- **Live check, browser plus a test cart (variation 712), after WooCommerce's AJAX refresh:**
  - `/es/finalizar-compra/`: "Utilizaremos tus datos personales para procesar tu pedido, facilitar tu experiencia en esta web y para los demás fines descritos en nuestra política de privacidad." The link goes to `/es/politica-de-privacidad/`.
  - `/checkout/`: the English text and the `/privacy-policy/` link are unchanged.
  - Screenshot: `es-checkout-privacy.png`.
- Cart, checkout and account pages were already Spanish apart from this text. The account endpoints are noindex, so translating their slugs adds no SEO value. Registration is switched off on `/mi-cuenta/`; the Spanish registration text is ready if it is switched on.
- **New critical finding, not SEO:** checkout shows "no payment methods available" in **both languages**. The test cart used the default address (Spain / Madrid). The "Place order" button is visible, but no order can be paid. **Known and expected (owner, 2026-10-06):** the payment gateway is pending setup with the bank. Re-test checkout end to end once it is live.
- **Brand note:** the checkout keeps WooCommerce's default purple button and pink links, not the brand's black and `#D4AF37`.

## Update — item 2 done (2026-10-06, run 37449633484)
- The Spanish Gutenberg pages are now protected: 765 `/es/envio/`, 766 `/es/devoluciones/`, 768 `/es/terminos-de-servicio/` and 769 `/es/politica-de-privacidad/`. This is `lunaci-seo.php` item 12, enabled by option `lunaci_es_post_guard` = on.
  - If a save would replace their Spanish content with mostly English content (a WPML rebuild from the English original), the guard keeps the current content and title. The attempt is logged in `lunaci_es_guard_log`.
  - Spanish edits go through.
- **Diagnosis (run 37449297225):** all four pages were Spanish, paired correctly in WPML, and not flagged as duplicates. They had no WPML translation job and 0 of 74 Gutenberg strings translated. Item 9 did not cover them, because these pages keep their text in `post_content`, not Elementor meta.
- **Apply:**
  - Full backup first: `~/lunaci-backups/pre-es-legal-guard-20261006-102517`, 102 tables.
  - Filter test for each page, with no writes to any post: the English original came back as the current Spanish content, and a Spanish edit passed through. md5 confirmed the four pages were unchanged.
  - Live: the 4 Spanish pages are Spanish and the 4 English pages are English.
- **Rollback:** `deploy-es-legal-guard.yml`, mode `rollback`.
- **Protection now covers every Spanish content page:**
  - Elementor: `/es/`, `/es/sobre-nosotros/`, `/es/contacto/`, `/es/productos/`.
  - Gutenberg: shipping, returns, terms, privacy.
  - Cart, checkout and account have no text content to protect.

## Update — all Spanish translations registered in WPML (2026-10-06)
**Result:** 124 of 162 WPML strings now have a complete Spanish translation, up from 10. The 38 that remain hold no text: 37 are empty fields, plus the WordPress sample post "Hello world".

**Gutenberg pages (run 37454321385; backup `pre-wpml-gutenberg-es-20261006-110751`):**
- The English WPML strings of shipping, returns, terms and privacy were out of date. They had the old email `info@lunaci.es`, "Patriotic Trade Co.", an older GDPR paragraph, and the returns hygiene paragraph was missing. A WPML rebuild would have brought that old English text back.
- 65 strings were kept. Each is named the way WPML names it, `md5(blockName . value)`, and the extraction matched WPML byte for byte on all 65.
- 10 strings were added and 9 obsolete ones dropped.
- All 75 now carry the live Spanish block as their complete translation.
- Direct database writes only: no page was saved, and md5 confirmed the Spanish pages were unchanged.

**Store (run 37454824840; backup `pre-wpml-store-es-*`), 39 string translations:**
- Checkout and registration privacy notices.
- Email footer and sender, price separators, sharing label "Compartir:", site title.
- The cart, checkout and account shortcode strings.
- 13 of the 15 endpoint slugs translated, for example `/es/mi-cuenta/recuperar-contrasena/`, `pedidos` and `editar-direccion`. `wc-api` and `wc/file/transient` keep their value on purpose, because payment gateways and file downloads depend on them.
- 13 AIOSEO templates, for example "404 - Página no encontrada" and "Archivo de …".

**Terms:**
- 36 Spanish `pa_color` shade terms, with the same shade names (they match the packaging) and slugs built from the current names.
- "Sin categoría".
- No product was changed. Live checks: the shade selectors and the "Tono" label still show, and the Spanish checkout privacy notice is unchanged.

**Live checks:**
- The Spanish cart, checkout, account and password-recovery pages work.
- The Spanish account page links to the Spanish password URL.
- The English URLs are unchanged.

**Left untranslated on purpose:**
- The 37 empty fields: blank email subjects and headings, and empty AIOSEO fields. WooCommerce and AIOSEO use their own translated defaults for these.
- The legacy empty categories Eyes Glow, Lips Bold and Nails Chic (0 products). These are candidates for deletion.
- `product_visibility`, the theme and template parts, WPCode, the unused "Main Menu" and the Default Kit. All are internal or unused.
- The unpublished Shadow product and the EN-only "All Products" page. The Spanish "Todos" tab uses `/es/productos/`.

**Rollback:** `deploy-wpml-gutenberg-es.yml` and `deploy-wpml-store-es.yml`, mode `rollback`.

## Update — item 4 accessibility done (2026-10-06, Lighthouse mobile run 37459830629)
| Page | Accessibility before → after | Best practices before → after |
|---|---|---|
| /product/lipstick/ | 89 → 100 | 75 → 79 |
| /product-category/lips/ | 87 → 100 | 75 → 79 |
| /about-us/ | 92 → 100 | 75 → 79 |
| /shipping/ | 96 → 100 | 75 → 79 |
| /contact/ | 88 → 98 | 75 → 79 |
| /products/ | 90 → 98 | 75 → 79 |
| /es/ | 96 → 96 | 75 → 79 |

**What changed** (`deploy-a11y-fixes.yml`; backup `pre-a11y-fixes-*`):
- **Cart link:** the English cart link in snippet 8 now has `aria-label="Cart"`.
- **WPCode CSS snippet "LUNACI Accessibility Contrast (WCAG AA)", v3:**
  - Greys are `#9a9894` with opacity 1.
  - Links are `#D4AF37` and underlined.
  - Text on gold is `#3a2f0f`.
  - Decorative numbers are `#8a7530`.
  - Every rule is scoped to `body:not(.home)`; the theme copyright rule applies only to the About pages.
- **`lunaci-seo.php` items 13–15:** the WooCommerce `#content` skip target; the favicon and touch icons (the "L" mark, cropped from the logo with no colour change); and the two missing-font `@font-face` rules on the contact page, which are dropped from the output.

**Open, needs the owner's decision:**
- ~~Contrast on the home page~~: fixed with the owner's approval (see below).
- The Hostinger Reach `embed.js` third-party cookie. It is the only thing holding Best Practices at 79.
- Footer heading order (h4) on contact and products, weight 3.

**Home page contrast (owner-approved, run 37461172936):** CSS v4 changes only the colours:
- Marquee: gold alpha 0.6 → 0.75.
- Footer line: cream alpha 0.35 → 0.6.

Copy and layout are unchanged. Accessibility on `/` and `/es/` is now **100**. Every audited page scores 98–100. Still open: Hostinger Reach (Best Practices 79) and the footer h4 order on contact and products.

## Update — "All" removed, category banners fixed on mobile (2026-10-06, run 37464054048)
- **"All" removed (owner request):**
  - The All/Todos button is gone from the category filter tabs (snippet 6, both languages).
  - Page 836 "All Products" is in the trash, so it can be restored.
  - `/all-products/` now returns a 301 to `/products/` (`lunaci-seo.php` item 16).
- **Category banners:** WooCommerce's `.woocommerce img {height:auto}` overrode the banner image height, so on mobile the image stayed 163 px tall inside a 260 px (3:2) box. That left an empty band under the banner. Snippet 7 now has `height: 100% !important`.
  - Mobile: 390×260 with no gap.
  - Desktop: fills the 21:9 box exactly (585 px at 1366).
  - Checked on all 4 categories, EN and ES.
- **Rollback:** `deploy-all-products-banner.yml`, mode `rollback`. Backup: `pre-all-products-banner-*`.
