# Spanish About page (/es/about-us-es/ → /es/sobre-nosotros/)

## What was wrong (live page, 2026-10-05)
- The H1 was in English ("Our Story"), and so was the hero subtitle ("Born from the golden light of Barcelona").
- Two H2s were in English: "Why LUNACI Exists" and "Our Values".
- All five value descriptions were in English.
- The closing line "Mediterranean Luxury Beauty House" was in English.
- The whole page footer was in English and linked to the **English** pages.
- The slug `about-us-es` was English.

## Change
- Every visible line on the page is now in Spanish. The text is the full translation of the English About page, which is longer than the old Spanish summary.
- The footer uses the same wording as the existing Spanish home footer, with links to the Spanish pages. That includes the Spanish category URLs and adds Uñas.
- Slug `about-us-es` → `sobre-nosotros`, with a 301 from the old URL.
- Hard-coded links to `/es/about-us-es/` in the other Spanish pages are updated. The front page is never edited (hard rule); its link reaches the page through the 301.
- The CSS, layout, images and English page are unchanged. Brand fonts stay as they are today, because `lunaci-perf.php` aliases them on the server.

## Copy (ES)
- **H1:** Nuestra *Historia*. **Subtitle:** Nacida de la luz dorada de Barcelona
- **Label:** Desde Barcelona. **H2:** El *Comienzo*
  - LUNACI Barcelona nació de una filosofía: una profunda apreciación por la belleza como elemento esencial de la experiencia humana. Desde el principio, nuestra ambición fue más allá de crear otro nombre dentro de la industria de la belleza.
  - El sueño detrás de LUNACI era crear un universo completo de belleza. Un universo donde la calidad, la estética, la artesanía y la expresión personal conviven en una misma filosofía. Un universo donde la belleza no se reduce a la apariencia, sino que se eleva hasta convertirse en una experiencia.
  - (third paragraph unchanged)
- **H2:** Por qué *existe LUNACI*
  - La belleza debe apoyar la expresión personal. Debe fortalecer la confianza. Debe mejorar la calidad de vida. Y debe permitir que cada persona muestre la versión más auténtica de sí misma. No es solo una filosofía: es la razón por la que LUNACI fue creada.
  - **Confianza:** Cada producto, campaña y comunicación debe fortalecer la confianza. La verdadera confianza nace desde dentro, no de la aprobación ajena. LUNACI existe para acompañar la confianza que ya vive en cada mujer.
  - **Autenticidad:** La marca debe ser genuina, honesta y emocionalmente creíble en todo lo que crea. Celebramos la individualidad en lugar de imponer estándares. La belleza nunca debe convertirse en una fuente de presión, sino de libertad.
  - **Elegancia:** El lujo debe ser refinado, intencional e intemporal. La verdadera elegancia nunca requiere exceso: nace de la contención, del cuidado artesanal y de saber que menos, hecho con intención, siempre es más.
- **H2:** Nuestros *Valores*
  - **Confianza:** Fortalecer la confianza interior de cada mujer que elige LUNACI
  - **Autenticidad:** Ser genuinos, honestos y emocionalmente creíbles en todo lo que creamos
  - **Elegancia:** Un lujo refinado, intencional e intemporal, presente en cada detalle
  - **Calidad:** Una excelencia innegociable en productos, envases y experiencia de cliente
  - **Presencia:** Crear momentos memorables que dejan una impresión duradera y significativa
- **Quote:** unchanged. It is the Spanish tagline already used on the Spanish home page. **Sub-line:** LUNACI Barcelona · Casa de Belleza de Lujo Mediterránea
- **Footer:**
  - Belleza Mediterránea. Confianza Serena. Elegancia Atemporal.
  - Tienda / Marca / Ayuda
  - © 2026 LUNACI Barcelona. Todos los derechos reservados.

## Self-review log
1. **Round 1:**
   - Automated check: no English left in the page body, and 0 forbidden words.
   - The label under the hero repeated the H1 ("Nuestra Historia"), so it now reads "Desde Barcelona".
2. **Round 2:**
   - Voice and grammar: long dashes became colons (restraint), and the values list mirrors the English structure.
   - The confidence line matches VAQAR ("does not seek approval").
   - The English page's "less, when done perfectly" was **not** carried over ("perfect" is forbidden). The Spanish reads "menos, hecho con intención".
   - LUNACI is feminine ("fue creada"), so the subtitle uses "Nacida".
3. **Round 3:**
   - Rendered on the live page (desktop and mobile).
   - All target URLs return 200, and `sobre-nosotros` is free (404).

## Found, not changed (needs your instruction)
- The global site header shows Home/Products/About/Contact in English on every Spanish page. It is a sitewide element.
- The Spanish home footer links its categories to the English category pages and links About through the 301. This is the front page, which falls under the hard rule.
- The English About page quote reads "All women are seen. Yet your presence is remembered." The official tagline is "Every woman is seen. But your presence is remembered."
- The English About page also contains "less, when done perfectly", and "perfect" is forbidden.
