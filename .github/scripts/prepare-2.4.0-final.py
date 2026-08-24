from pathlib import Path
import re


def replace_once(text, old, new, label):
    if old not in text:
        raise SystemExit('Missing patch anchor: ' + label)
    return text.replace(old, new, 1)


# Final release metadata and bootstrap.
p = Path('media-reference-inspector.php')
s = p.read_text(encoding='utf-8')
if '2.4.0-beta.2' in s:
    s = s.replace('2.4.0-beta.2', '2.4.0')
if "class-mediarefinspector-update-notice.php" not in s:
    anchor = "require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-site-audit-service.php';\n"
    s = replace_once(
        s,
        anchor,
        anchor + "require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-update-notice.php';\n",
        'update notice require',
    )
if 'MediaRefInspector_Update_Notice::register();' not in s:
    s = replace_once(
        s,
        "\t$plugin->register();\n",
        "\t$plugin->register();\n\tMediaRefInspector_Update_Notice::register();\n",
        'update notice register',
    )
p.write_text(s, encoding='utf-8')


# One-time admin update notice. Per-user state means every administrator can see it once.
notice = r'''<?php
/**
 * One-time What's New notice for Media Reference Inspector updates.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a concise update summary until the administrator opens the plugin page.
 */
class MediaRefInspector_Update_Notice {

	/**
	 * User-meta key used to remember the last version whose update notice was seen.
	 *
	 * @var string
	 */
	const USER_META_KEY = 'mediarefinspector_whats_new_notice_version';

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_init', array( __CLASS__, 'mark_seen_on_plugin_page' ) );
	}

	/**
	 * Marks the current release notice as seen when the plugin page is opened.
	 *
	 * @return void
	 */
	public static function mark_seen_on_plugin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
		$page = is_string( $page ) ? sanitize_key( $page ) : '';
		if ( 'media-reference-inspector' !== $page ) {
			return;
		}

		update_user_meta( get_current_user_id(), self::USER_META_KEY, MEDIAREFINSPECTOR_VERSION );
	}

	/**
	 * Renders a native WordPress notice after install/update until the plugin page is visited.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$seen_version = (string) get_user_meta( get_current_user_id(), self::USER_META_KEY, true );
		if ( MEDIAREFINSPECTOR_VERSION === $seen_version ) {
			return;
		}

		$url = add_query_arg(
			array(
				'page' => 'media-reference-inspector',
				'tab'  => 'scanner',
			),
			admin_url( 'upload.php' )
		);
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'Media Reference Inspector 2.4.0 is ready — see what’s new.', 'media-reference-inspector' ); ?></strong></p>
			<p><?php esc_html_e( 'New in this release: Media Impact Preview, expanded metadata and SEO/builder coverage, Media Library usage status, Site Audit Summary, JSON export, and WordPress 7.1 compatibility.', 'media-reference-inspector' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Explore the new features', 'media-reference-inspector' ); ?></a></p>
		</div>
		<?php
	}
}
'''
Path('includes/class-mediarefinspector-update-notice.php').write_text(notice, encoding='utf-8')


# Replace the beta What's New card with a stable, location-oriented summary.
p = Path('includes/class-mediarefinspector-plugin.php')
s = p.read_text(encoding='utf-8')
if "Media audit coverage expanded in 2.4" in s or "available in this test build" in s:
    pattern = re.compile(
        r"\n\t/\*\*\n\t \* Shows a compact What's New card until new feature tabs have been visited\..*?\n\tprivate function render_whats_new_panel\(\) \{.*?\n\t\}\n",
        re.S,
    )
    replacement = r'''
	/**
	 * Shows a compact What's New card until new feature tabs have been visited.
	 *
	 * @return void
	 */
	private function render_whats_new_panel() {
		if ( ! $this->is_new_feature( 'bulk' ) && ! $this->is_new_feature( 'site-audit' ) ) {
			return;
		}
		$bulk_url = add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'bulk' ), admin_url( 'upload.php' ) );
		$site_audit_url = add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'site-audit' ), admin_url( 'upload.php' ) );
		?>
		<div class="mediarefinspector-whats-new mediarefinspector-panel">
			<div><span class="mediarefinspector-new-badge"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span><strong><?php esc_html_e( 'What’s new in Media Reference Inspector 2.4.0', 'media-reference-inspector' ); ?></strong></div>
			<ul class="mediarefinspector-check-list">
				<li><strong><?php esc_html_e( 'Scanner:', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'Media Impact Preview plus expanded metadata, SEO/social, Bricks, Divi, and Beaver Builder reference checks.', 'media-reference-inspector' ); ?></li>
				<li><strong><?php esc_html_e( 'Bulk Scan:', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'Selected media IDs, evidence and file-health filters, and JSON export alongside CSV and printable HTML.', 'media-reference-inspector' ); ?></li>
				<li><strong><?php esc_html_e( 'Site Audit:', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'A bounded read-only overview of recent media references, file health, broken local URLs, and duplicate groups.', 'media-reference-inspector' ); ?></li>
				<li><strong><?php esc_html_e( 'Media Library:', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'Recent cached reference status and explicit Re-scan actions without heavy automatic page-load scans.', 'media-reference-inspector' ); ?></li>
			</ul>
			<p><a class="button" href="<?php echo esc_url( $bulk_url ); ?>"><?php esc_html_e( 'Open Bulk Scan', 'media-reference-inspector' ); ?></a> <a class="button button-primary" href="<?php echo esc_url( $site_audit_url ); ?>"><?php esc_html_e( 'Open Site Audit', 'media-reference-inspector' ); ?></a></p>
		</div>
		<?php
	}
'''
    s, count = pattern.subn('\n' + replacement, s, count=1)
    if count != 1:
        raise SystemExit('Could not replace What\'s New panel')
p.write_text(s, encoding='utf-8')


# Final readme metadata/changelog. Remove screenshot declarations until real, clean UI captures exist.
p = Path('readme.txt')
s = p.read_text(encoding='utf-8')
s = s.replace('Stable tag: 2.4.0-beta.2', 'Stable tag: 2.4.0', 1)
s = s.replace('= 2.4.0-beta.2 =', '= 2.4.0 =', 1)
s = s.replace('Updated compatibility metadata to WordPress 7.1 for beta validation.', 'Updated compatibility metadata and validation for WordPress 7.1.')
s = s.replace('Removed corrupt WordPress.org screenshot assets; real 2.4 screenshots will be captured from the tested plugin UI before publication.', 'Removed corrupt legacy WordPress.org screenshot assets so broken screenshots are no longer served.')
s = s.replace('Kept WordPress.org banner sources for re-validation and re-sync during the approved stable release.', 'Revalidated WordPress.org banner sources for stable asset re-sync.')
if '* Added a one-time WordPress admin What’s New notice for existing installs after updating to 2.4.0.' not in s:
    marker = '* Added 2.4 NEW badges and updated What’s New guidance.\n'
    s = replace_once(
        s,
        marker,
        marker + '* Added a one-time WordPress admin What’s New notice for existing installs after updating to 2.4.0.\n',
        '2.4 changelog notice',
    )
s = re.sub(r"\n== Screenshots ==\n.*?(?=\n== Frequently Asked Questions ==)", "\n", s, count=1, flags=re.S)
p.write_text(s, encoding='utf-8')

print('Prepared Media Reference Inspector 2.4.0 final source.')
