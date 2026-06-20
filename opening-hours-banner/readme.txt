=== Opening Hours by IT Boffins ===
Contributors: itboffins
Tags: opening hours, business hours, store hours, open closed, hours banner
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show your opening hours and a live "We're open / Closed" banner that stays correct even with page caching. Free, no external services.

== Description ==

**Opening Hours by IT Boffins** displays your business hours and an automatic *We're open / Sorry, we're closed* status banner — and, unlike most opening-hours plugins, it keeps working correctly even when your site is served from a full‑page cache.

Most "open/closed" plugins calculate the status on the server and bake it into the HTML. The moment a caching plugin or CDN stores that page, the status freezes and goes stale. This plugin solves that: the live status is computed **in the visitor's browser** from your schedule, so it is always right — and it even flips over the moment you open or close, with no page reload.

= Why you'll like it =

* **Caching‑safe.** Works with WP Rocket, W3 Total Cache, LiteSpeed, Cloudflare and any CDN. The status is computed client‑side, never frozen in cached HTML.
* **Polished, no‑fuss editor.** Set hours per day with native time pickers. Add split hours (e.g. a lunch break) with one click. Copy Monday to all weekdays.
* **Holidays & special dates.** Close for a public holiday or set special one‑off hours that override the weekly schedule.
* **Uses your WordPress timezone.** Correct across daylight saving — automatically.
* **Overnight hours.** Late‑night venues are handled (e.g. 22:00 – 02:00).
* **Live status banner.** Top or bottom of the page, your colours, optionally dismissible, with optional "Closes at …" / "Opens …" and "Opening/Closing soon" wording.
* **Shortcode & block.** Drop your hours and status anywhere with the *Opening Hours* block or `[opening_hours]` shortcode.
* **Genuinely free & private.** No external API, no account, nothing leaves your server. Works on any host.

== Installation ==

1. Upload the `opening-hours-banner` folder to `/wp-content/plugins/`, or install through the Plugins screen.
2. Activate the plugin.
3. Go to **Opening Hours** in the admin menu, set your weekly hours, and save.
4. Optionally add the *Opening Hours* block or the `[opening_hours]` shortcode to a page.

== Frequently Asked Questions ==

= Does it work with page caching, WP Rocket or a CDN? =
Yes — that is the whole point. The open/closed status is worked out in the browser from your schedule, so even a heavily cached page shows the correct status and updates live.

= Which timezone is used? =
Your site's WordPress timezone (Settings → General). If you use a named timezone (e.g. Europe/London) daylight saving is handled automatically.

= Can I set a lunch break or split hours? =
Yes. Add more than one time range to a day, e.g. 09:00–12:00 and 13:00–17:00.

= Can I open past midnight? =
Yes. Set a closing time earlier than the opening time, e.g. 22:00–02:00.

= How do I show hours on a page? =
Use the *Opening Hours* block, or the shortcode: `[opening_hours show="table"]`, `[opening_hours show="status"]`, or `[opening_hours show="both"]`.

= Does it send any data anywhere? =
No. There is no external API and no tracking. Everything runs on your own site.

== Screenshots ==

1. The schedule editor with a live preview.
2. The open/closed status banner on the front end.
3. The opening‑hours table from the block / shortcode.

== Changelog ==

= 1.0.0 =
* Initial release: weekly schedule editor, split hours, holidays/special dates, timezone‑aware caching‑safe status banner, hours table block + shortcode.

== Upgrade Notice ==

= 1.0.0 =
First release.
