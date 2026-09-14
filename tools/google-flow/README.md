# Google Flow (Veo) video generation

Generates LUNASI product/brand videos with Veo, the model behind Google
Flow, through the Gemini API. Runs as a GitHub Actions workflow so the
API key never touches this repository or the chat history — it lives
only as a GitHub Actions secret.

Docs: https://ai.google.dev/gemini-api/docs/video

## One-time setup

1. Get a Gemini API key from Google AI Studio: https://aistudio.google.com/api-keys
2. In the repo on GitHub: **Settings → Secrets and variables → Actions →
   New repository secret**, add:
   - `GOOGLE_FLOW_API_KEY` — the API key from step 1
   - `GOOGLE_FLOW_API_BASE_URL` — `https://generativelanguage.googleapis.com`
3. That's it — no key is ever pasted into code or committed.

## Running it

GitHub → **Actions** tab → **Generate Google Flow video** → **Run
workflow** → enter a prompt → run. Download the result from the run's
**Artifacts** section when it finishes (Veo generation typically takes
a few minutes).

## Local testing (optional)

```bash
cd tools/google-flow
cp .env.example .env   # fill in your key locally — .env is git-ignored
pip install -r requirements.txt
set -a && source .env && set +a
python generate_video.py "a 6-second close-up of LUNASI lip gloss catching light"
```

## Configuration

| Variable | Purpose | Default |
|---|---|---|
| `GOOGLE_FLOW_API_KEY` | Gemini API key (required) | — |
| `GOOGLE_FLOW_API_BASE_URL` | Gemini API base URL (required) | — |
| `GOOGLE_FLOW_MODEL` | Veo model to call | `veo-3.1-generate-preview` (also available: `veo-3.1-fast-generate-preview`, `veo-3.1-lite-generate-preview`) |

If the Gemini API's request/response shape changes, adjust
`GoogleFlowClient` in `generate_video.py` against the docs link above.
