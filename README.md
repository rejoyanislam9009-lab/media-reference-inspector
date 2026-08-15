# Media Reference Inspector

Media Reference Inspector is a lightweight, read-only WordPress admin tool that helps site administrators find where Media Library items are referenced before replacing or removing them.

**Official WordPress.org page:** https://wordpress.org/plugins/media-reference-inspector/

## Current release

- Version: `2.0.0`
- WordPress.org slug: `media-reference-inspector`
- Requires WordPress: `6.2+`
- Requires PHP: `7.4+`
- License: `GPL-2.0-or-later`

## Highlights in 2.0.0

- Professional WordPress-style admin header and Scanner / Bulk Scan / Help navigation.
- Responsive media cards, filters, richer file details, and grouped scan results.
- Bounded AJAX bulk scanning with progress, stop control, result filters, and CSV export.
- Media Library row action for opening an attachment directly in the inspector.
- WooCommerce product gallery reference detection.
- Elementor saved media-control reference detection with JSON validation.
- Existing core WordPress, generated image-size URL, block, featured image, menu, site icon/logo, and theme checks remain supported.

## Safety-first behavior

The plugin is intentionally read-only. It does not:

- Delete, detach, rename, replace, or modify media files.
- Modify post or page content.
- Create custom database tables.
- Store scan history or plugin settings.
- Send data to external services.
- Add tracking, analytics, or telemetry.

A result of **No supported references found** does not prove that a file is unused. Themes, page builders, custom database tables, external systems, custom code, or plugin-specific storage can reference media in ways that are outside the plugin's supported checks.

## Installation

The recommended installation method is through the WordPress Plugin Directory:

1. In WordPress admin, go to **Plugins > Add New**.
2. Search for **Media Reference Inspector**.
3. Install and activate the plugin.
4. Go to **Media > Media References**.
5. Use **Scanner** for one attachment or **Bulk Scan** for a bounded audit batch.

## Support and feedback

For normal support questions, bug reports, and usage help, use the WordPress.org support forum:

https://wordpress.org/support/plugin/media-reference-inspector/

Development issues and feature requests may also be opened in this GitHub repository.

## Development

The GitHub repository is the development source. WordPress.org is the canonical distribution channel for stable releases.

WordPress.org-specific icons, banners, and screenshots live in `.wordpress-org/` and are excluded from the plugin ZIP. Release validation checks the plugin version, stable tag, PHP syntax, and JavaScript syntax before publishing to the WordPress.org SVN repository.

The deployment workflow uses the WordPress.org SVN username `rejoyan9009` and one GitHub Actions repository secret named `WORDPRESS_SVN_PASSWORD`.

## Release policy

Stable releases use matching version numbers in the main plugin header, `readme.txt` Stable Tag, and the WordPress.org SVN tag. Existing published release tags are treated as immutable; changes are released under a new version number.
