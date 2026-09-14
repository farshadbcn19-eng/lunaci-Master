"""Client for the Google Flow video-generation API.

All credentials and endpoint configuration come from environment
variables — nothing here is ever committed. Set GOOGLE_FLOW_API_KEY and
GOOGLE_FLOW_API_BASE_URL as GitHub Actions secrets (or in a local,
git-ignored .env for testing).

The auth header, endpoint path, and payload/response shape below are
placeholders until confirmed against the real Google Flow API reference —
adjust GoogleFlowClient once that's available.
"""
import argparse
import json
import os
from pathlib import Path

import requests

API_KEY_ENV = "GOOGLE_FLOW_API_KEY"
BASE_URL_ENV = "GOOGLE_FLOW_API_BASE_URL"
ENDPOINT_ENV = "GOOGLE_FLOW_GENERATE_ENDPOINT"


class GoogleFlowClient:
    def __init__(self):
        api_key = os.environ.get(API_KEY_ENV)
        if not api_key:
            raise RuntimeError(f"{API_KEY_ENV} is not set")
        base_url = os.environ.get(BASE_URL_ENV)
        if not base_url:
            raise RuntimeError(f"{BASE_URL_ENV} is not set")

        self.base_url = base_url.rstrip("/")
        self.endpoint = os.environ.get(ENDPOINT_ENV, "/generate")
        self.session = requests.Session()
        self.session.headers.update({
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        })

    def generate_video(self, prompt: str, **params) -> dict:
        payload = {"prompt": prompt, **params}
        response = self.session.post(f"{self.base_url}{self.endpoint}", json=payload, timeout=120)
        response.raise_for_status()
        return response.json()


def main():
    parser = argparse.ArgumentParser(description="Generate a video via the Google Flow API")
    parser.add_argument("prompt", help="Text prompt describing the video to generate")
    parser.add_argument("--out", default="output/result.json", help="Where to save the raw API response")
    args = parser.parse_args()

    client = GoogleFlowClient()
    result = client.generate_video(args.prompt)

    out_path = Path(args.out)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(result, indent=2))
    print(f"Saved response to {out_path}")


if __name__ == "__main__":
    main()
