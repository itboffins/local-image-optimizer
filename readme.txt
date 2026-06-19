=== Local Image Optimizer ===
Contributors: itboffins
Tags: image optimization, compress images, webp, performance, page speed
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compress your images and serve next-gen WebP using only the tools already on your server. No external API, no account, works on any host.

== Description ==

**Local Image Optimizer** makes your WordPress site faster by compressing your images and serving modern WebP versions — using only the image libraries already installed on your server (GD or Imagick).

Unlike most image plugins, there is **no external service**, no API key, no monthly quota, and no account to create. Your images never leave your server. Because it relies only on PHP's built-in image tools, it works on practically **any WordPress host**, including cheap shared hosting where shell access is disabled.

= What it does =

* **Compress on upload** — every new JPEG and PNG is optimized automatically.
* **Bulk optimize** — compress your entire existing Media Library with a one-click batch tool that runs in the background.
* **WebP conversion** — generates WebP copies of your images (where your server supports it).
* **Automatic WebP delivery** — serves WebP to browsers that support it using the standard `<picture>` element, so it works on Apache, Nginx, LiteSpeed and IIS without any server config or `.htaccess` edits. Browsers that don't support WebP get the original automatically.
* **Safe & reversible** — keeps an untouched backup of every original so you can restore it with one click.
* **Honest about your server** — a settings panel shows exactly what your host can do (GD, Imagick, WebP support) so there are no surprises.

= Why "local"? =

Everything happens on your own server:

* No external API or third-party service.
* No `exec()` / shell access required (so it works where `cwebp`, `jpegoptim`, etc. are blocked).
* No data sent anywhere. Good for privacy and for sites behind a firewall.

= Privacy =

This plugin does not send your images or any data to any external service. All processing happens locally on your server.

== Installation ==

1. Upload the `local-image-optimizer` folder to `/wp-content/plugins/`, or install it through the **Plugins → Add New** screen.
2. Activate the plugin.
3. Go to **Settings → Image Optimizer** to review your server's capabilities and adjust quality settings.
4. New uploads are optimized automatically. To compress existing images, go to **Media → Bulk Optimize**.

== Frequently Asked Questions ==

= Does this send my images to a third-party service? =

No. All compression and WebP conversion happens on your own server using PHP's GD or Imagick libraries. Nothing is uploaded anywhere.

= My server says WebP is "Not available". What does that mean? =

Your server's image library was compiled without WebP support. The plugin will still compress your JPEGs and PNGs — it just won't be able to generate WebP files until your host enables WebP in GD or Imagick. Ask your host, or contact us.

= Will this break my images if the quality is too low? =

You can set the JPEG and WebP quality on the settings page. The default (82 / 80) is visually lossless for most photos. The plugin also keeps a backup of every original (unless you turn that off), so you can restore any image with one click.

= Does it work with my CDN / page cache? =

WebP is delivered with a standard `<picture>` element in your HTML, so it is compatible with most page caches. If your CDN rewrites image URLs to a different domain, automatic WebP delivery may not apply to those URLs.

= Are PNGs compressed too? =

PNGs are kept lossless (true lossy PNG compression requires external tools that aren't available on most hosts). The biggest win for PNGs comes from the WebP copy, which is usually much smaller.

== Screenshots ==

1. Settings page showing your server's image capabilities.
2. The bulk optimizer compressing the Media Library.
3. The Optimizer column in the Media Library.

== Changelog ==

= 1.0.1 =
* Fixed: broken images (e.g. logos) caused by corrupt WebP generated from indexed/palette PNGs on some servers. WebP output is now validated and re-encoded via a palette-safe GD path; a WebP is only ever served if it decodes correctly.
* Fixed: re-running the optimizer now repairs or removes previously broken WebP files instead of skipping them.
* Hardened: a re-compressed image is validated before it can replace the original, so a faulty encode can never corrupt your originals.

= 1.0.0 =
* Initial release: compress on upload, bulk optimizer, WebP generation, automatic `<picture>` delivery, originals backup & restore.

== Upgrade Notice ==

= 1.0.0 =
First release.
