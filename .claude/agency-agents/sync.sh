#!/usr/bin/env bash
# Re-sync The Agency agents (https://github.com/msitarzewski/agency-agents)
# into this repo's .claude/agents/. Run from anywhere: .claude/agency-agents/sync.sh
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET="$HERE/../agents"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
git clone --depth 1 https://github.com/msitarzewski/agency-agents.git "$TMP/agency-agents"
(cd "$TMP/agency-agents" && ./scripts/install.sh --tool claude-code --path "$TARGET")
git -C "$TMP/agency-agents" rev-parse HEAD > "$HERE/UPSTREAM_COMMIT"
cp "$TMP/agency-agents/LICENSE" "$HERE/LICENSE"
echo "Synced to $(cat "$HERE/UPSTREAM_COMMIT"). Review with: git status .claude/"
