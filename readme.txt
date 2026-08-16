=== Media Reference Inspector ===
Contributors: rejoyan9009
Tags: media library, media usage, attachments, references, admin tools
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 2.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find where Media Library items are referenced before you replace or remove them.

== Description ==

Media Reference Inspector is a read-only WordPress admin tool for checking where Media Library items are referenced.

The plugin does not delete files, modify content, create database tables, track users, or send analytics. Scan results are advisory: “No supported references found” does not prove that a file is unused.

= Scanner =

Inspect one media item at a time with a professional WordPress-native workflow. Supported checks include:

* Post, page, custom post type, synced/reusable block, and other post content or excerpts containing the primary URL, generated image-size URLs, or the WordPress `wp-image-ID` class.
* Core Image, Gallery, Cover, Media & Text, File, Audio, and Video blocks that store attachment IDs.
* Featured images stored in `_thumbnail_id` metadata.
* Navigation menu custom URLs.
* WordPress Site Icon and Site Logo.
* Active theme Custom Logo, Header Image, and Background Image settings.
* Core media widgets and block widgets.
* WooCommerce product gallery and product-category thumbnail attachment IDs.
* Elementor saved media-control references when the saved JSON confirms the attachment ID.

= Bulk Scan =

Bulk Scan processes media items one at a time through bounded AJAX requests. It supports media type, upload-age, search, and maximum-item filters, live progress, result filtering, and CSV export.

= Diagnostics =

The Diagnostics tab shows the exact plugin basename and WordPress core `update_plugins` cache entry that control Dashboard update notices. Administrators can refresh WordPress plugin update metadata without changing media or content.

= Privacy =

Media Reference Inspector does not send media or site data to external analytics or telemetry services and stores no scan history.

== Installation ==

1. Install Media Reference Inspector from the WordPress Plugin Directory, or upload an approved test ZIP.
2. Activate the plugin.
3. Go to Media > Media References.
4. Use Scanner for one media item, Bulk Scan for an audit batch, or Diagnostics for update/status information.

== Screenshots ==

1. Browse Media Library items from Media > Media References and choose an item to inspect.
2. Review the scan result and safety warning when no supported references are found.

== Frequently Asked Questions ==

= Does this plugin delete media files? =

No. It is intentionally read-only for media and content. The Diagnostics refresh action only refreshes WordPress core plugin-update cache data.

= Does “No supported references found” mean the file is safe to delete? =

No. Themes, builders, custom tables, external applications, caches, or custom code may store references outside the supported checks.

= Which WordPress blocks are detected by attachment ID? =

Image, Gallery, Cover, Media & Text, File, Audio, and Video blocks are detected when their saved attributes reference the selected attachment.

= Does the plugin detect resized image URLs? =

Yes. It checks generated image-size URLs recorded in WordPress attachment metadata as well as the primary attachment URL.

= What does the Diagnostics tab check? =

It shows the installed plugin version, actual plugin basename, WordPress/PHP versions, the version WordPress core last checked, and whether the `update_plugins` transient currently contains an update response for this exact plugin file.

= Who can use the scanner? =

The scanner requires the `manage_options` capability because results may reveal references to non-public content. Refreshing update metadata also requires the `update_plugins` capability.

== Changelog ==

= 2.1.0 =
* Added Diagnostics with exact plugin-basename and WordPress core update-cache visibility plus a nonce-protected update-status refresh action.
* Added core media widget and block-widget reference detection.
* Added WooCommerce product-category thumbnail detection.
* Added a scanner coverage summary for core, widgets, WooCommerce, and Elementor checks.
* Added bulk upload-age filtering and increased bounded bulk batches up to 250 items.
* Expanded CSV exports with media URL, file size, and upload date.
* Added keyboard focus and reduced-motion accessibility polish.

= 2.0.0 =
* Redesigned the admin experience with a professional WordPress-native header and Scanner, Bulk Scan, and Help navigation.
* Added responsive media cards, grouped scan results, clearer status states, and follow-up Edit/View actions.
* Added bounded AJAX bulk scanning, live progress, result filtering, and CSV export.
* Added a Media Library “Check references” row action.
* Added WooCommerce product gallery detection.
* Added Elementor saved media-control detection with JSON validation.
* Preserved all 1.1.0 reference checks and read-only behavior.

= 1.1.0 =
* Added generated image-size URL detection and core media-block attachment ID detection.
* Extended menu, header, and background checks to generated URL variants.
* Added post IDs to content-reference labels.

= 1.0.2 =
* Improved prepared SQL analysis and validated read-only navigation inputs.

= 1.0.1 =
* Improved responsive media-list layout and mobile usability.

= 1.0.0 =
* Initial release with read-only reference checks for standard WordPress content and settings.
