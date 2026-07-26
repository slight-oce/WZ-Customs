#!/usr/bin/env python3
"""Integrity checks for the customs dataset.

Every check here exists because something actually went wrong once.

The important one is CHECK 2. Player damage figures were transcribed from
screenshots and 15 of them were wrong by an average of 26%. They survived
because the only thing they were checked against was a total read off the
same image at the same time. Recording SQUAD TOTALS as its own field, entered
separately, turns that into a real checksum: if a player row is misread, the
sum stops matching and the build fails.

Exit code 1 on any error. Warnings do not fail the build.
"""
import csv, sys, os
from collections import defaultdict

ROOT = os.path.join(os.path.dirname(__file__), "..", "data")
errors, warnings = [], []


def read(path):
    with open(path, newline="") as f:
        return list(csv.DictReader(f))


def num(v):
    return int(v) if str(v).strip() not in ("", "None") else None


players = {r["player_id"]: r for r in read(f"{ROOT}/players.csv")}
aliases = read(f"{ROOT}/aliases.csv")
ranks = read(f"{ROOT}/ranks.csv")

tdir = f"{ROOT}/tournaments"
for tname in sorted(os.listdir(tdir)):
    d = f"{tdir}/{tname}"
    if not os.path.isdir(d):
        continue
    meta = read(f"{d}/meta.csv")[0]
    squads = read(f"{d}/squads.csv")
    mres = read(f"{d}/map_results.csv")
    pmap = read(f"{d}/player_map.csv")
    scoring = read(f"{d}/scoring.csv")
    E = lambda m: errors.append(f"[{tname}] {m}")
    W = lambda m: warnings.append(f"[{tname}] {m}")

    roster = defaultdict(set)
    for s in squads:
        roster[s["squad_id"]].add(s["player_id"])
        if s["player_id"] not in players:
            E(f"squads.csv references unknown player_id '{s['player_id']}'")

    # ---- CHECK 1: player kills must sum to the squad total ----------------
    agg = defaultdict(lambda: defaultdict(int))
    have = defaultdict(lambda: defaultdict(bool))
    for r in pmap:
        key = (r["map_no"], r["squad_id"])
        for col in ("kills", "assists", "redeploys", "damage"):
            v = num(r[col])
            if v is None:
                continue
            agg[key][col] += v
            have[key][col] = True
        if r["player_id"] not in players:
            E(f"player_map.csv references unknown player_id '{r['player_id']}'")
        if r["player_id"] not in roster[r["squad_id"]]:
            E(f"M{r['map_no']} {r['squad_id']}: {r['player_id']} is not on that squad")

    for r in mres:
        key = (r["map_no"], r["squad_id"])
        # CHECK 1 - kills
        st = num(r["squad_kills"])
        if st is not None and have[key]["kills"] and agg[key]["kills"] != st:
            E(f"M{r['map_no']} {r['squad_id']}: player kills sum to "
              f"{agg[key]['kills']}, squad_kills says {st}")
        # ---- CHECK 2: the one that would have caught the transcription bug
        for col, tot in (("damage", "squad_damage"), ("assists", "squad_assists"),
                         ("redeploys", "squad_redeploys")):
            st = num(r[tot])
            if st is None or not have[key][col]:
                continue
            if agg[key][col] != st:
                E(f"M{r['map_no']} {r['squad_id']}: player {col} sum to "
                  f"{agg[key][col]}, {tot} says {st} — one of them is misread")
        # every squad on a map needs a full roster of rows
        n = sum(1 for x in pmap if x["map_no"] == r["map_no"]
                and x["squad_id"] == r["squad_id"])
        if n != len(roster[r["squad_id"]]) and r["confidence"] != "DNP":
            W(f"M{r['map_no']} {r['squad_id']}: {n} player rows, roster has "
              f"{len(roster[r['squad_id']])}")

    # ---- CHECK 3: placements unique and complete within each map ----------
    bymap = defaultdict(list)
    for r in mres:
        p = num(r["placement"])
        if p is not None:
            bymap[r["map_no"]].append(p)
    nsq = len(roster)
    for m, ps in sorted(bymap.items()):
        dupes = {p for p in ps if ps.count(p) > 1}
        if dupes:
            E(f"M{m}: placement(s) {sorted(dupes)} claimed by more than one squad")
        missing = sorted(set(range(1, nsq + 1)) - set(ps))
        if missing:
            W(f"M{m}: no squad recorded at placement {missing}")

    # ---- CHECK 4: a rank must exist as at the tournament date -------------
    played = {r["player_id"] for r in pmap}
    for p in sorted(played):
        eff = [r for r in ranks if r["player_id"] == p
               and r["effective_on"] <= meta["played_on"]]
        if not eff:
            W(f"{p} played but has no rank effective on or before "
              f"{meta['played_on']} — cannot be compared to a band")

    # ---- CHECK 5: scoring table covers every placement --------------------
    for m, ps in bymap.items():
        for p in ps:
            if not any(int(s["place_from"]) <= p <= int(s["place_to"]) for s in scoring):
                E(f"M{m}: placement {p} is not covered by scoring.csv")

    # ---- CHECK 6: anything not read off a real scoreboard is declared -----
    for r in pmap:
        if r["confidence"] not in ("C", "K", "T", "U", "I", "L", "DNP"):
            E(f"player_map.csv: unknown confidence code '{r['confidence']}'")

# ---- CHECK 7: the public build must not leak private material ------------
pub = os.path.join(os.path.dirname(__file__), "..", "docs", "data.json")
if os.path.exists(pub):
    import json
    blob = open(pub).read()
    j = json.loads(blob)
    for t in j.get("tournaments", []):
        for d in t.get("decisions", []):
            if d.get("visibility") != "public":
                errors.append(f"docs/data.json exposes a non-public decision for "
                              f"{d.get('player_id')}")
            if d.get("direction") == "demote":
                errors.append(f"docs/data.json exposes a demotion for {d.get('player_id')}")
        for p in t.get("players", []):
            if "res" in p:
                errors.append("docs/data.json exposes the band residual - that is the "
                              "ranking-order field and is admin only")
                break
    for pid, row in players.items():
        if row.get("discord_id") and row["discord_id"] in blob:
            errors.append(f"docs/data.json contains a discord_id for {pid}")

seen = defaultdict(list)
for a in aliases:
    seen[a["in_game_name"]].append(a["player_id"])
for n, ids in seen.items():
    if len(ids) > 1:
        errors.append(f"aliases.csv: '{n}' maps to more than one player: {ids}")

for w in warnings:
    print(f"WARN  {w}")
for e in errors:
    print(f"ERROR {e}")
print(f"\n{len(errors)} error(s), {len(warnings)} warning(s)")
sys.exit(1 if errors else 0)
