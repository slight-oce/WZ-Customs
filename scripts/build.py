#!/usr/bin/env python3
"""Read the CSVs, compute every derived figure, write docs/data.json.

Nothing downstream stores a calculated number. If a kill count is corrected in
player_map.csv, the residuals, band averages and carry-forward flags all move
with it on the next push.
"""
import csv, json, os, sys, statistics as st
from collections import defaultdict

PUBLIC = "--public" in sys.argv
# Public build: promotions and holds only. Demotions, negative residuals and the
# below-band ordering are withheld - see README. Run without --public for the
# full admin build, which is gitignored and never committed.

ROOT = os.path.join(os.path.dirname(__file__), "..")
D = f"{ROOT}/data"
read = lambda p: list(csv.DictReader(open(p, newline="")))
num = lambda v: int(v) if str(v).strip() not in ("", "None") else None

players = {r["player_id"]: r["handle"] for r in read(f"{D}/players.csv")}
ranks = read(f"{D}/ranks.csv")

def rank_on(pid, date):
    hits = [r for r in ranks if r["player_id"] == pid and r["effective_on"] <= date]
    return int(max(hits, key=lambda r: r["effective_on"])["rank"]) if hits else None

out = {"tournaments": []}
tdir = f"{D}/tournaments"
for tname in sorted(os.listdir(tdir)):
    d = f"{tdir}/{tname}"
    if not os.path.isdir(d):
        continue
    meta = read(f"{d}/meta.csv")[0]
    date = meta["played_on"]
    scoring = read(f"{d}/scoring.csv")
    labels = {r["squad_id"]: r["label"] for r in read(f"{d}/squads.csv")}
    mres = {(r["map_no"], r["squad_id"]): r for r in read(f"{d}/map_results.csv")}
    pmap = read(f"{d}/player_map.csv")
    dec = read(f"{d}/decisions.csv") if os.path.exists(f"{d}/decisions.csv") else []

    def mult(place):
        if place is None:
            return 1.0
        for s in scoring:
            if int(s["place_from"]) <= place <= int(s["place_to"]):
                return float(s["multiplier"])
        return 1.0

    # squad rank strength: the sum of the ranks actually on a roster. Recorded because a
    # player on an under-strength squad is measured in conditions the band average does not
    # account for - it is the standing "genuine reason" for setting a demotion aside.
    sqrank = defaultdict(list)
    for s_ in read(f"{d}/squads.csv"):
        r_ = rank_on(s_["player_id"], date)
        if r_: sqrank[s_["squad_id"]].append(r_)
    ranksum = {k: sum(v) for k, v in sqrank.items() if len(v) >= 3}
    rs_vals = sorted(ranksum.values())
    rs_mean = sum(rs_vals) / len(rs_vals) if rs_vals else 0
    rs_sd = (sum((x - rs_mean) ** 2 for x in rs_vals) / len(rs_vals)) ** .5 if rs_vals else 0
    rs_med = st.median(rs_vals) if rs_vals else 0

    sqk = defaultdict(int)
    for r in mres.values():
        sqk[r["squad_id"]] += num(r["squad_kills"]) or 0

    pl = defaultdict(lambda: {"n": 0, "k": 0, "pts": 0.0, "pl": [], "sq": None,
                              "sqk": 0, "dmg": 0, "dn": 0, "imp": 0, "maps": []})
    for r in pmap:
        mr = mres[(r["map_no"], r["squad_id"])]
        if mr.get("confidence") == "W":      # squad had withdrawn - not a performance
            continue
        place = num(mr["placement"])
        o = pl[r["player_id"]]
        o["n"] += 1
        o["k"] += num(r["kills"]) or 0
        o["pts"] += (num(r["kills"]) or 0) * mult(place)
        o["maps"].append((num(r["kills"]) or 0) * mult(place))
        o["sq"] = r["squad_id"]
        o["sqk"] += num(mr["squad_kills"]) or 0
        if place:
            o["pl"].append(place)
        if num(r["damage"]) is not None:
            o["dmg"] += num(r["damage"]); o["dn"] += 1
        if r["confidence"] in ("I", "L", "T", "U"):
            o["imp"] += 1

    rows = []
    for pid, o in pl.items():
        rows.append({"p": players.get(pid, pid), "id": pid, "rk": rank_on(pid, date),
                     "team": labels.get(o["sq"], ""), "n": o["n"], "imp": o["imp"],
                     "k": o["k"], "kpm": round(o["k"] / o["n"], 2),
                     "plc": round(sum(o["pl"]) / len(o["pl"]), 2) if o["pl"] else None,
                     "pts": round(o["pts"], 1), "ppm": round(o["pts"] / o["n"], 2),
                     "med": round(st.median(o["maps"]), 2),
                     "ks": round(o["k"] / o["sqk"] * 100, 1) if o["sqk"] else None,
                     "dpm": round(o["dmg"] / o["dn"]) if o["dn"] else None,
                     "sqrank": ranksum.get(o["sq"]),
                     "sqrank_vs_field": (round(ranksum[o["sq"]] - rs_med, 1)
                                         if ranksum.get(o["sq"]) is not None else None),
                     "extn": o["dn"]})
    # Scoring base is the MEDIAN of a player's maps, not the mean. Tanking the back
    # half of an event moves a mean a long way and a median barely at all, so there is
    # nothing to gain by giving up once you're out of contention.
    MIN_BAND = 5      # below this a band is too small for a spread to mean anything
    # Two routes up, one route down.
    #   STANDOUT  a single event far enough outside your band promotes you on the spot,
    #             but only if the score was actually yours - see SHARE_GATE.
    #   CONSISTENT the two directions are deliberately NOT symmetric, because the two
    #             directions are not equally wanted.
    #             UP:   two of any rolling three. "Two in a row" is beaten by alternating -
    #                   clear the line, coast the next event, reset the counter, repeat.
    #             DOWN: two consecutive, which is the stricter of the two. Going down is the
    #                   direction players benefit from, so it should be the harder one to
    #                   engineer. It also protects anyone having a genuine rough patch.
    # There is no single-event demotion. A bad night is a bad night, and making one
    # cost a rank would hand a tanking player exactly what they wanted.
    STANDOUT_K = 2.0
    CONSISTENT_K = 1.25
    SHARE_GATE = 30.0   # % of your squad's kills. An even three-way split is 33.3%.
    # A capped bracket does not sample the low ranks fairly. An 8- fills with 8s and 7s;
    # the handful of 5s who turn up chose to enter a field above them, so they are a
    # self-selected group, not a sample of rank 5. No amount of them fixes that, which is
    # why the floor is structural and not just a sample-size rule.
    FLOOR = int(meta.get("movement_floor") or 0)
    band = defaultdict(list)
    for r in rows:
        if r["rk"] and r["n"] >= 5:
            band[r["rk"]].append(r["med"])
    bands = {}
    for k, v in sorted(band.items()):
        c, sd = st.mean(v), (st.pstdev(v) if len(v) > 1 else 0)
        below_floor = k < FLOOR
        small = len(v) < MIN_BAND or below_floor
        bands[str(k)] = {"mean": round(c, 2), "n": len(v), "sd": round(sd, 2),
                         "lo": round(min(v), 2), "hi": round(max(v), 2),
                         "promote_at": None if small else round(c + CONSISTENT_K * sd, 2),
                         "standout_at": None if small else round(c + STANDOUT_K * sd, 2),
                         "demote_at": None if small else round(c - CONSISTENT_K * sd, 2),
                         "share_gate": SHARE_GATE,
                         "small": small, "below_floor": below_floor,
                         "why": ("below the movement floor for a "
                                 + str(meta.get("bracket") or "capped") + " bracket"
                                 if below_floor else
                                 "fewer than %d players" % MIN_BAND if len(v) < MIN_BAND
                                 else None)}
    for r in rows:
        b = bands.get(str(r["rk"]))
        r["res"] = round(r["med"] - b["mean"], 2) if b and r["n"] >= 5 else None
        r["sig"] = 0            # -1 / +1 : counts toward a two-event move
        r["standout"] = False   # single-event automatic promotion
        if b and not b["small"] and r["res"] is not None and b["sd"]:
            z = r["res"] / b["sd"]
            if z >= CONSISTENT_K: r["sig"] = 1
            elif z <= -CONSISTENT_K: r["sig"] = -1
            # Squad strength is NOT an automatic exemption. An exemption at a fixed
            # threshold is something a player could deliberately construct, and it turns a
            # 19 into a 20 being the whole decision. It is recorded as context and put in
            # front of whoever makes the call instead.
            if z >= STANDOUT_K and (r["ks"] or 0) >= SHARE_GATE:
                r["standout"] = True
            r["z"] = round(z, 2)
    rows.sort(key=lambda r: -r["ppm"])

    tm = defaultdict(lambda: {"pts": 0.0, "k": 0, "pl": []})
    for r in mres.values():
        place = num(r["placement"])
        tm[r["squad_id"]]["pts"] += (num(r["squad_kills"]) or 0) * mult(place)
        tm[r["squad_id"]]["k"] += num(r["squad_kills"]) or 0
        if place:
            tm[r["squad_id"]]["pl"].append(place)
    teams = sorted(({"t": labels[s], "pts": round(v["pts"], 1), "k": v["k"],
                     "plc": round(sum(v["pl"]) / len(v["pl"]), 2)}
                    for s, v in tm.items()), key=lambda r: -r["pts"])
    if PUBLIC:
        dec = [x for x in dec if x["visibility"] == "public"]
        for r in rows:
            r.pop("res", None)          # withhold the gap - it reads as a ranking
            r.pop("sig", None)
            r.pop("z", None)
            r.pop("dpm", None)
            r.pop("extn", None)
        rows.sort(key=lambda r: (-(r["rk"] or 0), r["p"].lower()))
    meta["rank_sum_mean"] = round(rs_mean, 1)
    meta["rank_sum_lo"] = min(rs_vals) if rs_vals else None
    meta["rank_sum_hi"] = max(rs_vals) if rs_vals else None
    meta["rank_sum_median"] = rs_med
    out["tournaments"].append({"date": date, "meta": meta, "players": rows,
                               "bands": bands, "teams": teams, "decisions": dec})

