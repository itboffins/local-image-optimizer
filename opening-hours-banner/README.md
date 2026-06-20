# Opening Hours by IT Boffins

Show your opening hours and a live **We're open / Closed** banner that stays
correct **even with page caching** — no external services, no account, works on
any WordPress host.

> **Note on this repository.** This plugin is fully self‑contained and is
> intended to live in its own repository (`itboffins/opening-hours-banner`). It
> was scaffolded alongside *Local Image Optimiser* for convenience; the
> `opening-hours-banner/` folder can be moved into a standalone repo as‑is.

## Why

Most opening‑hours plugins compute the open/closed status on the server and bake
it into the HTML. As soon as a caching plugin or CDN stores that page, the status
**freezes and goes stale** — a common, much‑complained‑about failure.

This plugin computes the live status **in the browser** from your schedule, so
the banner is always correct regardless of full‑page caching, and it flips the
moment you open or close — no reload required.

## Features

- **Caching‑safe** live status (works with WP Rocket, LiteSpeed, Cloudflare, …).
- **Polished editor** — native time pickers, split hours (lunch breaks), copy
  Monday to weekdays.
- **Holidays / special dates** that override the weekly schedule.
- **Timezone‑aware**, DST‑correct (uses your WordPress timezone via
  `Intl.DateTimeFormat`).
- **Overnight hours** (e.g. 22:00 – 02:00).
- **Status banner** (top/bottom, your colours, dismissible, "Closes at …" /
  "Opens …" and "soon" wording).
- **Block + shortcode** to show hours/status anywhere.
- **No external API**, nothing leaves your server.

## How the caching‑safety works

The localized config (`IBOH_DATA`) carries only the *schedule definition*, never
a computed status. `assets/frontend.js` evaluates open/closed live and re‑runs on
a 30‑second timer (and on tab focus). Timezone math uses
`Intl.DateTimeFormat({ timeZone })` so it reflects the **site's** wall clock, not
the visitor's, and handles daylight saving automatically. A PHP mirror
(`includes/class-iboh-evaluator.php`) renders a sensible no‑JS fallback.

## Usage

- **Admin:** *Opening Hours* in the WordPress menu.
- **Shortcode:** `[opening_hours show="table|status|both"]`
- **Block:** search for *Opening Hours* in the block inserter.

## Structure

```
opening-hours-banner.php      Bootstrap (constants, singleton, activation)
includes/
  class-iboh-settings.php     Options array: defaults / all / get / sanitize
  class-iboh-timezone.php     Site timezone → IANA name + DST-aware offset
  class-iboh-config.php       Canonical config shared by PHP + JS
  class-iboh-evaluator.php    Server-side open/closed (no-JS fallback)
  class-iboh-frontend.php     Asset enqueue, localize, banner injection
  class-iboh-shortcode.php    [opening_hours] + table/status renderers
  class-iboh-block.php        Server-rendered block (reuses the shortcode)
  class-iboh-admin.php        Settings page + schedule editor
  class-iboh-ajax.php         Nonce-guarded preview endpoint
assets/
  admin.css / admin.js        Schedule editor + live preview
  frontend.css / frontend.js  Banner + client-side evaluator
  editor.js                   Block editor (no build step)
```

## Development

Plain PHP WordPress plugin — **no build step**, no Composer, no npm. Targets the
[WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards).

## License

GPL‑2.0‑or‑later. More free plugins at [itboffins.com](https://itboffins.com/).
