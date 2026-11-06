=== TT Team Planner ===
Contributors: robinaldasoro
Tags: table tennis, team management, sports, club
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.13.0
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

= Who can edit the planner? =

Any logged-in user can view it. Editing (line-ups, squads, availability, players, magic links) requires the planner management capability (`ttp_manage_planner`), which administrators and editors have. To let a member of the team committee manage the planner without access to the WordPress dashboard, give them the "Membre commission équipe" role: they are sent to the planner instead of wp-admin. Syncing players, importing matches, resetting the season and reading the action history remain reserved to administrators.

== Changelog ==

= 1.13.0 =
* Feature: pre-written SMS can be edited from the app settings, with a live preview. Each committee member edits their own copy; without one, they send the club template set in the dashboard, and can go back to it at any time.
* Feature: the line-up confirmation SMS, so far never sent, can be sent to each player placed in a line-up, from the round view.
* Feature: an action history shows administrators who changed what over the last 7 days: line-ups, availability, players, squads, forfeits, validations, settings, and players' answers through their link. It opens from each team header in the round view, or from the settings. Contact details are named when changed, never logged.
* Fix: SMS variables were only replaced once, {nom} and {equipe} were not replaced in the availability request, and the date was written as 2026-11-07. Every occurrence is replaced and the date reads "samedi 7 novembre".

= 1.12.2 =
* Fix: in the Players tab selection mode, clicking the round check mark next to a player did not select them. The whole card now toggles the selection.

= 1.12.1 =
* Fix: 1.12.0 crashed the app, the settings page and burnage with a critical error: two new classes were not loaded by the plugin.

= 1.12.0 =
* Feature: in Pré-Nationale, R1, R2 and R3, a team must field 4 players (LNATT regional rules, art. 4.6.2). A regional team with fewer than 4 players is flagged: a discreet "4 players required" while the round is more than 6 days away, then a red "3/4 · match lost" badge, a warning on the team tab and on the shared image. Fielding 3 players loses the match but is not a forfeit: declaring a forfeit for a team with players in its line-up now asks for confirmation.
* Feature: an ⓘ button in the round view opens the burnage and qualification rules, in short with the full text on demand, for members without access to the settings.
* Feature: the rules summary in the settings lists the complete-team rule.

= 1.11.0 =
* Feature: a "Dispos" tab in the round view lists every active player for the round, grouped by availability: available and not selected, available and selected (with their team), uncertain, no answer (with the SMS reminder) and unavailable. A header tells whether there are enough players: "31 available for 40 places · 6 without answer", forfeited teams left out.

= 1.10.0 =
* Feature: projected burnage. A player who would be burned if the earlier rounds not yet validated were played as composed now gets an orange warning badge in the line-ups and the player picker. Real burnage is unchanged and still counts validated rounds only.
* Feature: sharing the line-ups image now attaches a link to the round, which messaging apps turn into a clickable link. The share window also shows the link with a Copy button, for when the image is downloaded instead.
* Fix: validating or unvalidating a round now reloads the burnage of the following rounds, instead of showing stale badges until the team is changed.
* Fix: the round view subtitle always showed "0/N line-ups". It now counts the teams with four players in the round on screen.
* Fix: the dashboard counters now follow line-up changes without reloading the page.
* Fix: a player moved to another team of the same round no longer stays displayed in their former slot until the next reload.
* Fix: the magic link email text can now be translated.
* Change: a team declared forfeit no longer counts as an incomplete line-up. The counters leave it out and show it apart ("5/7 line-ups · 1 forfeit"), like the share image.
* Change: "Declare forfeit" becomes a discreet link, so it no longer sits at the same level as "Validate this line-up".
* Change: the manual "burned" flag, which nothing could set or display, is removed along with its badge, the "Burned" filter of the player list and its database column. The burnage computed for each team is unchanged.

= 1.9.1 =
* Change: the "Gestionnaire planner" role is renamed "Membre commission équipe". Existing role holders keep it, with the same rights.

= 1.9.0 =
* Security: the players, availability and squads endpoints of the API answered without authentication, exposing each player's name, phone number, email and license number. They now require a logged-in user, like the rest of the API.
* Feature: a "Planner manager" role lets someone manage the planner from the front end without any access to the WordPress dashboard. Editing the planner now requires its dedicated capability, given to administrators and editors on update; authors and contributors lose write access.

