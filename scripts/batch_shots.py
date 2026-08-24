#!/usr/bin/env python3
"""Tile scoreboard screenshots into montages so they can be read in bulk.

Reading screenshots one file at a time is the most expensive part of an
extraction. Stacking several into one image costs about what a single
full-size screenshot costs, provided the wasted pixels are cropped away first.

Two crops matter:

  vertical    the table is often only ~20% of a full-screen capture; the rest
              is game world. Tesseract is unreliable at READING the game's
              stylised digits, but it locates the header row and the SQUAD
              TOTALS row well enough to crop around them. Where that fails
              (angled phone photos, mostly) we fall back to a fixed band.

  horizontal  everything left of NAME is player emblems and level badges -
              visually busy and carrying no data.

    python scripts/batch_shots.py shots-dir --out montages

Then read montages/batch_01.png .. batch_NN.png instead of the raw files.
Each tile is labelled with its source filename so readings stay traceable.

Verified against independently transcribed values: montage reads reproduced
all four columns exactly, and the one checksum failure seen in practice was
resolved by re-cropping that single cell at full resolution.
"""
import argparse, os, subprocess, sys, tempfile
from PIL import Image, ImageDraw, ImageOps

HDR = {"KILLS", "ASSISTS", "REDEPLOYS", "DAMAGE", "ELIMINATIONS", "SCORE"}

def _words(im):
    with tempfile.NamedTemporaryFile(suffix=".png", delete=False) as f:
        p = f.name
    im.save(p)
    try:
        out = subprocess.run(["tesseract", p, "stdout", "--psm", "11", "tsv"],
                             capture_output=True, text=True, timeout=60).stdout
    except Exception:
        return []
    finally:
        os.unlink(p)
    w = []
    for line in out.splitlines()[1:]:
        f = line.split("\t")
        if len(f) < 12:
            continue
        try:
            c = float(f[10])
        except ValueError:
            continue
        if c < 25 or not f[11].strip():
            continue
        w.append((int(f[6]), int(f[7]), int(f[8]), int(f[9]), f[11].strip().upper()))
    return w

def locate(im):
    """Return (top, bottom, left) as fractions, or None if the table isn't found."""
    s = ImageOps.autocontrast(im.convert("L").resize((2000, int(im.height * 2000 / im.width))))
    ws = _words(s)
    if not ws:
        return None
    H, W = s.height, s.width
    ys = [y for x, y, w, h, t in ws if t.rstrip(":") in HDR]
    bot = [y + h for x, y, w, h, t in ws if "TOTAL" in t]
    if not ys or not bot or max(bot) <= min(ys):
        return None
    top, bottom = min(ys), max(bot)
    band = (bottom - top) / H
    if not 0.05 < band < 0.9:
        return None
    pad = int((bottom - top) * 0.12)
    names = [x for x, y, w, h, t in ws if t.startswith("NAME")]
    left = (min(names) - int(W * 0.01)) / W if names else 0.0
    return (max(0, top - pad) / H, min(H - 1, bottom + pad) / H, max(0.0, min(left, 0.35)))

def band(im, auto=True):
    if auto:
        loc = locate(im)
        if loc:
            t, b, l = loc
            return im.crop((int(l * im.width), int(t * im.height), im.width, int(b * im.height)))
    if im.height <= im.width * 0.45:
        return im
    return im.crop((0, int(im.height * 0.18), im.width, int(im.height * 0.72)))

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("src")
    ap.add_argument("--out", default="montages")
    ap.add_argument("--per", type=int, default=12, help="max tiles per montage")
    ap.add_argument("--width", type=int, default=1100)
    ap.add_argument("--maxh", type=int, default=2000,
                    help="max montage height in px; tiles pack under this so they "
                         "stay legible after the reader downscales")
    ap.add_argument("--no-auto", action="store_true", help="skip tesseract table location")
    a = ap.parse_args()

    files = []
    for root, _, names in os.walk(a.src):
        for n in sorted(names):
            if n.lower().endswith((".png", ".jpg", ".jpeg", ".webp")):
                files.append(os.path.join(root, n))
    files.sort()
    if not files:
        print(f"no images under {a.src}")
        return 1
    os.makedirs(a.out, exist_ok=True)

    prepped, located = [], 0
    for f in files:
        im = Image.open(f).convert("RGB")
        before = im.height
        im2 = band(im, auto=not a.no_auto)
        if im2.height < before * 0.9:
            located += 1
        im2 = im2.resize((a.width, max(1, int(im2.height * a.width / im2.width))), Image.LANCZOS)
        prepped.append((os.path.relpath(f, a.src), im2))

    groups, cur, h = [], [], 0
    for lab, im in prepped:
        th = im.height + 26
        if cur and (len(cur) >= a.per or h + th > a.maxh):
            groups.append(cur); cur, h = [], 0
        cur.append((lab, im)); h += th
    if cur:
        groups.append(cur)

    for n, tiles in enumerate(groups, 1):
        H = sum(t[1].height + 26 for t in tiles)
        out = Image.new("RGB", (a.width + 16, H), (20, 20, 20))
        d = ImageDraw.Draw(out)
        y = 0
        for lab, im in tiles:
            d.text((8, y + 6), lab, fill=(255, 235, 60))
            out.paste(im, (8, y + 22))
            y += im.height + 26
        p = os.path.join(a.out, f"batch_{n:02d}.png")
        out.save(p)
        print(f"{p}  {len(tiles)} shots  {out.size[0]}x{out.size[1]}")
    print(f"\n{len(files)} screenshots -> {len(groups)} montages "
          f"({located} tables auto-located, {len(files)-located} used the fallback crop)")
    return 0

if __name__ == "__main__":
    sys.exit(main())
