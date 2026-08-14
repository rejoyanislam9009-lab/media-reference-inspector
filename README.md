# Media Reference Inspector

Media Reference Inspector is a lightweight, read-only WordPress admin tool that helps site administrators find where a Media Library item is referenced before replacing or removing it.

**Official WordPress.org page:** https://wordpress.org/plugins/media-reference-inspector/

## Current release

- Version: `1.0.2`
- WordPress.org slug: `media-reference-inspector`
- Requires WordPress: `6.2+`
- Requires PHP: `7.4+`
- License: `GPL-2.0-or-later`

## What it checks

Media Reference Inspector checks common, standard WordPress reference locations, including:

- Post, page, and custom post type content or excerpts containing the media URL or a `wp-image-ID` class.
- Featured images stored in `_thumbnail_id` metadata.
- Navigation menu custom URLs that exactly match the media URL.
- WordPress Site Icon and Site Logo settings.
- Active theme Custom Logo, Header Image, and Background Image settings.

## Safety-first behavior

The plugin is intentionally read-only. It does not:

- Delete, detach, rename, replace, or modify media files.
- Modify post or page content.
- Create custom database tables.
- Store scan history or plugin settings.
- Send data to external services.
- Add tracking or analytics.

A result of **no references found** does not prove that a file is unused. Themes, page builders, custom database tables, external systems, custom code, or plugin-specific storage can reference media in ways that are outside the plugin's supported checks.

## Installation

The recommended installation method is through the WordPress Plugin Directory:

1. In WordPress admin, go to **Plugins > Add New**.
2. Search for **Media Reference Inspector**.
3. Install and activate the plugin.
4. Go to **Media > Media References**.
5. Choose a Media Library item and select **Find References**.

## Support and feedback

For normal support questions, bug reports, and usage help, use the WordPress.org support forum:

https://wordpress.org/support/plugin/media-reference-inspector/

Development issues and feature requests may also be opened in this GitHub repository.

## Development

The GitHub repository is the development source. WordPress.org is the canonical distribution channel for stable releases.

WordPress.org-specific icons and banners live in `.wordpress-org/` and are excluded from the plugin ZIP. The deployment workflow validates the plugin version, stable tag, and PHP syntax before publishing to the WordPress.org SVN repository.

The deployment workflow uses the WordPress.org SVN username `rejoyan9009` and one GitHub Actions repository secret named `WORDPRESS_SVN_PASSWORD`.

## Release policy

Stable releases use matching version numbers in the main plugin header, `readme.txt` Stable Tag, and the WordPress.org SVN tag. Existing published release tags are not modified; changes are released under a new version number.
