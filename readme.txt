=== ITBoffins Image Scout ===
Contributors: itboffins
Tags: image optimisation, webp, page builder, privacy, media library
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find images your Media Library misses. Compress JPEG/PNG and create WebP locally, with no API, account, or shell access.

== Description ==

**ITBoffins Image Scout** is a local image optimisation plugin for sites whose images are scattered across the Media Library, page-builder templates, theme output, and raw uploads folders.

Unlike most image plugins, there is **no external service**, no API key, no monthly quota, and no account to create. Your images never leave your server. Because it relies only on PHP's built-in image tools, it works on practically **any WordPress host**, including cheap shared hosting where shell access is disabled.

= The Image Scout difference =

Most optimisers focus on Media Library attachments. Image Scout adds a disk-level uploads scout: it walks `/uploads` in batches, finds JPEG/PNG files that builders and themes use outside the Library, and creates validated WebP siblings. That gives small and shared-host sites a practical no-cloud way to modernise hidden images without rewriting the entire front-end response.

= What it does =

* **Compress on upload** — every new JPEG and PNG is optimised automatically.
* **Bulk optimise** — compress your entire existing Media Library with a one-click batch tool that runs in the background and shows you each file as it works.
* **Scan entire uploads folder** — generate WebP for *every* JPEG/PNG in your uploads folder, including page-builder and theme images that are not in the Media Library.
* **WebP conversion** — generates WebP copies of your images (where your server supports it).
* **Automatic WebP delivery** — serves WebP to browsers that support it using the standard `<picture>` element, so it works on Apache, Nginx, LiteSpeed and IIS without any server config or `.htaccess` edits. Browsers that don't support WebP get the original automatically.
* **Builder/theme image scout** — the uploads-folder scanner creates WebP copies for images that page builders, themes, or imports placed outside the Media Library.
* **Safe & reversible** — optionally keeps protected untouched backups so you can restore originals with one click.
* **Honest about your server** — a settings panel shows exactly what your host can do (GD, Imagick, WebP support) so there are no surprises.

= Why "local"? =

Everything happens on your own server:

* No external API or third-party service.
* No `exec()` / shell access required (so it works where `cwebp`, `jpegoptim`, etc. are blocked).
* No data sent anywhere. Good for privacy and for sites behind a firewall.

= Privacy =

This plugin does not send your images or any data to any external service. All processing happens locally on your server.

== Installation ==

1. Upload the `itboffins-image-scout` folder to `/wp-content/plugins/`, or install it through the **Plugins → Add New** screen.
2. Activate the plugin.
3. Go to **Settings → Image Scout** to review your server's capabilities and adjust quality settings.
4. New uploads are optimised automatically. Administrators can compress existing images under **Media → Bulk Optimise**.

== Frequently Asked Questions ==

= Does this send my images to a third-party service? =

No. All compression and WebP conversion happens on your own server using PHP's GD or Imagick libraries. Nothing is uploaded anywhere.

= My server says WebP is "Not available". What does that mean? =

Your server's image library was compiled without WebP support. The plugin will still compress your JPEGs and PNGs — it just won't be able to generate WebP files until your host enables WebP in GD or Imagick. Ask your host, or contact us.

= Some of my images still load as JPEG/PNG. Why? =

The plugin rewrites images that pass through WordPress's normal content and featured-image filters. Some builder, theme, CDN, or CSS background images may not pass through those filters, so use **Media → Bulk Optimise → Scan entire uploads folder** to make WebP files for hidden upload-folder images too. CSS background images cannot use the `<picture>` element and so cannot be swapped this way.

= Some images don't have a WebP version at all =

The bulk optimiser only processes images in your **Media Library**. Page-builder template images and theme images often live in your uploads folder without being Library attachments, so they never get a WebP. Use **Media → Bulk Optimise → Scan entire uploads folder** to generate WebP for every image file on disk, Library or not.

= Will this break my images if the quality is too low? =

