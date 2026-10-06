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
