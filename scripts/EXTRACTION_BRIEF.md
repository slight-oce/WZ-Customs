# Extraction brief — scoreboard screenshots

Read this whole file before touching anything.

## What you are doing

`1UP__-9_Tourney.zip` contains 124 in-game scoreboard screenshots from the 20 Jul 2026
tournament, one per squad per map. Each shows a squad's three players with SCORE,
ELIMINATIONS, KILLS, ASSISTS, REDEPLOYS, DAMAGE, and a SQUAD TOTALS row underneath.

Kills are already in the repo and are correct — they were cross-checked three ways
against the organiser's summary sheet. **Do not overwrite the kills column.** You are
adding `assists`, `redeploys` and `damage` to `data/tournaments/2026-07-20/player_map.csv`,
and the squad totals to `map_results.csv`.

## Why this file exists

A previous pass at this transcribed damage figures from screenshots and got **15 out of
15 wrong**, by an average of 26%. They survived review because the only thing they were
checked against was the SQUAD TOTALS row read off the same image in the same glance — so
a misread player row and a misread total agreed with each other, and the check passed.

Five separate conclusions were drawn from that data. All five were wrong and had to be
retracted. Kills never had the problem, because kills had a second independent source.

Everything below exists to stop that recurring.

## The rule that matters

**Read the SQUAD TOTALS row as its own separate record, and enter it into
`map_results.csv` independently of the player rows.**

Then `scripts/validate.py` requires the three player values to sum to it. If you misread
a digit, the sum stops matching and the build fails.

This only works if you genuinely read the totals row as a separate act — if you sum the
players and write that in, the check is circular and proves nothing. Read the players.
Read the total. Do not reconcile them by eye. Let the validator do it.

## File naming

Files are `<Reporter> Game <N>.<ext>` where N is the map number 1–9.

| Reporter | squad_id | Squad |
|---|---|---|
| Zinc | T01 | Snowyy x Zinc x Duq |
| Turbs | T02 | Sleepy x Chef Ram x Turbs |
| Inctz | T03 | Inctz x Light x Gooey |
| OG | T04 | Koby x Dyna x OG |
| Minho | T05 | Minho x Monkey x 5thstump |
| Kaiba | T06 | Obricks x Hazard x Kaiba |
| z | T07 | Guckie x JDZ x Zmoney |
| Aggy | T08 | Ankhas x Aggy x Unrivaled |
| Drizzy | T09 | Drizzy x Trix x Dx |
| ONFLEEK / ON FLEEK | T10 | Miss Fairy x OnFleek x Sass |
| Jatzy | T11 | Dolem1te x Jatzy x Hooli |
| Shan0 | T12 | Shan0 x Willybee x Caita |
| Delayed | T13 | Delayed x Kino x Aentity |
| Lezza | T14 | Leza x Lupin x Manemerc |
| Humz | T15 | Humz x VGL x Arsenic |
| RedShftr / Termi | T16 | Termxi x Meataxe x Redshiftr |

In-game names differ from sign-up names. `data/aliases.csv` maps them. Add any you find
that are missing rather than guessing — e.g. `[zinc]poj` and `duq` are in T01.

## Method, per screenshot

1. Open the image. If any column is cropped, blurred, or you are not certain of a digit,
   **leave that field empty and note it.** An empty cell is fine. A guessed cell is not.
2. Read the three player rows: name, assists, redeploys, damage.
3. Read the SQUAD TOTALS row separately: total assists, total redeploys, total damage.
4. Match players to `player_id` via `data/aliases.csv`.
5. Write player values into `player_map.csv` on the matching `(map_no, squad_id, player_id)`
   row. Write the totals into `map_results.csv` on the matching `(map_no, squad_id)` row.

Do not create new rows. Every row you need already exists. If a `(map_no, squad_id)` you
have a screenshot for is missing from `map_results.csv`, stop and report it — that means
the summary sheet and the screenshots disagree about who played, which is itself a finding.

## Withdrawal maps

Six squads left partway through. Maps after they withdrew are marked `confidence = W` and
carry no placement. If you have a screenshot for a map marked `W`, **stop and report it** —
it means the withdrawal boundary is wrong and player medians are being computed over the
wrong set of maps.

Known boundaries: T02 after M8, T08 after M5, T09 after M8, T10 after M6, T13 after M8,
T15 after M7, T16 after M8.

## When you are done

```bash
python scripts/validate.py        # must exit 0 for the rows you touched
python scripts/build.py           # admin build
python scripts/build.py --public  # public build
```

`validate.py` will fail on any squad where player values do not sum to the totals row.
**Do not fix a failure by editing the total to match the players.** Re-open the screenshot
and read both again. The failure is telling you one of the two is wrong, and adjusting one
to fit the other destroys the only check there is.

Report at the end:

- how many of the 124 you read completely
- every field left empty, and why
- every validator failure and how you resolved it
- any in-game name you added to `aliases.csv`

## What not to do

- Do not infer a value from the other two players and the total. That is arithmetic, not
  reading, and it launders a guess into the dataset.
- Do not touch `kills`.
- Do not touch any file under `data/tournaments/2026-07-19/`.
- Do not add damage to any scoring path. It is context only — the model runs on kills,
  placement and kill share, and that is deliberate. See README.
