# Local Image Optimiser

A free WordPress plugin that compresses your images and serves next-gen **WebP** using only the image tools already on your server (**GD** or **Imagick**).

No external API. No account. No shell access required. Works on practically **any WordPress host**, including cheap shared hosting.

> Built and maintained by [IT Boffins](https://itboffins.com/).

## Why this plugin

Most image-optimisation plugins either send your images to a paid third-party API (with monthly quotas) or shell out to binaries like `cwebp`/`jpegoptim` that are blocked on most shared hosting. This one does neither — it uses WordPress's own `WP_Image_Editor` abstraction, which transparently uses whatever your server has (Imagick or GD).

- 🔒 **Local only** — images never leave your server.
- 🌍 **Runs anywhere** — no `exec()`, no `.htaccess` requirement.
- 🪶 **WebP for everyone** — delivered via the standard `<picture>` element, so it works on Apache, Nginx, LiteSpeed and IIS.
- 🧱 **Page-builder friendly** — optional whole-page mode also converts Elementor/Divi and theme-template images.
- ↩️ **Reversible** — keeps a backup of every original; restore with one click.

## Features

| Feature | Details |
| --- | --- |
| Compress on upload | New JPEG/PNG uploads optimised automatically |
| Bulk optimiser | Batch-compress your whole Media Library (AJAX, runs in background, shows each filename as it works) |
| Scan uploads folder | Generate WebP for *every* JPEG/PNG on disk — including page-builder/theme images not in the Media Library (memory-safe, resumable) |
| WebP generation | Creates `image.jpg.webp` siblings where the server supports WebP |
| Auto WebP delivery | Wraps `<img>` in `<picture>` with a WebP `<source>` + original fallback |
| Whole-page mode | Optional output buffering rewrites every image, including page-builder and theme output |
| Backup & restore | Untouched originals kept in `/uploads/lio-originals` |
| Capability panel | Shows exactly what GD/Imagick/WebP your host supports |

## Requirements

- WordPress 5.8+
- PHP 7.2+
- GD **or** Imagick (one is present on virtually all hosts). WebP generation additionally requires WebP support compiled into that library.

## Installation

### From source (this repo)

1. Download or clone this repository.
2. Copy the `local-image-optimizer` folder into `wp-content/plugins/`.
3. Activate **Local Image Optimiser** in **Plugins**.
4. Configure under **Settings → Image Optimiser**; bulk-process under **Media → Bulk Optimise**.

### From the WordPress.org directory

_(Coming soon — search "Local Image Optimiser" in your wp-admin Plugins → Add New screen.)_

## How it works

- **Compression** re-encodes JPEGs at a configurable quality and keeps the result only if it is actually smaller (and decodes correctly). PNGs are kept lossless.
- **WebP** files are written next to the original as `original.ext.webp` (collision-free naming → trivial existence check at serve time). Output is validated; indexed/palette PNGs are flattened to truecolor first to avoid corrupt WebP.
- **Delivery** (default) filters `the_content`, `post_thumbnail_html`, and `wp_get_attachment_image`, swapping each eligible `<img>` for a `<picture>` that offers WebP first and falls back to the original. URL matching is done on the path, so www/non-www and http/https variants resolve correctly.
- **Whole-page mode** (optional) buffers the full page output and rewrites every `<img>`, catching images printed by page builders and theme templates. CSS background images cannot use `<picture>` and are not converted.

## Development

This is a plain PHP WordPress plugin — no build step. Coding standard target: [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards).

```
local-image-optimizer/
├── local-image-optimizer.php   # bootstrap
├── includes/
│   ├── class-lio-settings.php       # options + defaults
│   ├── class-lio-capabilities.php   # runtime server probe
│   ├── class-lio-optimizer.php      # compress / webp / backup / restore + path-based API
│   ├── class-lio-scanner.php        # memory-safe recursive /uploads WebP scanner
│   ├── class-lio-frontend.php       # <picture> delivery (+ whole-page mode)
│   ├── class-lio-admin.php          # settings + bulk + media column
│   └── class-lio-ajax.php           # AJAX endpoints
├── assets/                     # admin.css, admin.js
├── readme.txt                  # WordPress.org readme
└── uninstall.php
```

> Note: code identifiers (the `local-image-optimizer` slug, text domain, file names, function/hook names) use the US spelling "optimizer" for technical stability; the user-facing interface uses British English ("optimiser").

## Contributing

Issues and pull requests welcome. Please keep changes host-agnostic — anything that assumes a specific binary, shell access, or an external API is out of scope for this plugin.

## License

[GPL-2.0-or-later](LICENSE).
