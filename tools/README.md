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

**Status: verified working (Aug 2026).** Both the single mid-frame path
(`eval.interpolator_test`) and the full video path
(`eval.interpolator_cli --output_video`) were run end-to-end against the
pinned submodule commit and produced correct, artifact-free output - see the
exact working dependency versions below (the plain `pip install -r
requirements.txt` in upstream's own instructions no longer resolves cleanly
on today's PyPI).

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

**A plain `pip install -r requirements.txt` will NOT work as of 2026** — the
2021-era pins conflict with what PyPI resolves for their unpinned
sub-dependencies today (numpy/scipy/matplotlib pulled too new for
`tensorflow==2.6.2`; a modern `protobuf` breaks TF's generated `_pb2.py`
files; a modern `typing_extensions`/`beautifulsoup4`/`ipython` chain breaks
`gdown`; `apache-beam` needs `pkg_resources`, which pip's build-isolation
sandbox won't have unless you pass `--no-build-isolation`). This was verified
end-to-end (Style-model mid-frame interpolation, then a full
`interpolator_cli` run with `--output_video`, against upstream commit
`69f8708`) with this exact, working sequence instead:

```bash
python3.9 -m venv .venv && source .venv/bin/activate
pip install --upgrade pip
pip install tensorflow==2.6.2
pip install absl-py==0.12.0 gin-config==0.5.0 mediapy==1.0.3 \
  scikit-image==0.19.1 natsort==8.1.0 gdown==4.5.4
pip install "numpy~=1.19.2" "typing-extensions~=3.7.4" "protobuf==3.19.6"
pip install "ipython<8" "exceptiongroup==1.1.3"
pip install "matplotlib==3.4.3" "scipy==1.7.3" "PyWavelets==1.1.1" \
  "networkx==2.6.3" "tifffile==2021.11.2" "imageio==2.9.0"
pip install "beautifulsoup4==4.11.1" "soupsieve==2.3.2"
pip install "apache-beam==2.34.0" --no-build-isolation   # only needed for
  # eval.interpolator_cli (the --output_video path) - eval.interpolator_test
  # (single mid-frame) works without it
sudo apt-get install -y ffmpeg
```

**Performance reality check:** with no GPU, one interpolation step took
~16-30s on a 1024×768 pair in this environment. `--times_to_interpolate 6`
(the CLI default in the example below) generates 2^6+1 = 65 frames per
directory - budget on the order of 15-30 minutes per clip on CPU. A GPU
(Option A's Docker image, or any CUDA 11.2 box) is what makes this practical
for repeated use; CPU is fine for a one-off test.

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
