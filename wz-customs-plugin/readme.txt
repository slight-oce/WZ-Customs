=== WZ Customs ===
Contributors: slight-oce
Tags: gaming, tournament, rankings, warzone, leaderboard
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Renders the Warzone customs rank review — promotions, rank bands and per-player
breakdowns — from a published data.json.

== Description ==

Reads the JSON published by the wz-customs project and renders it as shortcodes inside an
existing WordPress site. The plugin holds no tournament data of its own, computes no
ranking figure, and cannot write anything back.

**Shortcodes**

* `[wz_customs_changes]` — promotions and holds
* `[wz_customs_bands]` — where the rank bands sit
* `[wz_customs_players player_page="/players/"]` — everyone, grouped by rank, with a filter
* `[wz_customs_player]` — one player's breakdown, from `?player=` in the URL
* `[wz_customs_teams limit="10"]` — squad standings
* `[wz_customs_rules]` — the ranking rule, with this event's numbers

All of them accept `date="YYYY-MM-DD"` to pin an older tournament. Left off, they show the
most recent one.

**Withholding**

The upstream project builds a public feed and a private admin feed from the same source.
This plugin filters every payload through an allowlist on ingest — before caching — so
demotions, internal decisions, discord IDs and the band residual cannot reach a page even
if the plugin is pointed at the wrong file. If it ever finds any, it says so in wp-admin
rather than failing quietly.

== Installation ==

1. Copy the plugin folder to `/wp-content/plugins/`.
2. Activate it through the Plugins screen.
3. Go to Settings → WZ Customs and set the data URL.
4. Add a shortcode to any page or post.

== Frequently Asked Questions ==

= Where does the data come from? =

A `data.json` you host — by default the public GitHub Pages build of the wz-customs
project. Set the URL under Settings → WZ Customs.

= How often does it refetch? =

Every 15 minutes by default, configurable between 60 seconds and 24 hours. There is a
"Refresh now" button on the settings screen.

= What happens if the source is unreachable? =

The last good copy is served and the error is shown in wp-admin. The page does not go
blank.

= Can it show demotions? =

No, and there is no setting that enables it. Demotions are withheld by the upstream
project by design, and this plugin drops them independently of what the feed says.

== Screenshots ==

1. Rank changes and the reviewed-but-held cards.
2. Rank bands, showing which bands do not move automatically.
3. A player's own breakdown against their band.

== Changelog ==

= 0.1.0 =
* First release. Six shortcodes, cached fetch, settings screen, privacy guard.
