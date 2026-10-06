# The Agency — specialist agents

282 Claude Code subagents from [msitarzewski/agency-agents](https://github.com/msitarzewski/agency-agents) (MIT, see `LICENSE`), installed in `.claude/agents/`.
Pinned upstream commit: see `UPSTREAM_COMMIT`.

- **Cloud sessions & local clones:** loaded automatically from `.claude/agents/` — nothing to install.
- **Use:** "Use the Brand Guardian agent to review this copy."
- **Update:** `.claude/agency-agents/sync.sh`, then review and commit the diff.
- **All projects on a personal computer (optional):** clone the upstream repo and run `./scripts/install.sh --tool claude-code` (installs to `~/.claude/agents/`).

Project rules in `CLAUDE.md` (if present) always take precedence over any agent's default behaviour.
