# CLAUDE.md — LUNACI Barcelona (lunaci-master)

This repository is dedicated **exclusively** to LUNACI Barcelona (luxury cosmetics, `lunacibarcelona.com`). Do not touch, reference, or copy anything from the separate `farshadbcn19-eng/bronci-website` repository — that belongs to an unrelated oil & gas trading business and must remain fully isolated. If a file that seems missing here might exist in `bronci-website` (e.g. deployment automation), stop and ask the user before pulling anything across repositories.

## Brand governance — read first

The single source of truth for brand identity, voice, and approved language lives in [`brand-docs/`](brand-docs/README.md). Before writing or editing any copy, prompt, image brief, or public-facing content, check that folder — in particular:

- **`LUNACI_VAQAR_Locked_Definition.md`** — the Locked Brand Definition of **VAQAR™**, the single defining value behind every LUNACI decision. It is a Protected Semantic Brand Asset: no campaign, ad, packaging, website copy, AI prompt, or public communication may redefine, weaken, or contradict it without formal Brand Governance approval. Every other brand document must stay consistent with this file; where they conflict, this file governs.
- `LUNACI_LDO000_Pocket_Guide.md` — quick-start brand guide (read in 15 minutes).
- `LUNACI_LDO001_Brand_Approval_Checklist.md` — checklist to run before shipping content.
- `LUNACI_LMB-001_Chapter1_Master.md`, `LUNACI_LMB-002_Chapter2_Master.md`, `LUNACI_LMB-003_BOS_Master.md` — the full Brand Master Book.
- `LUNACI_Appendix_B-C_Brand_Constants_Language.md` — approved and forbidden language, with worked examples.
- `LUNACI_LUNA001_Character_Bible_V4.2_Cinematography.docx` — LUNA (the Brand Muse) visual production rules.

## Brand identity — executive summary

- Single core value: **Vaqar** (quiet dignity). Every design and copy decision must derive from this one value — see the Locked Definition above.
- Guiding metaphor: Cinderella was never transformed — she was always valuable. LUNACI reveals, it does not create.
- Archetype: Sage (unsought leader) + Creator (primary driver) = 70% of the structure; Lover = only 30%, expressed solely through light/texture/tone, never as a standalone pillar.
- Official tagline: "Every woman is seen. But your presence is remembered."

### Brand voice — 4 mandatory pillars

- **Quiet Confidence** — no shouting, no multiple exclamation marks, no absolute claims ("best", "revolutionary").
- **Direct Warmth** — human, not salesy. Never "Darling", "Babe", "Gorgeous".
- **Restraint as Luxury** — one precise sentence instead of three decorative ones.
- **Respectful Directness** — never imply the audience is incomplete. Forbidden words: "flawless", "fix", "hide your flaws", "perfect".

### Visual identity

- Background: `#0B0B0B` (warm black, not absolute black). Gold: `#D4AF37` — used sparingly, never as a fill color.
- Typography: Trade Gothic LT Std Extended (headings) + Helvetica (body).
- Photography: natural light, informal composition, minimal studio background. Never ornate decoration or formal glamour styling.
- Photo editing rule: only lighting/background may be altered; product color, shape, and packaging label must never be manipulated.

## Platform & technical constraints

- Platform: WordPress + Elementor **Free** (no paid plugins) + WooCommerce active. Hosting: Hostinger.
- For any complex section, use a single HTML Widget — not block-by-block native Elementor building. The homepage (Page ID 57) is one custom HTML Widget (`id=9b0a463`).
- Custom CSS only via the WPCode Snippet Manager plugin, snippet type CSS (not HTML).
- After every new image upload, cache-bust with `?v=2` (incrementing) on the image URL.
- Code delivery convention: provide HTML in sequential parts ready to paste into the Elementor HTML widget — full replacement, not partial edits, unless the task says otherwise.
- SSH/WP-CLI automation runs via GitHub Actions workflows (`diagnose-*` / `fix-*` / `query-*.yml`) using `sshpass` and Hostinger SSH secrets.
- **Hard rule:** homepage copy/text must never change without the user's explicit instruction — only image placement and section sizing/dimensions may change unless told otherwise.

## Products (15 confirmed SKUs)

- Lips: Lip Pencil, Lipstick Matt Vegan, Lip Gloss Matt Fix, Lip Velvet.
- Eyes: Eyebrow Pencil, Eye Pencil, Eyeliner Liquid, Mascara Volume, Mascara Length, Shadow.
- Face: Concealer, Foundation, Compact Powder, Blusher.
- Nails: Nail Polish Long Lasting.

No other product categories (skincare, fragrance) currently exist — do not display or imply them on the site.

## Working rules

- Before any change, inspect the current state of the page and document the real error — never guess.
- Never write product copy or captions without using the approved vocabulary in `brand-docs/LUNACI_Appendix_B-C_Brand_Constants_Language.md`.
- After every change, give a precise summary of what changed and why.
