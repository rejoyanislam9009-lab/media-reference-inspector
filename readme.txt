=== Media Reference Inspector ===
Contributors: rejoyan9009
Tags: media library, media usage, attachments, references, admin tools
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find where a Media Library item is referenced in standard WordPress content before you replace or remove it.

== Description ==

Media Reference Inspector is a small, read-only admin tool for checking where an individual Media Library item is referenced.

The plugin focuses on standard WordPress locations and intentionally does not delete files, change content, create database tables, track users, or call external services.

Supported reference checks in version 1.0.2:

* Post, page, and custom post type content or excerpts that contain the media URL or WordPress `wp-image-ID` class.
* Featured images stored with WordPress core `_thumbnail_id` metadata.
* Navigation menu custom URLs that exactly match the media URL.
* WordPress Site Icon and Site Logo settings.
* Active theme Custom Logo, Header Image, and Background Image settings.

The results are advisory. A result of "no references found" does not prove that a file is unused. Themes, page builders, custom database tables, external systems, custom code, or plugin-specific storage can reference media in other ways.

= Privacy =

Media Reference Inspector does not send data to external services and does not add tracking or analytics. It stores no plugin settings or scan history.

== Installation ==

1. Upload the `media-reference-inspector` folder to `/wp-content/plugins/`, or install the ZIP through Plugins > Add New > Upload Plugin.
2. Activate Media Reference Inspector.
3. Go to Media > Media References.
4. Choose a media item and select "Find References".

== Frequently Asked Questions ==

= Does this plugin delete media files? =

No. The plugin is intentionally read-only. It does not delete, detach, rename, replace, or modify media.

= Does "no references found" mean the file is safe to delete? =

No. The plugin checks a focused set of standard WordPress locations. Custom builders, plugins, themes, custom tables, external applications, and custom code may store references elsewhere.

= Does the plugin use an external service or API? =

No. All checks run locally in the WordPress admin area against the site's own WordPress data.

= Who can use the scanner? =

Version 1.0.2 requires the `manage_options` capability because scan results can reveal references to content that is not publicly visible.

= Does uninstalling the plugin remove anything? =

The plugin stores no settings or scan history, so there is no plugin data to remove.

== Changelog ==

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
