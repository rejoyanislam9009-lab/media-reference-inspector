=== Media Reference Inspector ===
Contributors: rejoyan9009
Tags: media library, media usage, attachments, references, admin tools
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find where a Media Library item is referenced in standard WordPress content before you replace or remove it.

== Description ==

Media Reference Inspector is a small, read-only admin tool for checking where an individual Media Library item is referenced.

The plugin focuses on standard WordPress locations and intentionally does not delete files, change content, create database tables, track users, or call external services.

Supported reference checks in version 1.1.0:

* Post, page, and custom post type content or excerpts that contain the original media URL, a generated image-size URL, or the WordPress `wp-image-ID` class.
* Core WordPress media blocks that store attachment IDs: Image, Gallery, Cover, Media & Text, File, Audio, and Video.
* Featured images stored with WordPress core `_thumbnail_id` metadata.
* Navigation menu custom URLs that exactly match the original media URL or a generated image-size URL.
* WordPress Site Icon and Site Logo settings.
* Active theme Custom Logo, Header Image, and Background Image settings, including generated image-size URL variants where applicable.

Scan results include the post ID for content references to make follow-up checks easier.

The results are advisory. A result of "no references found" does not prove that a file is unused. Themes, page builders, custom database tables, external systems, custom code, or plugin-specific storage can reference media in other ways.

= Privacy =

Media Reference Inspector does not send data to external services and does not add tracking or analytics. It stores no plugin settings or scan history.

== Installation ==

1. Upload the `media-reference-inspector` folder to `/wp-content/plugins/`, or install the ZIP through Plugins > Add New > Upload Plugin.
2. Activate Media Reference Inspector.
3. Go to Media > Media References.
4. Choose a media item and select "Find References".

== Screenshots ==

1. Browse Media Library items from Media > Media References, search the list, and choose Find References for the item you want to inspect.
2. Review the scan result and safety warning when no supported references are found; the plugin remains read-only and does not change the selected media item.

== Frequently Asked Questions ==

= Does this plugin delete media files? =

No. The plugin is intentionally read-only. It does not delete, detach, rename, replace, or modify media.

= Does "no references found" mean the file is safe to delete? =

No. The plugin checks a focused set of standard WordPress locations. Custom builders, plugins, themes, custom tables, external applications, and custom code may store references elsewhere.

= Which WordPress blocks are detected by attachment ID? =

Version 1.1.0 recognizes the core Image, Gallery, Cover, Media & Text, File, Audio, and Video blocks when their saved block attributes reference the selected Media Library item.

= Does the plugin detect resized image URLs? =

Yes. For image attachments, version 1.1.0 also checks URLs for generated image sizes recorded in WordPress attachment metadata, in addition to the main attachment URL.

= Does the plugin use an external service or API? =

No. All checks run locally in the WordPress admin area against the site's own WordPress data.

= Who can use the scanner? =

Version 1.1.0 requires the `manage_options` capability because scan results can reveal references to content that is not publicly visible.

= Does uninstalling the plugin remove anything? =

The plugin stores no settings or scan history, so there is no plugin data to remove.

== Changelog ==

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
