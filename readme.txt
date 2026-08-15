=== Media Reference Inspector ===
Contributors: rejoyan9009
Tags: media library, media usage, attachments, references, admin tools
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find where Media Library items are referenced before you replace or remove them.

== Description ==

Media Reference Inspector is a read-only WordPress admin tool for checking where Media Library items are referenced.

Version 2.0.0 adds a redesigned professional workflow for both individual checks and bulk audits while preserving the plugin's safety-first behavior. It does not delete files, modify content, create database tables, track users, or call external services.

= Scanner =

Use the Scanner tab to search and filter Media Library items, review file details, and inspect one item at a time. Results are grouped by reference type with clear status information and Edit or View actions where WordPress provides them.

Supported reference checks include:

* Post, page, and custom post type content or excerpts containing the original media URL, generated image-size URLs, or the WordPress `wp-image-ID` class.
* Core Image, Gallery, Cover, Media & Text, File, Audio, and Video blocks that store the attachment ID.
* Featured images stored in WordPress core `_thumbnail_id` metadata.
* Navigation menu custom URLs matching the original media URL or generated image-size URLs.
* WordPress Site Icon and Site Logo settings.
* Active theme Custom Logo, Header Image, and Background Image settings.
* WooCommerce product gallery attachment references when WooCommerce gallery data is present.
* Elementor saved media-control references when Elementor data is present and the saved JSON confirms the attachment ID.

= Bulk Scan =

Bulk Scan can process a bounded batch of Media Library items and shows live progress plus a summary of referenced items, items with no supported references found, and items that need review.

Bulk results can be filtered and exported to CSV for audit or reporting workflows. Bulk scanning remains read-only.

= Media Library shortcut =

A Check references row action is added to the standard Media Library list so administrators can open an item directly in Media Reference Inspector.

= Important safety note =

A result of "No supported references found" does not prove that a file is unused or safe to delete. Themes, page builders, custom database tables, external systems, custom code, or plugin-specific storage can reference media in ways that are outside the supported checks.

= Privacy =

Media Reference Inspector does not send data to external services and does not add tracking, analytics, or telemetry. It stores no plugin settings or scan history.

== Installation ==

1. Install Media Reference Inspector from the WordPress Plugin Directory and activate it.
2. Go to Media > Media References.
3. Use Scanner for an individual media item or Bulk Scan for a bounded audit batch.
4. Review detected reference locations before making changes elsewhere in WordPress.

== Screenshots ==

1. Browse Media Library items from Media > Media References and choose an item to inspect.
2. Review the scan result and the safety warning when no supported references are found; the plugin remains read-only and does not change the selected media item.

== Frequently Asked Questions ==

= Does this plugin delete media files? =

No. The plugin is intentionally read-only. It does not delete, detach, rename, replace, or modify media.

= Does "No supported references found" mean the file is safe to delete? =

No. The plugin checks supported WordPress and integration locations. Other themes, plugins, builders, custom tables, external applications, or custom code may store references elsewhere.

= Which WordPress blocks are detected by attachment ID? =

Media Reference Inspector recognizes the core Image, Gallery, Cover, Media & Text, File, Audio, and Video blocks when their saved block attributes reference the selected Media Library item.

= Does the plugin detect resized image URLs? =

Yes. For image attachments, the scanner also checks generated image-size URLs recorded in WordPress attachment metadata, in addition to the primary attachment URL.

= What does WooCommerce support check? =

When WooCommerce product gallery metadata is present, the scanner can report product gallery references to the selected attachment.

= What does Elementor support check? =

When Elementor saved data is present, the scanner filters candidate content and validates saved Elementor JSON for media controls that reference the selected attachment ID.

= Does Bulk Scan change anything? =

No. Bulk Scan only reads supported reference locations. It includes progress, result filtering, and CSV export, but it does not delete or modify media or content.

= Does the plugin use an external service or API? =

No. All checks run locally in the WordPress admin area against the site's own WordPress data.

= Who can use the scanner? =

The scanner requires the `manage_options` capability because results can reveal references to content that is not publicly visible.

= Does uninstalling the plugin remove anything? =

The plugin stores no settings or scan history, so there is no plugin data to remove.

== Changelog ==

= 2.0.0 =
* Redesigned the admin experience with a WordPress-style header and Scanner, Bulk Scan, and Help navigation.
* Rebuilt the individual scanner UI with responsive media cards, filters, richer file details, grouped results, and clearer follow-up actions.
* Added bounded AJAX bulk scanning with live progress, stop control, summary counters, result filtering, and CSV export.
* Added a Check references shortcut to the standard Media Library list.
* Added WooCommerce product gallery reference detection.
* Added validated Elementor saved media-control reference detection.
* Preserved all existing standard WordPress, generated image-size URL, featured image, menu, theme, Site Icon/Site Logo, and core block reference checks.
* Preserved read-only behavior with capability checks, nonces, escaped output, and no external tracking or telemetry.

= 1.1.0 =
* Added detection for generated image-size URLs recorded in WordPress attachment metadata, including HTTP/HTTPS URL variants.
* Added attachment-ID-aware detection for core Image, Gallery, Cover, Media & Text, File, Audio, and Video blocks.
* Extended navigation menu, header image, and background image checks to recognize generated image-size URL variants.
* Added post IDs to content-reference labels for faster follow-up checks.
* Preserved the plugin's read-only behavior, existing security checks, and existing standard WordPress reference checks.

= 1.0.2 =
* Restructured direct database reads so prepared SQL is passed directly to the query methods, satisfying Plugin Check prepared-SQL analysis.
* Read search and pagination values through validated PHP input filters, removing false-positive nonce warnings for read-only navigation.
* Kept nonce verification on attachment scan actions and preserved all existing security, responsive-layout, and accessibility behavior.

= 1.0.1 =
* Fixed media-list layout on narrow screens and browsers using a desktop-sized mobile viewport.
* Prevented the search form and Find References buttons from clipping or overlapping content.
* Added a compact responsive card layout while preserving accessible table semantics.
* Bumped the asset version to refresh cached admin CSS.

= 1.0.0 =
* Initial release.
* Added read-only media reference checks for post content, excerpts, featured images, menu URLs, Site Icon, Site Logo, Custom Logo, Header Image, and Background Image.
* Added nonce-protected individual scans and administrator capability checks.
* Added explicit warnings that an empty result is not proof that a media file is unused.
* Added responsive admin tables, horizontal pagination, accessible action labels, and a file-type fallback for media without generated previews.
