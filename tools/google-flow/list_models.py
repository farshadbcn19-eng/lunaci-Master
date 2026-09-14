"""Diagnostic: lists the Gemini API models this key can access.

Run this when video generation returns 404 for a given model name — it
shows which models (including any veo-*) are actually enabled for this
key/project, without ever printing the key itself.
"""
import os

import requests

API_KEY_ENV = "GOOGLE_FLOW_API_KEY"
BASE_URL_ENV = "GOOGLE_FLOW_API_BASE_URL"


def main():
    api_key = os.environ[API_KEY_ENV]
    base_url = os.environ[BASE_URL_ENV].rstrip("/")

    response = requests.get(
        f"{base_url}/v1beta/models",
        headers={"x-goog-api-key": api_key},
        params={"pageSize": 200},
        timeout=30,
    )
    response.raise_for_status()
    models = response.json().get("models", [])

    print(f"{len(models)} models visible to this key:\n")
    for model in models:
        name = model.get("name", "")
        methods = model.get("supportedGenerationMethods", [])
        flag = "  <-- video/veo" if "veo" in name.lower() or "predictLongRunning" in methods else ""
        print(f"{name}  {methods}{flag}")


if __name__ == "__main__":
    main()
