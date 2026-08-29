# tools/

External tooling used to produce LUNACI brand assets. Unlike `scripts/`
(small helpers written for this repo), everything here is a third-party
project pulled in as a git submodule and used as-is.

## frame-interpolation

[google-research/frame-interpolation](https://github.com/google-research/frame-interpolation)
(FILM: Frame Interpolation for Large Motion) turns two near-duplicate photos
into smooth in-between frames, which can then be assembled into a slow-motion
video clip. Used for turning product/lifestyle stills into short video
content for ads and social.

It's wired in as a git submodule, so this repo tracks which upstream commit
we use without carrying its (large, frequently-updated) code or model
weights in our own history.

### First-time clone

```bash
git submodule update --init --recursive
```

### Environment setup

The upstream project pins `tensorflow==2.6.2`, which only publishes wheels
for **Python 3.6–3.9** — newer Python (this repo's dev container ships 3.10
and 3.11) cannot install it. Two supported options:

**Option A — Docker (recommended, matches upstream exactly):**

```bash
docker pull gcr.io/deeplearning-platform-release/tf2-gpu.2-6:latest
docker run --rm -it -v "$(pwd)/tools/frame-interpolation:/workspace" \
  -w /workspace gcr.io/deeplearning-platform-release/tf2-gpu.2-6:latest bash
pip3 install -r requirements.txt
apt-get update && apt-get install -y ffmpeg
```

**Option B — native Python 3.9 virtualenv:**

```bash
cd tools/frame-interpolation
python3.9 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
sudo apt-get install -y ffmpeg   # or `brew install ffmpeg` on macOS
```

GPU (NVIDIA CUDA 11.2.1 + cuDNN 8.1.0) is optional but strongly recommended —
inference runs on CPU too, just much slower.

### Pretrained models

Weights aren't bundled with the source. Download the TF2 SavedModels from the
[Google Drive folder linked in the upstream README](https://github.com/google-research/frame-interpolation#pre-trained-models)
into a directory *outside* this repo (e.g. `tools/frame-interpolation-models/`,
already git-ignored), keeping the `film_net/{L1,Style,VGG}` structure.

### Generating a brand video clip

```bash
cd tools/frame-interpolation
python3 -m eval.interpolator_cli \
  --pattern "path/to/folder-with-two-or-more-stills" \
  --model_path ../frame-interpolation-models/film_net/Style/saved_model \
  --times_to_interpolate 6 \
  --output_video
```

This writes the interpolated frames and an `interpolated.mp4` into the input
folder. Use the `Style` model for the most natural-looking motion for brand
photography; see the upstream README for `L1`/`VGG` trade-offs and
high-resolution `--block_height`/`--block_width` options for large source
images.

### Updating the submodule

```bash
cd tools/frame-interpolation
git fetch origin
git checkout origin/main   # or a specific pinned commit
cd ../..
git add tools/frame-interpolation
git commit -m "Bump frame-interpolation submodule"
```
