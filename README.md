# ITBoffins Image Scout

A free WordPress plugin that finds images your Media Library misses, compresses JPEG/PNG files, and serves next-gen **WebP** using only the image tools already on your server (**GD** or **Imagick**).

No external API. No account. No shell access required. Works on practically **any WordPress host**, including cheap shared hosting.

> Built and maintained by [IT Boffins](https://itboffins.com/).

## Why this plugin

Most image-optimisation plugins either send your images to a paid third-party API (with monthly quotas), only process Media Library attachments, or shell out to binaries like `cwebp`/`jpegoptim` that are blocked on most shared hosting. Image Scout does neither: it uses WordPress's own `WP_Image_Editor` abstraction and adds a batched uploads-folder scout for files page builders and themes leave outside the Library.

- 🔒 **Local only** — images never leave your server.
- 🌍 **Runs anywhere** — no `exec()`, no `.htaccess` requirement.
- 🪶 **WebP for everyone** — delivered via the standard `<picture>` element, so it works on Apache, Nginx, LiteSpeed and IIS.
- 🧱 **Uploads scout** — finds page-builder, theme, and imported files that are on disk but not in the Media Library.
- ↩️ **Reversible** — optional protected backups let you restore originals with one click.

## The Scout workflow

The standout workflow is the uploads-folder scout. It walks /uploads in small AJAX batches, skips the protected backup folders, and validates every generated WebP before it can be served. It is aimed at builder/theme images that sit on disk outside the Media Library, without rewriting the entire front-end response.

## Features

| Feature | Details |
| --- | --- |
| Compress on upload | New JPEG/PNG uploads optimised automatically |
| Bulk optimiser | Batch-compress your whole Media Library (AJAX, runs in background, shows each filename as it works) |
| Scan uploads folder | Generate WebP for *every* JPEG/PNG on disk — including page-builder/theme images not in the Media Library (memory-safe, resumable) |
| WebP generation | Creates `image.jpg.webp` siblings where the server supports WebP |
| Auto WebP delivery | Wraps `<img>` in `<picture>` with a WebP `<source>` + original fallback |
| Front-end delivery | Rewrites WordPress content/image markup to use WebP where a validated WebP file exists |
| Backup & restore | Optional protected originals kept in a randomised uploads subfolder |
| Capability panel | Shows exactly what GD/Imagick/WebP your host supports |

## Requirements

- WordPress 5.8+
- PHP 7.2+
- GD **or** Imagick (one is present on virtually all hosts). WebP generation additionally requires WebP support compiled into that library.

## Installation

### From source (this repo)

1. Download or clone this repository.
2. Copy the `itboffins-image-scout` folder into `wp-content/plugins/`.
3. Activate **ITBoffins Image Scout** in **Plugins**.
4. Configure under **Settings → Image Scout**; administrators can bulk-process under **Media → Bulk Optimise**.

### From the WordPress.org directory

_(Coming soon — search "ITBoffins Image Scout" in your wp-admin Plugins → Add New screen.)_

## How it works

- **Compression** re-encodes JPEGs at a configurable quality and keeps the result only if it is actually smaller (and decodes correctly). PNGs are kept lossless.
- **WebP** files are written next to the original as `original.ext.webp` (collision-free naming → trivial existence check at serve time). Output is validated; indexed/palette PNGs are flattened to truecolor first to avoid corrupt WebP.
- **Delivery** (default) filters `the_content`, `post_thumbnail_html`, and `wp_get_attachment_image`, swapping each eligible `<img>` for a `<picture>` that offers WebP first and falls back to the original. URL matching is done on the path, so www/non-www and http/https variants resolve correctly.
- **Front-end delivery** rewrites images that pass through WordPress content and image filters. CSS background images cannot use `<picture>` and are not converted.

## Development

This is a plain PHP WordPress plugin — no build step. Coding standard target: [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards).

```
itboffins-image-scout/
├── itboffins-image-scout.php   # bootstrap
├── includes/
│   ├── class-itboffins-image-scout-settings.php       # options + defaults
│   ├── class-itboffins-image-scout-capabilities.php   # runtime server probe
│   ├── class-itboffins-image-scout-optimizer.php      # compress / webp / backup / restore + path-based API
│   ├── class-itboffins-image-scout-scanner.php        # memory-safe recursive /uploads WebP scanner
│   ├── class-itboffins-image-scout-frontend.php       # <picture> delivery
│   ├── class-itboffins-image-scout-admin.php          # settings + bulk + media column
│   └── class-itboffins-image-scout-ajax.php           # AJAX endpoints
├── assets/                     # admin.css, admin.js
├── readme.txt                  # WordPress.org readme
└── uninstall.php
```

> Note: the WordPress.org slug and text domain are `itboffins-image-scout`; internal PHP/JS prefixes use `ITBOFFINS_IMAGE_SCOUT_` and `itboffins_image_scout_` for collision safety.

## Contributing

Issues and pull requests welcome. Please keep changes host-agnostic — anything that assumes a specific binary, shell access, or an external API is out of scope for this plugin.

## License

[GPL-2.0-or-later](LICENSE).
