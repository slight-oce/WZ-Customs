# 2026-08-21 — kills per player per map

One row per player, one column per scored map. Read off 147 scoreboard
screenshots; every team's three rows sum to that team's squad kills on every
map in `map_results.csv`, which came from the organiser's spreadsheet — 139
independent agreements.

`-` means no screenshot exists for that team on that map, so the value is
unknown. **It is not a zero.** Four such gaps hold real kills with no player
breakdown: Kenner's squad M9 (11 kills), Slight's squad M3 (8), Zinc's squad
M8 (14), Vibes' squad M7 (2). Slight's squad M9 is also blank, but that squad
scored 0 there, so the true value is 0 for all three.

`Z x Camzy x Kaiba` has four rows: Kaiba played M2–M5, Splyce X covered M1 and
M6–M9.

Map 10 was played but is not scored — the spreadsheet records a placement for
only 5 of 16 squads, and placement drives the multiplier. Its eight
screenshots are in `screenshot_readings.csv` under `map_no` 10.

The long form of this table, with assists, redeploys and damage, is
`player_map.csv`.


| Team | Player | M1 | M2 | M3 | M4 | M5 | M6 | M7 | M8 | M9 | Total |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Wakey x Mint x Nzzl | Wakey | 13 | 13 | 3 | 8 | 3 | 16 | 7 | 6 | 8 | 77 |
|  | Mint | 11 | 3 | 1 | 5 | 0 | 3 | 7 | 5 | 1 | 36 |
|  | Nzzl | 2 | 7 | 0 | 3 | 0 | 5 | 0 | 2 | 2 | 21 |
| Kenner x Cloudy x Kenn | Kenner | 4 | 2 | 23 | 6 | 10 | 1 | 5 | 11 | - | 62 |
|  | Cloudy | 0 | 4 | 16 | 7 | 7 | 1 | 2 | 16 | - | 53 |
|  | Kenn | 0 | 0 | 1 | 1 | 0 | 0 | 0 | 5 | - | 7 |
| 6ixer x Faf x Pengie | Pengie | 7 | 3 | 6 | 7 | 8 | 6 | 5 | 2 | 2 | 46 |
|  | stiinger | 0 | 5 | 5 | 6 | 6 | 5 | 2 | 2 | 4 | 35 |
|  | Faf | 2 | 2 | 3 | 2 | 5 | 5 | 1 | 1 | 0 | 21 |
| Bray x Vague x Money Demon | bray | 5 | 4 | 10 | 13 | 7 | 6 | 10 | 11 | 3 | 69 |
|  | Vague | 2 | 4 | 8 | 6 | 4 | 2 | 10 | 9 | 2 | 47 |
|  | MONEYDEMON | 0 | 1 | 4 | 0 | 3 | 1 | 1 | 2 | 1 | 13 |
| Kalon x Drizzy x Roccy | Kalon | 11 | 7 | 4 | 4 | 6 | 2 | 9 | 1 | 5 | 49 |
|  | Drizzy | 7 | 8 | 5 | 4 | 5 | 4 | 6 | 1 | 7 | 47 |
|  | Roccy | 1 | 3 | 0 | 1 | 3 | 0 | 1 | 0 | 2 | 11 |
| Dynax Koby x NzDee | Dyna | 10 | 3 | 4 | 5 | 6 | 3 | 8 | 4 | 3 | 46 |
|  | nzdee | 6 | 6 | 2 | 1 | 5 | 1 | 3 | 4 | 5 | 33 |
|  | Koby | 1 | 4 | 1 | 2 | 1 | 4 | 6 | 8 | 2 | 29 |
| Chong Lee x Minho x Unrivaled | ChongLee | 11 | 10 | 2 | 11 | 4 | 11 | 8 | 5 | 2 | 64 |
|  | Unrivaled | 3 | 7 | 3 | 1 | 5 | 13 | 3 | 4 | 8 | 47 |
|  | Minho | 0 | 2 | 1 | 0 | 2 | 2 | 6 | 0 | 2 | 15 |
| Z x Camzy x Kaiba | Zmoney | 11 | 8 | 9 | 5 | 6 | 11 | 5 | 1 | 1 | 57 |
|  | Camzy | 3 | 4 | 7 | 4 | 3 | 5 | 2 | 1 | 0 | 29 |
|  | Splyce | 7 | - | - | - | - | 4 | 1 | 0 | 0 | 12 |
|  | Kaiba | - | 3 | 3 | 1 | 2 | - | - | - | - | 9 |
| Slight x Envious Jay x jdz | JDZ | 4 | 10 | - | 8 | 2 | 6 | 5 | 8 | - | 43 |
|  | Jay | 3 | 3 | - | 6 | 3 | 5 | 13 | 6 | - | 39 |
|  | slight | 0 | 0 | - | 0 | 0 | 0 | 0 | 2 | - | 2 |
| Dx x Hassi x Lgks | Hassi | 8 | 1 | 2 | 4 | 9 | 2 | 1 | 4 | 12 | 43 |
|  | Lgks | 8 | 1 | 2 | 3 | 0 | 0 | 1 | 2 | 8 | 25 |
|  | Dx | 1 | 3 | 0 | 2 | 4 | 0 | 1 | 3 | 4 | 18 |
| Light x Jatzy x Venqur | Light | 2 | 0 | 5 | 2 | 1 | 10 | 2 | 3 | 6 | 31 |
|  | Jatzy | 2 | 0 | 2 | 0 | 0 | 3 | 2 | 6 | 7 | 22 |
|  | Venqur | 2 | 2 | 3 | 1 | 0 | 6 | 0 | 1 | 7 | 22 |
| Zinc x Snowy x Hikkar | Snowyy | 7 | 5 | 5 | 7 | 9 | 6 | 5 | - | 10 | 54 |
|  | Zinc | 8 | 3 | 9 | 7 | 4 | 2 | 13 | - | 3 | 49 |
|  | Hikkar | 1 | 0 | 0 | 0 | 2 | 3 | 5 | - | 2 | 13 |
| ZaZa x Akhi x Hi | Hi | 2 | 1 | 3 | 4 | 8 | 5 | 6 | 4 | 3 | 36 |
|  | zaza | 2 | 1 | 2 | 3 | 3 | 4 | 6 | 3 | 2 | 26 |
|  | Akhi- | 1 | 1 | 4 | 2 | 3 | 3 | 7 | 1 | 0 | 22 |
| Leza x Manemerc x Jono | Lezafort | 8 | 9 | 2 | 5 | 5 | 2 | 5 | 5 | 4 | 45 |
|  | ManeMerc | 0 | 3 | 2 | 3 | 4 | 5 | 3 | 6 | 2 | 28 |
|  | JONO | 0 | 4 | 2 | 0 | 2 | 4 | 3 | 9 | 3 | 27 |
| Zealo x N31mak x 5th Stump | N31MAK | 1 | 1 | 6 | 3 | 5 | 3 | 1 | 0 | 0 | 20 |
|  | zzeallo | 3 | 1 | 7 | 3 | 2 | 2 | 1 | 0 | 0 | 19 |
|  | 5thstump | 4 | 1 | 5 | 1 | 1 | 3 | 1 | 0 | 0 | 16 |
| Vibes x Cortez x Frenchy | Frenchy | 3 | 5 | 2 | 4 | 0 | 0 | - | 2 | 0 | 16 |
|  | Cortez | 2 | 1 | 2 | 1 | 2 | 1 | - | 1 | 3 | 13 |
|  | Vibes | 2 | 1 | 1 | 1 | 0 | 0 | - | 3 | 4 | 12 |
