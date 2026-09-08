# Claude SEO — manual install notes

Source: https://github.com/AgriciDaniel/claude-seo, pinned to the `v2.2.5`
release (same tag `install.sh` pins to). Vendored manually because
`/plugin marketplace add` / `/plugin install` are not available in this
remote Claude Code environment — see `docs/troubleshooting` upstream for the
native install path on a machine that does support `/plugin`.

## What's here

- `.claude/skills/seo*` (25 symlinks) → `.agents/skills/seo*` — the skill
  content Claude Code auto-discovers and triggers on (audit, schema,
  Core Web Vitals, GEO, local SEO, etc.)
- `.claude/agents/seo-*.md` (18 files) — the specialist subagents the `seo`
  and `seo-audit` skills delegate to
- `.agents/skills/seo/{bin,scripts,schema,data}` — the Python runtime the
  skills shell out to via `claude-seo run <script>.py ...`
- `skills-lock.json` — records each skill's upstream source and a sha256 of
  its `SKILL.md` for drift tracking (mirrors the existing `usestrix/strix`
  entries already in this file)

Not vendored: `extensions/` (DataForSEO/Firecrawl/Ahrefs — optional,
each needs its own API key), and `hooks/` (enforcement only works through a
real plugin install; upstream's own installer calls manual hook copying
"best-effort" for the same reason).

## One-time setup

The bundled scripts need an isolated Python 3.10+ venv before first use:

```bash
bash .agents/skills/seo/bin/claude-seo setup
```

This creates its own venv and installs `.agents/skills/seo/requirements.txt`
(playwright, lxml, matplotlib, weasyprint, Google API clients, etc.) — it
does not touch any repo-level Python environment. Re-run after bumping the
pinned version.

## Running a script

Every `SKILL.md` in this plugin references commands like
`claude-seo run render_page.py <url> --json`, assuming `claude-seo` is on
`PATH`. It isn't here, so call it by full path instead:

```bash
.agents/skills/seo/bin/claude-seo run sitemap_discovery.py https://lunaci.com --json
```

## Known gap vs. a native `/plugin install`

- `/seo audit <url>`-style **slash commands** aren't available — invoke the
  work by describing the task ("run a full SEO audit of lunaci.com") and
  Claude will trigger the matching skill, or load one explicitly (e.g. the
  `seo-audit` skill).
- Hook-based enforcement (auto-running checks) isn't wired up.

## Updating

Re-clone the tag you want, diff `skills/`, `agents/`, and `scripts/` against
what's vendored here, and recompute hashes for `skills-lock.json`:

```bash
git clone --depth 1 --branch vX.Y.Z https://github.com/AgriciDaniel/claude-seo /tmp/claude-seo
```
