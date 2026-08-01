# wz-customs-plugin

A WordPress plugin that renders the Warzone customs rank review — promotions, rank
bands, squad standings and per-player breakdowns — inside an existing site, from the
`data.json` published by [wz-customs](https://github.com/slight-oce/wz-customs).

It is a reader. It holds no tournament data of its own, computes no ranking figure, and
has no way to write anything back. Point it at a URL, drop a shortcode on a page.

```
[wz_customs_changes]                          promotions and holds
[wz_customs_bands]                            where the rank bands sit
[wz_customs_players player_page="/players/"]  everyone, grouped by rank
[wz_customs_player]                           one player, from ?player= in the URL
[wz_customs_teams limit="10"]                 squad standings
[wz_customs_rules]                            the ranking rule, with this event's numbers
```

Every shortcode takes `date="2026-07-20"` to pin an older tournament. Left off, they show
the most recent one.

---

## The part that matters: it does not trust its input

The upstream repo builds twice from the same CSVs. `build.py --public` writes
`docs/data.json`; running it bare writes `admin/data.json`, which additionally contains
demotions, the internal decisions, discord IDs, and `res` — the residual that orders every
member of a rank band from worst to best. The admin build is gitignored and never leaves
the organiser's machine.

The only thing separating those two files is which flag someone typed. And this plugin is
configured by pasting a URL into a text field in wp-admin.

So the plugin assumes the URL is wrong. Every payload is filtered through
`WZC_Privacy::sanitize()` **on ingest** — before it is cached, and long before it reaches a
template:

- Player rows are rebuilt from an **allowlist**. `res`, `sig`, `z`, `dpm`, `extn` and
  `discord_id` cannot survive, because nothing copies them across.
- A decision must be `visibility: public` **and** not a demotion. Both tests, independently.
  A demotion marked public is still withheld — that is far more likely to be the wrong cell
  ticked in a spreadsheet than a considered decision to publish that somebody was moved down.
- The `carry_forward` block is dropped whole. There is no public form of it.

Allowlist rather than denylist is deliberate. If the upstream build grows a new field, the
failure mode is that this plugin does not render it yet — not that it publishes it. Those
misses are listed on the settings screen under "Not rendered", separately from the leak
report, so "we don't show that yet" never gets confused with "we caught something".

Because sanitising happens before caching, a private field is never written to the
options table. Clearing the cache is not a remediation step, because there is nothing in
it to remediate.

When the guard does fire, it says so — an error notice on *every* admin screen, not just
the plugin's own. If the source URL is pointing at an admin build, that is worth
interrupting whatever the administrator was doing. A guard that quietly does its job is a
guard nobody knows has fired.

### Tested by grepping the output, not by inspecting the array

`tests/test-privacy.php` and `tests/test-render.php` both take an admin-build fixture
carrying every private field, run it through the real code, and then search the finished
output for the private *values* — `-3.82`, the discord ID, the demotion rationale, the
word "demote". Asserting that a key is absent only proves the key you thought of is
absent. Searching the rendered HTML catches a value that survived under a name nobody
predicted.

```bash
php tests/test-privacy.php   # 42 checks
php tests/test-render.php    # 53 checks
```

No PHPUnit, no WordPress, no install step — `tests/stubs.php` provides the dozen API
functions the render layer touches. CI runs both on PHP 7.4 through 8.4.

---

## Numbers are passed through, not recomputed

Upstream, `build.py` carries a standing rule: nothing downstream stores a calculated
number, so that correcting a kill count in a CSV moves every residual, band average and
carry-forward flag with it. This plugin keeps to the same rule for the same reason — a
figure recomputed here is a figure that can silently disagree with the source.

There is one derived value: the player page compares a player's **median map** against
their band's published `promote_at` line. Both numbers come from the payload; the plugin
only subtracts and compares.

**This differs from the standalone site**, which compares `ppm` against `mean` and uses a
hardcoded threshold of 2.50. Upstream, `res` is defined as `med - mean`, and the review
line is `mean + 1.25 × sd`, recomputed every event. The plugin follows `build.py`, because
that is the model of record — a fixed threshold means something different in every band,
which is the exact problem the recomputed line exists to solve. Worth reconciling the two
so a player reading both is told the same thing.

---

## Install

Copy the directory into `wp-content/plugins/`, activate it, then set the data URL under
**Settings → WZ Customs**. It defaults to the published public build.

The settings screen also shows when the data was last fetched, how many tournaments
loaded, and whether anything was withheld.

### Caching

The payload is cached in a transient, 15 minutes by default (60s–24h, configurable). On a
fetch failure the last good copy is served rather than blanking the page, and the error is
shown in wp-admin — GitHub Pages having a bad minute should not take the rankings off a
site.

---

## Layout

```
wz-customs.php              plugin header, constants, bootstrap
includes/
  class-wzc-privacy.php     the guard. No WordPress dependency, so it is testable alone.
  class-wzc-source.php      fetch, sanitise, cache. Only sanitised data is ever stored.
  class-wzc-data.php        read-only accessors over the payload
  class-wzc-render.php      all HTML, escaped at the point of output
  class-wzc-shortcodes.php  the six shortcodes
  class-wzc-settings.php    settings screen and the leak notice
assets/                     CSS scoped under .wzc, and the list filter
tests/                      standalone runners + public/admin fixtures
```

## Notes

- **Player IDs, never gamertags.** Three players changed name mid-tournament upstream. The
  `?player=` parameter and every internal lookup use `player_id`.
- **A missing figure renders as an em dash, never as zero.** Zero is a real result upstream
  — on a league leaderboard it means wiped early — and the two must not look alike.
- **Styles are scoped under `.wzc`** and each colour falls back to a CSS variable the host
  theme can override. A CMS plugin lands in someone else's theme and has no business
  restyling their headings.
- **Assets are enqueued only when a shortcode renders**, so a page with no customs content
  ships no CSS or JS.
- **Filtering the player list is a display toggle**, never a request — the whole list is
  already on the page.

## Not built yet

- **Gutenberg blocks.** Shortcodes work in the block editor via the Shortcode block, which
  is why they came first. Native blocks want a build step; worth adding if the site's
  editors would rather not type shortcodes.
- **Multi-tournament views.** Every shortcode renders one event. Anything spanning events
  is carry-forward shaped, and carry-forward is the one thing that has no public form —
  so a cross-event view needs a decision about what it may show before it needs code.
- **A player's history across events.** Same problem, same order: policy first.
