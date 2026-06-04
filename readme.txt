=== TT Team Planner ===
Contributors: robinaldasoro
Tags: table tennis, team management, sports, club
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage team compositions and player availability for a table tennis club.

== Description ==

TT Team Planner is a mobile-first web application embedded in WordPress via a shortcode. It helps table tennis club commissions manage:

* **Player roster** — imported from MonClubTT (FFTT data), with phone numbers and internal notes editable directly in the app.
* **Availability** — per player, per round, per phase. Tap a cell to cycle through available / unavailable / uncertain.
* **Phase squads** — define a pool of players for each team at the start of a phase, then auto-ventilate compositions across all rounds with rotation.
* **Round compositions** — assign up to 4 players per team per round via a searchable picker that surfaces squad players first.
* **Hash-based routing** — every screen has its own URL (`#joueurs`, `#journee/3`, `#joueur/42`) for easy sharing within the commission.

**Requirements:**
* [MonClubTT](https://github.com/robinos33/MonClubTT) plugin must be installed and active to import players from the FFTT database.

== Installation ==

1. Install and activate the [MonClubTT](https://github.com/robinos33/MonClubTT) plugin.
2. Upload `tt-team-planner` to the `/wp-content/plugins/` directory.
3. Activate the plugin through the **Plugins** menu in WordPress.
4. Go to **TT Team Planner → Settings** to configure your club name, teams, and journée dates.
5. The plugin will automatically create a front-end page with the `[tt_team_planner]` shortcode, or you can add it manually to any page.
6. Synchronise your players from the **Settings** page.

== Frequently Asked Questions ==

= Does it work without MonClubTT? =

No. Player data comes from FFTT via MonClubTT. Without it, no players can be imported.

= Is an internet connection required? =

The app registers a Service Worker and caches assets for offline use. Data will still display from cache if the connection is lost, but changes cannot be saved.

= Can I use it on mobile? =

Yes — the app is designed mobile-first. It installs as a PWA on iOS and Android.

== Changelog ==

= 1.0.2 =
* Fix: bottom navigation bar now fixed to the bottom of the device viewport (100dvh).
* Fix: REST API URL construction for sites without pretty permalinks.
* Fix: dashboard now shows the actual next upcoming journée across both phases.
* Fix: compositions complete counter now reads real data from the phase.
* Feature: hash-based URL routing — every screen is shareable.
* Feature: player phone and notes editable from the player detail screen.
* Feature: availability editing directly from the player detail screen (both phases).
* Feature: phase squads — define player pools per team, auto-ventilate rounds.
* Feature: team management UI in admin settings (add/remove, auto-generated code, level datalist).
* Removed: alerts tab and rules engine (not used in production).

= 1.0.1 =
* Initial release.

== Upgrade Notice ==

= 1.0.2 =
Recommended update — fixes bottom nav bar, REST API URLs, and dashboard journée detection.