# carry-forward: residual per player per tournament, standout only on two in a row
# (player, tournament date) pairs where the rank moved as a result of that event
RANK_CHANGED = set()
for t in out["tournaments"]:
    for dd in t.get("decisions", []):
        if dd.get("direction") in ("promote", "demote"):
            nm = next((r["p"] for r in t["players"] if r["id"] == dd["player_id"]), None)
            if nm: RANK_CHANGED.add((nm, t["date"]))

cf = defaultdict(dict)
for t in out["tournaments"]:
    for r in t["players"]:
        if r.get("res") is not None:
            cf[r["p"]][t["date"]] = (r["res"], r.get("sig", 0), r.get("standout", False),
                                     r.get("sqrank_vs_field"), r.get("ks"))
dates = [t["date"] for t in out["tournaments"]]
watch = []
for p, byd in cf.items():
    blank = (None, 0, False, None, None)
    seq = [byd.get(d, blank)[0] for d in dates]
    sig = [byd.get(d, blank)[1] for d in dates]
    sto = [byd.get(d, blank)[2] for d in dates]
    sqd = [byd.get(d, blank)[3] for d in dates]
    ksh = [byd.get(d, blank)[4] for d in dates]
    # A rank change resets the clock. Without this a promotion plus one good event
    # reads as "two above the line" and promotes again, when the first of those two
    # has already been paid out. Dyna was +1.36 as a rank 8, was moved to 9, and came
    # back +1.79 as a 9 - correctly ranked, not owed a second move.
    # a decision recorded AT event N is the output of event N, so it resets the clock
    # for N+1 onward - it must not blank the window that produced it
    moved = [i for i, d in enumerate(dates)
             if (p, d) in RANK_CHANGED and i < len(dates) - 1]
    start = (max(moved) + 1) if moved else 0
    sig = sig[start:]
    flag = "hold"
    for i in range(len(sig)):
        w = sig[max(0, i - 2):i + 1]               # UP: two of any rolling three
        if len(w) >= 2 and w.count(1) >= 2:
            flag = "PROMOTE"
    for i in range(len(sig) - 1):                  # DOWN: two consecutive
        if sig[i] == -1 and sig[i + 1] == -1:
            flag = "DEMOTE"
    if sto and sto[-1]:
        flag = "PROMOTE (standout)"
    note = []
    if flag == "DEMOTE":
        light = [d for d, v in zip(dates, sqd) if v is not None and v <= -2]
        if light:
            note.append("short-handed squad at " + ", ".join(light) +
                        " — weigh before acting")
        lowks = [d for d, v in zip(dates, ksh) if v is not None and v >= 45]
        if lowks:
            note.append("carried a high kill share at " + ", ".join(lowks))
    watch.append({"p": p, "res": seq, "flag": flag, "sqrank_vs_field": sqd,
                  "kill_share": ksh, "consider": note})
out["carry_forward"] = {"rule": "standout 2.0 SD + 30% kill share = immediate; 1.25 SD in two of any three = up; 1.25 SD in two consecutive = down; no single-event demotion", "dates": dates,
                        "rows": sorted(watch, key=lambda r: -(r["res"][-1] or 0))}

os.makedirs(f"{ROOT}/docs", exist_ok=True)
if PUBLIC:
    out.pop("carry_forward", None)
dest = f"{ROOT}/docs/data.json" if PUBLIC else f"{ROOT}/admin/data.json"
os.makedirs(os.path.dirname(dest), exist_ok=True)
json.dump(out, open(dest, "w"), separators=(",", ":"))
print("wrote", dest)
t = out["tournaments"][-1]
print(f"built {len(out['tournaments'])} tournament(s), "
      f"{len(t['players'])} players, bands {list(t['bands'])}")
