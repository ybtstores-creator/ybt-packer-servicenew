#!/usr/bin/env python3
"""
Extract the YBT signatory's signature (blue ink on cardboard photo) into a
transparent PNG for embedding in generated letters.

Approach: the ink is much darker and bluer than the brown cardboard, so we
compute a "blueness" score (B - (R+G)/2), soft-threshold it into an alpha
map, normalise the result, and recolour to a deep ink blue.

Usage:
    python tools/extract_signature.py            # writes assets/signature.png
"""
from __future__ import annotations

import os

import numpy as np
from PIL import Image, ImageFilter

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "downloads", "WhatsApp Image 2026-09-18 at 10.29.51 AM.jpeg")
OUT = os.path.join(ROOT, "assets", "signature.png")

# Ink colour for the cleaned signature (deep pen blue-violet).
INK_RGB = (28, 24, 82)

# Crop window around the signature in the source photo (x0, y0, x1, y1).
# Chosen to exclude the dark left band of the photo entirely.
CROP = (330, 260, 500, 580)


def main() -> None:
    im = Image.open(SRC).convert("RGB").crop(CROP)
    a = np.asarray(im).astype(float)
    r, g, b = a[..., 0], a[..., 1], a[..., 2]

    # "Blueness" is the discriminator: ink has B well above the R/G average,
    # while cardboard, its dark band and paper speckles do not (score <= 0).
    score = b - (r + g) / 2.0
    alpha = np.clip(score / 30.0, 0.0, 1.0)
    alpha = np.clip((alpha - 0.05) / (0.75 - 0.05), 0.0, 1.0)

    # Median filter kills isolated speckles; blur smooths stroke edges.
    am = Image.fromarray((alpha * 255).astype(np.uint8), "L")
    am = am.filter(ImageFilter.MedianFilter(3))
    am = am.filter(ImageFilter.GaussianBlur(0.5))
    av = np.asarray(am).astype(float) / 255.0

    # Trim to ink bounding box with padding, normalise peak to 255.
    ys, xs = np.where(alpha > 0.2)
    if len(xs) == 0:
        raise SystemExit("No signature ink found — check the CROP window.")
    pad = 6
    x0, x1 = max(0, xs.min() - pad), min(alpha.shape[1], xs.max() + pad)
    y0, y1 = max(0, ys.min() - pad), min(alpha.shape[0], ys.max() + pad)
    alpha = alpha[y0:y1, x0:x1]
    alpha = alpha / max(alpha.max(), 1e-6)

    # Gentle blur to smooth JPEG-edged strokes, then a contrast curve.
    av = np.clip(av * 1.3 - 0.06, 0.0, 1.0)  # push faint noise to 0, keep strokes solid

    out = np.zeros((*av.shape, 4), dtype=np.uint8)
    out[..., 0], out[..., 1], out[..., 2] = INK_RGB
    out[..., 3] = (av * 255).astype(np.uint8)

    img = Image.fromarray(out, "RGBA")
    # Upscale 3x for smoother print rendering.
    img = img.resize((img.width * 3, img.height * 3), Image.Resampling.LANCZOS)
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    img.save(OUT)
    print(f"Signature extracted: {OUT} ({img.width}x{img.height}px)")


if __name__ == "__main__":
    main()
