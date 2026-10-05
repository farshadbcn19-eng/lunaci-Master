# Spanish global menu, English-only About page, Spanish footer copyright — applied live 2026-10-05, run 37354636418 (all checks passed)

## What was wrong
- The global header (Code Snippet 8) showed Home / Products / About / Contact, linked to the English pages, on every Spanish page.
- The theme footer showed "All rights reserved" on Spanish pages. It comes from one Elementor kit setting used for all languages.
- The English About page (`/about-us/`) had Spanish lines under the English copy in 20 places. Every other English page is English-only, and the Spanish page now exists separately at `/es/sobre-nosotros/`.

## Change
- **Header:** the labels and links follow the WPML language.
  - Spanish pages: Inicio, Productos, Sobre Nosotros, Contacto. The logo goes to `/es/`, the cart to `/es/carrito/`, and the aria-labels are in Spanish.
  - The English output is byte-identical to before; this was checked by rendering both versions.
  - This also applies to the Spanish home page header, under your instruction for the global menu. Nothing inside the home page content changes.
- **English About:** the Spanish elements are removed. No English word is changed.
- **Theme footer copyright on Spanish pages:** "Todos los derechos reservados" (lunaci-seo.php item 8, option `lunaci_es_copyright`).

## Self-review log
1. **Round 1:**
   - The menu labels match the Spanish page titles (Inicio, Productos, Sobre Nosotros, Contacto), and the aria-labels are Spanish.
   - The English About change is removal only. A diff confirms 20 removed elements and the label "Our Story · Nuestra Historia" → "Our Story".
2. **Round 2:**
   - English header output is byte-identical (rendered old and new code with stubs).
   - The language follows WPML, so the Spanish shop, cart and product pages get the Spanish menu too.
   - The new snippet code passes `php -l`, and is linted again on the server before it is written.
3. **Round 3:**
   - Rendered on the live pages. The Spanish menu fits on one row on desktop ("SOBRE NOSOTROS" included), and the mobile menu stacks as before.
   - The English About page is clean.
   - "Todos los derechos reservados" matches the Spanish footers already on the site.

## Not changed (needs your instruction)
- The English About quote reads "All women are seen. Yet your presence is remembered." The official tagline is "Every woman is seen. But your presence is remembered."
- The English About page also contains "less, when done perfectly", and "perfect" is forbidden.
