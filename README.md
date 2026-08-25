# Media Reference Inspector

Media Reference Inspector is a lightweight, read-only WordPress admin tool that helps site administrators find where Media Library items are referenced before replacing or removing them.

**Official WordPress.org page:** https://wordpress.org/plugins/media-reference-inspector/

## Current release

- Version: `2.4.1`
- WordPress.org slug: `media-reference-inspector`
- Requires WordPress: `6.2+`
- Requires PHP: `7.4+`
- Tested up to: `7.1`
- License: `GPL-2.0-or-later`

## 2.4.1 directory presentation maintenance

- Adds five polished, privacy-safe WordPress.org screenshots using real plugin admin UI.
- Adds matching screenshot captions for Scanner, Bulk Scan, Page Audit, Help, and Broken URLs.
- Preserves the scanner and audit behavior shipped in 2.4.0.

## Highlights in 2.4

- Media Impact Preview on single-item scans.
- Bounded generic media-like post, term, and selected option metadata validation.
- Known Yoast SEO and Rank Math social-image metadata checks.
- Validated Bricks, Divi, and Beaver Builder media-reference coverage.
- Media Library cached reference status/counts with explicit Re-scan actions.
- Bounded Site Audit Summary for recent Media Library items.
- JSON export alongside CSV and printable HTML reports.
- Improved Bulk Scan evidence/source and file-health filtering.
- Per-user NEW badges, a clearer in-plugin What's New panel, and a one-time admin What's New notice after updating.
- WordPress 7.1 compatibility metadata and validation.
- Existing Scanner, Broken URLs, Page & Post Audit, exact Duplicate Finder, ACF/WooCommerce/Elementor/widget checks, media file health, and support form remain supported.

## Safety-first behavior

The plugin is intentionally read-only for media and content. It does not:

- Delete, detach, rename, replace, or modify media files.
- Modify post or page content.
- Automatically repair broken URLs.
- Create custom database tables.
- Send scan data to external services.
- Add tracking, analytics, or telemetry.

A result of **No supported references found** does not prove that a file is unused. Themes, page builders, custom database tables, external systems, custom code, or plugin-specific storage can reference media in ways that are outside the plugin's supported checks.

The plugin may keep short-lived local scan-status cache data in WordPress so recent on-demand scan status can be shown without running a heavy scan on every Media Library page load.

## Installation

The recommended installation method is through the WordPress Plugin Directory:

1. In WordPress admin, go to **Plugins > Add New**.
2. Search for **Media Reference Inspector**.
3. Install and activate the plugin.
4. Go to **Media > Media References**.
5. Use **Scanner** for one attachment, **Bulk Scan** for a bounded audit batch, **Broken URLs** for missing local uploads files referenced in content, **Page Audit** for post/page media review, **Duplicates** for exact-file duplicate review, or **Site Audit** for a bounded recent-media overview.

## Support and feedback

The plugin includes an explicit Help-tab support form for bug reports, feature requests, and questions. Nothing is sent until an administrator submits the form, and delivery uses the site's configured WordPress mail system.

The WordPress.org support forum is also available:

https://wordpress.org/support/plugin/media-reference-inspector/

Development issues and feature requests may be opened in this GitHub repository.

## Development

The GitHub repository is the development source. WordPress.org is the canonical distribution channel for stable releases.

WordPress.org-specific icons, banners, and screenshots live in `.wordpress-org/` and are excluded from the installable plugin package. Release validation checks the plugin version, stable tag, PHP syntax, JavaScript syntax, and WordPress SQL/nonce/i18n checks before publishing to the WordPress.org SVN repository.
