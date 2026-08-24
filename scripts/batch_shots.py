#!/usr/bin/env python3
"""Tile scoreboard screenshots into N-up montages so they can be read in bulk.

Reading 124 screenshots one at a time is the single most expensive part of an
extraction. Six scoreboards stacked into one image are just as legible and cost
roughly what one full-size screenshot costs, because the wasted pixels - the
game world behind the table - get cropped away first.

    python scripts/batch_shots.py shots-v2 --out montages --per 6

Then read montages/batch_01.png .. batch_NN.png instead of the raw files. Each
tile is labelled with its source filename so readings stay traceable.

Verified on the 20 Aug batch: a 6-up montage reproduced all four columns of all
six scoreboards exactly, checked against independently transcribed values.
"""
import argparse, os, sys
from PIL import Image, ImageDraw

def band(im):
    """Crop to the horizontal strip holding the table, if the shot is a full screen."""
    if im.height <= im.width * 0.45:
        return im                      # already a tight strip
    return im.crop((0, int(im.height * 0.18), im.width, int(im.height * 0.72)))

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("src")
    ap.add_argument("--out", default="montages")
    ap.add_argument("--per", type=int, default=6)
    ap.add_argument("--width", type=int, default=1500)
    a = ap.parse_args()
    files = []
    for root, _, names in os.walk(a.src):
        for n in sorted(names):
            if n.lower().endswith((".png", ".jpg", ".jpeg", ".webp")):
                files.append(os.path.join(root, n))
    files.sort()
    if not files:
        print(f"no images under {a.src}"); return 1
    os.makedirs(a.out, exist_ok=True)
    n = 0
    for i in range(0, len(files), a.per):
        chunk = files[i:i + a.per]
        tiles = []
        for f in chunk:
            im = band(Image.open(f).convert("RGB"))
            im = im.resize((a.width, int(im.height * a.width / im.width)), Image.LANCZOS)
            tiles.append((os.path.relpath(f, a.src), im))
        H = sum(t[1].height + 26 for t in tiles)
        out = Image.new("RGB", (a.width + 16, H), (20, 20, 20))
        d = ImageDraw.Draw(out)
        y = 0
        for lab, im in tiles:
            d.text((8, y + 6), lab, fill=(255, 235, 60))
            out.paste(im, (8, y + 22))
            y += im.height + 26
        n += 1
        p = os.path.join(a.out, f"batch_{n:02d}.png")
        out.save(p)
        print(f"{p}  {len(chunk)} shots  {out.size[0]}x{out.size[1]}")
    print(f"\n{len(files)} screenshots -> {n} montages ({a.per} per image)")
    return 0

if __name__ == "__main__":
    sys.exit(main())
