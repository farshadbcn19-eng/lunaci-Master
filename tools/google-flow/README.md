# Google Flow video generation

Generates LUNASI product/brand videos through the Google Flow API. Runs
as a GitHub Actions workflow so the API key never touches this
repository or the chat history — it lives only as a GitHub Actions
secret.

## One-time setup

1. In the repo on GitHub: **Settings → Secrets and variables → Actions →
   New repository secret**, add:
   - `GOOGLE_FLOW_API_KEY` — your Google Flow API key
   - `GOOGLE_FLOW_API_BASE_URL` — the API's base URL (from Google Flow's
     API reference)
2. That's it — no key is ever pasted into code or committed.

## Running it

GitHub → **Actions** tab → **Generate Google Flow video** → **Run
workflow** → enter a prompt → run. Download the result from the run's
**Artifacts** section when it finishes.

## Local testing (optional)

```bash
cd tools/google-flow
cp .env.example .env   # fill in your key locally — .env is git-ignored
pip install -r requirements.txt
set -a && source .env && set +a
python generate_video.py "a 6-second close-up of LUNASI lip gloss catching light"
```

## Note on the API contract

`generate_video.py` currently assumes bearer-token auth and a single
`POST {base_url}/generate` endpoint returning JSON. That's a
placeholder — confirm the real request/response shape against Google
Flow's API reference and adjust `GoogleFlowClient` in
`generate_video.py` accordingly (auth header, endpoint path, payload
fields, and how the generated video is retrieved once rendering
completes).
