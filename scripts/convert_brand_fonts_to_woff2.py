"""Convert LUNACI brand OTF fonts to WOFF2 and assemble the remote deploy script.

Run inside the deploy-brand-fonts-woff2.yml GitHub Actions job, after checkout,
with fonttools + brotli installed. Reads Helvetica.otf and
TradeGothicLTStd-Extended.otf from the repo root, verifies each font's name
table before converting, then writes remote_deploy.sh (SSH payload for the
next step) to the workspace root.
"""

import base64
import os
import sys

from fontTools.ttLib import TTFont

BUILD_DIR = "build"
os.makedirs(BUILD_DIR, exist_ok=True)

CONVERSIONS = [
    {
        "src": "Helvetica.otf",
        "dst": os.path.join(BUILD_DIR, "Helvetica.woff2"),
        "expect_substr": "helvetica",
    },
    {
        "src": "TradeGothicLTStd-Extended.otf",
        "dst": os.path.join(BUILD_DIR, "TradeGothicLTStd-Extended.woff2"),
        "expect_substr": "trade gothic",
    },
]

abort = False
sizes = {}

for job in CONVERSIONS:
    print("=== Converting " + job["src"] + " -> " + job["dst"] + " ===")
    font = TTFont(job["src"])
    name1 = font["name"].getDebugName(1) or ""
    name4 = font["name"].getDebugName(4) or ""
    print("  name table getDebugName(1) [Font Family]: " + name1)
    print("  name table getDebugName(4) [Full Name]:   " + name4)

    ok1 = job["expect_substr"] in name1.lower()
    ok4 = job["expect_substr"] in name4.lower()
    print("  name(1) contains '" + job["expect_substr"] + "': " + ("YES" if ok1 else "NO"))
    print("  name(4) contains '" + job["expect_substr"] + "': " + ("YES" if ok4 else "NO"))

    if not ok1 or not ok4:
        print("ABORT: name table verification failed for " + job["src"])
        abort = True
        continue

    font.flavor = "woff2"
    font.save(job["dst"])
    size = os.path.getsize(job["dst"])
    sizes[job["dst"]] = size
    print(
        "  OK: saved "
        + job["dst"]
        + " ("
        + str(size)
        + " bytes, source OTF was "
        + str(os.path.getsize(job["src"]))
        + " bytes)"
    )
    print("")

if abort:
    print("ABORT: one or more fonts failed name-table verification - aborting before any deployment, no files written to server")
    sys.exit(1)

helv_path = os.path.join(BUILD_DIR, "Helvetica.woff2")
trade_path = os.path.join(BUILD_DIR, "TradeGothicLTStd-Extended.woff2")

with open(helv_path, "rb") as fh:
    helv_b64 = base64.b64encode(fh.read()).decode("ascii")
with open(trade_path, "rb") as fh:
    trade_b64 = base64.b64encode(fh.read()).decode("ascii")

helv_size = sizes[helv_path]
trade_size = sizes[trade_path]

# Static base64 of scripts/create-brand-fonts-snippet.php (php -l verified).
# Content is fixed regardless of the font conversion output, so it is
# generated once and embedded here rather than re-read at Action runtime.
with open(os.path.join("scripts", "create-brand-fonts-snippet.php"), "rb") as fh:
    snippet_b64 = base64.b64encode(fh.read()).decode("ascii")

with open(os.path.join("scripts", "remote_deploy.sh.template"), "r") as fh:
    remote_template = fh.read()

remote_script = (
    remote_template.replace("__HELV_B64__", helv_b64)
    .replace("__TRADE_B64__", trade_b64)
    .replace("__HELV_SIZE__", str(helv_size))
    .replace("__TRADE_SIZE__", str(trade_size))
    .replace("__SNIPPET_B64__", snippet_b64)
)

with open("remote_deploy.sh", "w") as fh:
    fh.write(remote_script)

print("OK: conversion complete, name-table verification passed for both fonts")
print("Helvetica.woff2 size: " + str(helv_size) + " bytes")
print("TradeGothicLTStd-Extended.woff2 size: " + str(trade_size) + " bytes")
print("remote_deploy.sh assembled and ready for SSH deployment step")