= 1.8.0 =
* Feature: a "Share line-ups" button in the round view builds a single image of every team's line-up for that round, to discuss in the captains' group chat. Each team shows its status (complete, incomplete, validated, forfeit) and each player their points and the signals that matter: unavailable, uncertain or unanswered, burned (with the reason), foreign, transfer, captain. Players who are unavailable or burned are highlighted in red, and empty slots in yellow. The players available but not selected are listed at the bottom.
* Feature: burnage and validation are checked for every team, not only the one open in the round view. When a team's burnage could not be loaded (offline, no cache), the image says so instead of showing no warnings.
* Feature: on phones, the image opens the share sheet (WhatsApp, etc.); everywhere, it can be downloaded as a PNG.

= 1.7.0 =
* Changed: the plugin now requires PHP 8.2, the version already required by MonClubTT. Activation is refused on an older PHP.

= 1.6.1 =
* Fix: the FFTT import recorded played matches and validated the round, but left the team line-ups empty in the app. Line-ups are now filled from the match sheet; a player planned in another team for the same round is removed from it.

= 1.6.0 =
* Feature: played matches are imported twice a day from the official FFTT match sheets, read through MonClubTT 1.9.0+. Each club team is matched to the planner team with the same number, players are recognised by name, and the round is marked as validated. An "Import now" button and a report (bye rounds, sheets without club players, unknown players) are available in the settings.
* Feature: team forfeits can be declared from a team's round. The two FFTT II.112.2 consequences are checked: after a forfeit on round 1, the team can only field players who did not play round 1 for another team; after a forfeit on another round, the players who played the previous round for that team cannot play for a higher-numbered team that day.
* Fix: the "Validate this line-up" button had disappeared from team rounds since June, so no match was ever recorded and the burnage rules never triggered. It is back as a fallback to the FFTT import (bye rounds, sheets not yet published).
* Fix: uninstalling the plugin now also drops the match, validated round, magic link and forfeit tables.
* Changed: the regional titles qualification is no longer listed in the rules summary.

= 1.5.0 =
* Feature: minimum points check for Nouvelle-Aquitaine regional divisions — 1200 points in Pré-Nationale, 1000 in Régionale 1, based on the best official ranking of the season recorded at each MonClubTT sync. In phase 2, players with at least 3 phase-1 matches in the team stay eligible (FFTT II.112.4).
* Feature: a second foreign (non-EU) player in the same team is now flagged in compositions (FFTT II.609).
* Feature: the settings page lists the burnage and eligibility rules, with what the app checks, flags, or leaves to the captain.

= 1.4.0 =
* Feature: the player sheet shows the player's photo when one is set in MonClubTT (1.8.0 or later), looked up by licence number. Initials remain the fallback when there is no photo, when MonClubTT is missing or older, or when the image fails to load.

= 1.3.1 =
* Fix: in the installed app, the bottom navigation bar was pushed below the fold by the height of the Android gesture bar — only a sliver of it remained visible. The bar now keeps clear of the system areas (Android gesture bar, iOS home indicator).

= 1.3.0 =
* Feature: availability e-mails now list the dates of the upcoming rounds, so players know which matches they are answering for before opening the link.
* Feature: automatic reminder — players who have not answered are e-mailed once, 7 days after the initial send. Filter `ttp_reminder_delay_days` changes the delay; 0 disables reminders entirely.
* Fix: availability e-mails were sent from wordpress@yourdomain and displayed as "WordPress". They are now sent under the club name, from a no-reply address on the site's own domain, with Reply-To set to the site admin.
* Improvement: availability e-mails are redesigned in the app's visual language (club header, preview of the three availability states, prominent call to action) and are sent as HTML with a plain-text alternative, which also improves their spam score.
* Cleanup: removed an unused admin class left over from an earlier version of the sync page.

= 1.2.0 =
* Feature: the team management page can be installed as an app on Android and iOS. The web app manifest is now generated from the plugin settings (club name, link to the actual front page) instead of a static file, and the plugin ships its own icons.
* Feature: new "Installation sur mobile" setting to enable or disable installation (enabled by default).
* Fix: the service worker was served from the plugin directory, so its scope never covered the app page and offline consultation never actually worked. It is now served from the site root.
* Fix: the service worker cached CSS/JS from every plugin, not just this one — it could serve stale assets, including in wp-admin.