You can set the JPEG and WebP quality on the settings page. The default (82 / 80) is visually lossless for most photos. Optional backups can be enabled if you want one-click restore; new installs keep backups off by default.

= Does it work with my CDN / page cache? =

WebP is delivered with a standard `<picture>` element in your HTML, so it is compatible with most page caches. If your CDN rewrites image URLs to a different domain, automatic WebP delivery may not apply to those URLs.

= Are PNGs compressed too? =

PNGs are kept lossless (true lossy PNG compression requires external tools that aren't available on most hosts). The biggest win for PNGs comes from the WebP copy, which is usually much smaller.

== Screenshots ==

1. Settings page showing your server's image capabilities.
2. The bulk optimiser compressing the Media Library.
3. The Image Scout column in the Media Library.

== Changelog ==

= 1.0.7 =
* Removed the full-response rewrite mode to align with WordPress.org review guidance while keeping the uploads-folder scout workflow.
* Updated settings and readme copy so hidden builder/theme images are handled through the uploads-folder scan.

= 1.0.6 =
* Reworked the settings page with plain-English benefits, clearer recommendations, and friendlier host readiness messages.

= 1.0.5 =
* Renamed to ITBoffins Image Scout with matching slug and text domain.
* Strengthened WordPress-safe prefixes for classes, functions, options, AJAX actions, script/style handles, and admin selectors.
* Added a safer review-ready WebP delivery path without persistent front-end response rewriting.

= 1.0.4 =
* Security hardening: site-wide bulk optimisation and whole-uploads scans now require administrator capability, while single-image actions require permission to edit the selected attachment.
* Security hardening: original backups are off by default for new installs and, when enabled, are stored in a randomised uploads subfolder with deny files. Legacy backups remain restorable.

= 1.0.3 =
* New: **Scan entire uploads folder** — generate WebP for every JPEG/PNG on disk, including page-builder (Elementor/Divi) and theme images that are not in the Media Library. Memory-safe, resumable, and skips the originals backup folder.
* New: redesigned admin screens with IT Boffins branding.
* The folder scan reuses the same validated, palette-safe WebP encoder, so it can also repair previously broken WebP files.

= 1.0.2 =
* New: optional front-end WebP delivery for images that pass through WordPress content and image filters.
* Fixed: WebP files that exist on disk were not served when a page linked images with a different www/non-www or http/https prefix. Delivery now matches on the URL path, so those variants resolve correctly.
* Improved: the bulk optimiser now shows the filename of each image as it is processed, instead of just a running count.
* Language: interface now uses British English spelling throughout.

= 1.0.1 =
* Fixed: broken images (e.g. logos) caused by corrupt WebP generated from indexed/palette PNGs on some servers. WebP output is now validated and re-encoded via a palette-safe GD path; a WebP is only ever served if it decodes correctly.
* Fixed: re-running the optimiser now repairs or removes previously broken WebP files instead of skipping them.
* Hardened: a re-compressed image is validated before it can replace the original, so a faulty encode can never corrupt your originals.

= 1.0.0 =
* Initial release: compress on upload, bulk optimiser, WebP generation, automatic `<picture>` delivery, originals backup & restore.

== Upgrade Notice ==

= 1.0.7 =
Removes the full-response rewrite mode flagged during WordPress.org review; use the uploads-folder scanner for hidden builder/theme images.

= 1.0.6 =
Settings are easier for non-technical site owners to understand, with each option explaining the practical benefit.

= 1.0.5 =
New distinctive plugin name/slug and stronger prefixes for WordPress.org review.

= 1.0.4 =
Hardens AJAX permissions and backup storage. Recommended before public directory submission.

= 1.0.3 =
Adds a whole-uploads-folder WebP scanner (catches page-builder/theme images) and a branded admin redesign.

= 1.0.2 =
Adds front-end WebP delivery and fixes WebP not serving on www/non-www URL mismatches. Recommended for all users.

= 1.0.1 =
Fixes broken images caused by corrupt WebP on some servers. Recommended for all users.

= 1.0.0 =
First release.
