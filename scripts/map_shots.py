#!/usr/bin/env python3
"""Map screenshot filenames to (squad_id, map_no) and report coverage.

Run this first. It tells you which of the 124 files correspond to which rows,
and flags any file that does not map cleanly, before you start reading images.

    python scripts/map_shots.py "/path/to/For Slight Analysis"
"""
import csv, os, re, sys
from collections import defaultdict

REPORTER = {
    "Zinc":"T01","Turbs":"T02","Inctz":"T03","OG":"T04","Minho":"T05","Kaiba":"T06",
    "z":"T07","Aggy":"T08","Drizzy":"T09","ONFLEEK":"T10","ON FLEEK":"T10","Jatzy":"T11",
    "Shan0":"T12","Delayed":"T13","Lezza":"T14","Humz":"T15","RedShftr":"T16","Termi":"T16",
}
ROOT = os.path.join(os.path.dirname(__file__), "..")
TDIR = f"{ROOT}/data/tournaments/2026-07-20"


def main(shots):
    mres = {(r["map_no"], r["squad_id"]): r
            for r in csv.DictReader(open(f"{TDIR}/map_results.csv"))}
    found, unmapped = {}, []
    for fn in sorted(os.listdir(shots)):
        m = re.match(r"(.+) Game (\d+)\.(png|jpg|jpeg)$", fn, re.I)
        if not m or m.group(1) not in REPORTER:
            unmapped.append(fn); continue
        sq, mp = REPORTER[m.group(1)], m.group(2)
        found[(mp, sq)] = fn

    print(f"{len(found)} files mapped, {len(unmapped)} unmapped\n")
    for fn in unmapped:
        print(f"  UNMAPPED  {fn}")

    print("\nCOVERAGE  (row exists in map_results.csv?)")
    problems = 0
    for (mp, sq), fn in sorted(found.items(), key=lambda x: (x[0][1], int(x[0][0]))):
        r = mres.get((mp, sq))
        if r is None:
            print(f"  NO ROW    M{mp} {sq}  <- {fn}"); problems += 1
        elif r["confidence"] == "W":
            print(f"  WITHDRAWN M{mp} {sq}  <- {fn}   screenshot exists for a map marked "
                  f"as withdrawn - the boundary is wrong"); problems += 1

    print("\nROWS WITH NO SCREENSHOT (fine - just no damage available)")
    for (mp, sq), r in sorted(mres.items(), key=lambda x: (x[0][1], int(x[0][0]))):
        if r["confidence"] != "W" and (mp, sq) not in found:
            print(f"  M{mp} {sq}")

    print(f"\n{problems} problem(s) to resolve before extracting")
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1] if len(sys.argv) > 1 else "For Slight Analysis"))
