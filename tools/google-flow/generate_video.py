"""Generates LUNASI videos with Veo through the Gemini API.

The key from Google AI Studio (GOOGLE_FLOW_API_KEY) is a Gemini API key —
Veo, the model behind Google Flow, is reachable through that same API.
Nothing here is committed: the key and base URL come from GitHub Actions
secrets (or a local, git-ignored .env for testing).
"""
import argparse
import os
import time
from pathlib import Path

import requests

API_KEY_ENV = "GOOGLE_FLOW_API_KEY"
BASE_URL_ENV = "GOOGLE_FLOW_API_BASE_URL"
MODEL_ENV = "GOOGLE_FLOW_MODEL"
DEFAULT_MODEL = "veo-3.1-generate-preview"
POLL_SECONDS = 10
MAX_POLLS = 60


class GoogleFlowClient:
    def __init__(self):
        api_key = os.environ.get(API_KEY_ENV)
        if not api_key:
            raise RuntimeError(f"{API_KEY_ENV} is not set")
        base_url = os.environ.get(BASE_URL_ENV)
        if not base_url:
            raise RuntimeError(f"{BASE_URL_ENV} is not set")

        self.base_url = base_url.rstrip("/")
        self.model = os.environ.get(MODEL_ENV, DEFAULT_MODEL)
        self.session = requests.Session()
        self.session.headers.update({
            "x-goog-api-key": api_key,
            "Content-Type": "application/json",
        })

    def generate_video(self, prompt: str, aspect_ratio: str = "16:9") -> bytes:
        start = self.session.post(
            f"{self.base_url}/v1beta/models/{self.model}:predictLongRunning",
            json={
                "instances": [{"prompt": prompt}],
                "parameters": {"aspectRatio": aspect_ratio},
            },
            timeout=60,
        )
        if not start.ok:
            raise RuntimeError(f"{start.status_code} error starting generation: {start.text}")
        operation_name = start.json()["name"]

        for _ in range(MAX_POLLS):
            time.sleep(POLL_SECONDS)
            poll = self.session.get(f"{self.base_url}/v1beta/{operation_name}", timeout=30)
            if not poll.ok:
                raise RuntimeError(f"{poll.status_code} error polling operation: {poll.text}")
            status = poll.json()
            if status.get("done"):
                if "error" in status:
                    raise RuntimeError(f"Video generation failed: {status['error']}")
                samples = status["response"]["generateVideoResponse"]["generatedSamples"]
                video_uri = samples[0]["video"]["uri"]
                video = self.session.get(video_uri, timeout=120)
                video.raise_for_status()
                return video.content

        raise TimeoutError(f"Video generation did not finish after {MAX_POLLS * POLL_SECONDS}s")


def main():
    parser = argparse.ArgumentParser(description="Generate a LUNASI video with Veo via the Gemini API")
    parser.add_argument("prompt", help="Text prompt describing the video to generate")
    parser.add_argument("--aspect-ratio", default="16:9")
    parser.add_argument("--out", default="output/video.mp4")
    args = parser.parse_args()

    client = GoogleFlowClient()
    video_bytes = client.generate_video(args.prompt, aspect_ratio=args.aspect_ratio)

    out_path = Path(args.out)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_bytes(video_bytes)
    print(f"Saved video to {out_path}")


if __name__ == "__main__":
    main()