= 1.1.1 =
* Fix: fatal error crashing the whole site (front and admin) since 1.0.5/1.1.0 — the magic-link classes were never loaded by the plugin bootstrap.

= 1.1.0 =
* Feature: permanently delete a player from the roster (irreversible), available once the player has already been soft-deleted. Wipes the player and all related data (availabilities, round compositions including validated rounds, match appearances, phase squads) from the database, behind a dedicated confirmation warning about the irreversibility.

= 1.0.5 =
* Feature: players can report their own availability without logging in, via a personal magic-link e-mail (token scoped to that player only, 3-month expiry). Captains/coaches can bulk-select players in the roster and send links in one action.
* Feature: e-mail field added to the player record (editable alongside phone/notes).
* The magic-link page is a minimal standalone page — no PWA manifest and no service worker registration — so it can never be "installed" as an app.

= 1.0.4 =
* Feature: soft-delete a player from the roster (listing and player detail screen) so they can no longer be selected in round compositions or phase squads, with a confirmation modal. History is preserved and the player can be restored at any time.
* Fix: deleting a player now also clears them from unvalidated round compositions and phase squads (already-validated rounds and match history are left untouched).

= 1.0.3 =
* Feature: player burn tracking (brûlage) — participation history, per-round validation, and burn status surfaced in the app.
* Feature: PWA install banner on iOS and Android.
* Fix: simplified phase tabs (no dates) and removed non-functional "Publish" button on journée pages.
* Fix: burned players now highlighted (red background, thick border) and burn status recalculated after each entry.
* Fix: removed the current-phase display from the admin settings screen.

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

= 1.13.0 =
Pre-written SMS editable from the app, line-up confirmation SMS, and an action history for administrators. Nothing to do: the current SMS templates become the club templates.

= 1.12.2 =
Selecting players in bulk works when clicking the check mark.

= 1.12.1 =
Fixes the critical error introduced by 1.12.0. Update right away.

= 1.12.0 =
Incomplete regional teams flagged, and the rules readable from the app. Check that each team's level is filled in the settings.

= 1.11.0 =
New "Dispos" tab in the round view: see at a glance who is available, selected or has not answered. Nothing to do.

= 1.10.0 =
Projected burnage, a link to the round in the shared image, and line-up counters that stay right. Nothing to do.

= 1.9.1 =
The "Gestionnaire planner" role is renamed "Membre commission équipe". Nothing to do.

= 1.9.0 =
Security fix: player contact details were readable without logging in. Update now. Authors and contributors can no longer edit the planner: give them the new "Planner manager" role.

= 1.8.0 =
Share all of a round's line-ups as one image, from the round view.

= 1.7.0 =
Requires PHP 8.2 or later. Check your hosting PHP version before updating.

= 1.6.1 =
Played rounds imported from the FFTT now show their line-up. Run "Import now" once after updating to fill the rounds already imported.

= 1.6.0 =
Burnage rules work again: played matches are now imported from the FFTT match sheets (requires MonClubTT 1.9.0) and team forfeits can be declared. Update MonClubTT first.

= 1.5.0 =
Adds the Nouvelle-Aquitaine minimum points rule and the one-foreign-player limit. Re-sync players after updating so their official points are recorded.

= 1.4.0 =
Player sheets now display the photos managed in MonClubTT 1.8.0+.

= 1.3.1 =
Fixes the bottom navigation bar being cut off below the fold in the installed app on Android.

= 1.3.0 =
Availability e-mails are no longer sent as "WordPress": they now come from the club, look the part, list the upcoming round dates, and non-responders get one automatic reminder after a week.

= 1.2.0 =
Adds real "install as an app" support for the team management page on Android and iOS, and fixes a service worker scope bug that prevented offline consultation from ever working.

= 1.1.1 =
Critical fix: 1.0.5 and 1.1.0 crash the whole site (fatal error). Update immediately.

= 1.1.0 =
Adds permanent, irreversible deletion of a player (and their history) from the roster, in addition to the existing reversible soft-delete.

= 1.0.4 =
Adds the ability to remove a player from the roster (soft-delete, reversible) so they can no longer be selected in team compositions.

= 1.0.3 =
Adds player burn tracking (brûlage), per-round validation, and a PWA install banner for iOS and Android.

= 1.0.2 =
Recommended update — fixes bottom nav bar, REST API URLs, and dashboard journée detection.
