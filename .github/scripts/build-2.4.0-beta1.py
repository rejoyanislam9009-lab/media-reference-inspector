from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
PLUGIN = ROOT / 'includes' / 'class-mediarefinspector-plugin.php'
JS = ROOT / 'assets' / 'js' / 'admin.js'
CSS = ROOT / 'assets' / 'css' / 'admin.css'
README = ROOT / 'readme.txt'


def replace_once(text, old, new, label):
    if old not in text:
        raise RuntimeError(f'missing patch anchor: {label}')
    return text.replace(old, new, 1)


plugin = PLUGIN.read_text()

plugin = replace_once(
    plugin,
    "\t\t\t\t} elseif ( 'broken' === $tab ) {\n\t\t\t\t\t$this->render_broken_tab();\n\t\t\t\t} elseif ( 'help' === $tab ) {",
    "\t\t\t\t} elseif ( 'broken' === $tab ) {\n\t\t\t\t\t$this->render_broken_tab();\n\t\t\t\t} elseif ( 'site-audit' === $tab ) {\n\t\t\t\t\t$this->render_site_audit_tab();\n\t\t\t\t} elseif ( 'help' === $tab ) {",
    'site audit routing',
)

plugin = replace_once(
    plugin,
    "\t\t\t'broken'     => __( 'Broken URLs', 'media-reference-inspector' ),\n\t\t\t'help'       => __( 'Help', 'media-reference-inspector' ),",
    "\t\t\t'broken'     => __( 'Broken URLs', 'media-reference-inspector' ),\n\t\t\t'site-audit' => __( 'Site Audit', 'media-reference-inspector' ),\n\t\t\t'help'       => __( 'Help', 'media-reference-inspector' ),",
    'site audit tab',
)

plugin = replace_once(
    plugin,
    "\t\t\t\t<span><strong><?php esc_html_e( 'ACF', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Confirmed Image, File and Gallery field references', 'media-reference-inspector' ); ?></span>\n\t\t\t</div>",
    "\t\t\t\t<span><strong><?php esc_html_e( 'ACF', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Confirmed Image, File and Gallery field references', 'media-reference-inspector' ); ?></span>\n\t\t\t\t<span><strong><?php esc_html_e( 'Metadata', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Validated media-like post, term and option metadata', 'media-reference-inspector' ); ?></span>\n\t\t\t\t<span><strong><?php esc_html_e( 'Builders', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Bricks, Divi and Beaver Builder saved media data', 'media-reference-inspector' ); ?></span>\n\t\t\t</div>",
    'coverage strip',
)

plugin = replace_once(
    plugin,
    "\t\t\t\t\t<button type=\"button\" class=\"button\" id=\"mediarefinspector-export-html\" disabled><?php esc_html_e( 'Printable HTML report', 'media-reference-inspector' ); ?></button>",
    "\t\t\t\t\t<button type=\"button\" class=\"button\" id=\"mediarefinspector-export-html\" disabled><?php esc_html_e( 'Printable HTML report', 'media-reference-inspector' ); ?></button>\n\t\t\t\t\t<button type=\"button\" class=\"button\" id=\"mediarefinspector-export-json\" disabled><?php esc_html_e( 'Export JSON', 'media-reference-inspector' ); ?></button>",
    'json button',
)

plugin = replace_once(
    plugin,
    "\t\t<?php $this->render_file_health( $attachment_id ); ?>",
    "\t\t<?php $this->render_media_impact_preview( $attachment_id, $usages ); ?>\n\t\t<?php $this->render_file_health( $attachment_id ); ?>",
    'impact preview hook',
)

plugin = replace_once(
    plugin,
    "\t\t\t\t\t\t\t<p><?php echo esc_html( $media['meta'] ); ?></p>",
    "\t\t\t\t\t\t\t<p><?php echo esc_html( $media['meta'] ); ?></p>\n\t\t\t\t\t\t\t<?php $cached_status = get_transient( 'mediarefinspector_scan_status_' . absint( $attachment_id ) ); ?>\n\t\t\t\t\t\t\t<?php if ( is_array( $cached_status ) && ! empty( $cached_status['status'] ) ) : ?>\n\t\t\t\t\t\t\t\t<p class=\"mediarefinspector-cached-status\"><span class=\"dashicons dashicons-clock\" aria-hidden=\"true\"></span><?php echo esc_html( 'referenced' === $cached_status['status'] ? sprintf( __( 'Recent scan: %d references', 'media-reference-inspector' ), isset( $cached_status['count'] ) ? absint( $cached_status['count'] ) : 0 ) : __( 'Recent scan: no supported references', 'media-reference-inspector' ) ); ?></p>\n\t\t\t\t\t\t\t<?php endif; ?>",
    'cached media status',
)

row_pattern = re.compile(r"\tpublic function add_media_row_action\( \$actions, \$post, \$detached \) \{.*?\n\t\treturn \$actions;\n\t\}\n", re.S)
row_match = row_pattern.search(plugin)
if not row_match:
    raise RuntimeError('missing patch anchor: media row action method')
row_method = r'''	public function add_media_row_action( $actions, $post, $detached ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required filter signature.
		if ( ! current_user_can( 'manage_options' ) || ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return $actions;
		}

		$cached = get_transient( 'mediarefinspector_scan_status_' . absint( $post->ID ) );
		$url    = $this->get_scan_url( $post->ID, '', 1 );
		$label  = is_array( $cached ) ? __( 'Re-scan references', 'media-reference-inspector' ) : __( 'Check references', 'media-reference-inspector' );
		$actions['mediarefinspector'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $label ) );

		if ( is_array( $cached ) && ! empty( $cached['status'] ) ) {
			$count = isset( $cached['count'] ) ? absint( $cached['count'] ) : 0;
			$status_label = 'referenced' === $cached['status']
				? sprintf( _n( 'Cached: %d reference', 'Cached: %d references', $count, 'media-reference-inspector' ), $count )
				: __( 'Cached: Needs review', 'media-reference-inspector' );
			$actions['mediarefinspector_status'] = '<span class="mediarefinspector-row-status">' . esc_html( $status_label ) . '</span>';
		}

		return $actions;
	}
'''
plugin = plugin[:row_match.start()] + row_method + plugin[row_match.end():]

whats_pattern = re.compile(r"\tprivate function render_whats_new_panel\(\) \{.*?\n\t\}\n\n\t/\*\*\n\t \* Renders page/post media audit results", re.S)
whats_match = whats_pattern.search(plugin)
if not whats_match:
    raise RuntimeError('missing patch anchor: whats new method')
whats_method = r'''	private function render_whats_new_panel() {
		if ( ! $this->is_new_feature( 'bulk' ) && ! $this->is_new_feature( 'site-audit' ) ) {
			return;
		}
		?>
		<div class="mediarefinspector-whats-new mediarefinspector-panel">
			<div><span class="mediarefinspector-new-badge"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span><strong><?php esc_html_e( 'Media audit coverage expanded in 2.4', 'media-reference-inspector' ); ?></strong></div>
			<p><?php esc_html_e( 'New metadata, SEO/social and builder checks, Media Impact Preview, cached usage counts, Site Audit and JSON export are available in this test build.', 'media-reference-inspector' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders page/post media audit results'''
plugin = plugin[:whats_match.start()] + whats_method + plugin[whats_match.end():]

new_pattern = re.compile(r"\tprivate function is_new_feature\( \$feature \) \{.*?\n\t\}\n\n\t/\*\*\n\t \* Marks the current 2\.3 feature tab as seen.*?\n\tprivate function mark_feature_seen\( \$feature \) \{.*?\n\t\}\n", re.S)
new_match = new_pattern.search(plugin)
if not new_match:
    raise RuntimeError('missing patch anchor: new feature methods')
new_methods = r'''	private function is_new_feature( $feature ) {
		if ( ! in_array( $feature, array( 'bulk', 'site-audit' ), true ) ) {
			return false;
		}
		$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_240', true );
		$seen = is_array( $seen ) ? $seen : array();
		return empty( $seen[ $feature ] );
	}

	/**
	 * Marks a 2.4 feature tab as seen after rendering.
	 *
	 * @param string $feature Feature key.
	 * @return void
	 */
	private function mark_feature_seen( $feature ) {
		if ( ! in_array( $feature, array( 'bulk', 'site-audit' ), true ) ) {
			return;
		}
		$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_240', true );
		$seen = is_array( $seen ) ? $seen : array();
		$seen[ $feature ] = 1;
		update_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_240', $seen );
	}
'''
plugin = plugin[:new_match.start()] + new_methods + plugin[new_match.end():]

plugin = replace_once(
    plugin,
    "\t\t\t\t\t\t<li><?php esc_html_e( 'Elementor media-control data saved in Elementor JSON', 'media-reference-inspector' ); ?></li>",
    "\t\t\t\t\t\t<li><?php esc_html_e( 'Elementor media-control data saved in Elementor JSON', 'media-reference-inspector' ); ?></li>\n\t\t\t\t\t\t<li><?php esc_html_e( 'Validated media-like post, term, and option metadata', 'media-reference-inspector' ); ?></li>\n\t\t\t\t\t\t<li><?php esc_html_e( 'Known Yoast SEO and Rank Math social-image metadata', 'media-reference-inspector' ); ?></li>\n\t\t\t\t\t\t<li><?php esc_html_e( 'Validated Bricks, Divi, and Beaver Builder media data', 'media-reference-inspector' ); ?></li>",
    'help integration list',
)

methods = r'''
	/**
	 * Renders a read-only impact summary from the already-computed scan results.
	 *
	 * @param int                              $attachment_id Attachment ID.
	 * @param array<int, array<string, mixed>> $usages        Scan usages.
	 * @return void
	 */
	private function render_media_impact_preview( $attachment_id, $usages ) {
		$categories = array();
		foreach ( is_array( $usages ) ? $usages : array() as $usage ) {
			$category = ! empty( $usage['source_category'] ) ? sanitize_key( $usage['source_category'] ) : 'other';
			$categories[ $category ] = isset( $categories[ $category ] ) ? $categories[ $category ] + 1 : 1;
		}
		$labels = array(
			'core-id' => __( 'Exact ID / blocks', 'media-reference-inspector' ),
			'core-url' => __( 'URLs / content', 'media-reference-inspector' ),
			'integration' => __( 'Integrations / builders', 'media-reference-inspector' ),
			'metadata' => __( 'Metadata / settings', 'media-reference-inspector' ),
			'widget' => __( 'Widgets', 'media-reference-inspector' ),
			'setting' => __( 'WordPress settings', 'media-reference-inspector' ),
			'other' => __( 'Other supported evidence', 'media-reference-inspector' ),
		);
		?>
		<div class="mediarefinspector-panel mediarefinspector-impact-preview">
			<div class="mediarefinspector-section-heading mediarefinspector-section-heading-split"><div><h3><?php esc_html_e( 'Media Impact Preview', 'media-reference-inspector' ); ?> <span class="mediarefinspector-new-badge"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span></h3><p><?php esc_html_e( 'Read-only summary of supported places that could be affected if this media item is replaced or removed.', 'media-reference-inspector' ); ?></p></div><span class="mediarefinspector-impact-total"><?php echo esc_html( sprintf( _n( '%d supported reference', '%d supported references', count( $usages ), 'media-reference-inspector' ), count( $usages ) ) ); ?></span></div>
			<?php if ( empty( $categories ) ) : ?><p class="description"><?php esc_html_e( 'No supported impact was found. This is not proof that the file is unused.', 'media-reference-inspector' ); ?></p><?php else : ?><div class="mediarefinspector-impact-grid"><?php foreach ( $categories as $category => $count ) : ?><span><strong><?php echo esc_html( isset( $labels[ $category ] ) ? $labels[ $category ] : $labels['other'] ); ?></strong><?php echo esc_html( (string) $count ); ?></span><?php endforeach; ?></div><?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the explicit bounded site-audit summary.
	 *
	 * @return void
	 */
	private function render_site_audit_tab() {
		$limit = isset( $_GET['site_audit_limit'] ) ? absint( $_GET['site_audit_limit'] ) : 50;
		if ( ! in_array( $limit, array( 25, 50, 100 ), true ) ) { $limit = 50; }
		$run = isset( $_GET['run_site_audit'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['run_site_audit'] ) );
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-site-audit-heading">
			<div class="mediarefinspector-section-heading"><div><h2 id="mediarefinspector-site-audit-heading"><?php esc_html_e( 'Site Audit Summary', 'media-reference-inspector' ); ?> <span class="mediarefinspector-new-badge"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span></h2><p><?php esc_html_e( 'Run a bounded, manual audit of recent Media Library items. It is read-only and does not repair, delete, replace, or detach anything.', 'media-reference-inspector' ); ?></p></div></div>
			<form method="get" class="mediarefinspector-panel mediarefinspector-filter-form"><input type="hidden" name="page" value="media-reference-inspector" /><input type="hidden" name="tab" value="site-audit" /><input type="hidden" name="run_site_audit" value="1" /><?php wp_nonce_field( 'mediarefinspector_run_site_audit', 'mediarefinspector_site_audit_nonce', false ); ?><div class="mediarefinspector-field"><label for="mediarefinspector-site-audit-limit"><?php esc_html_e( 'Recent media items', 'media-reference-inspector' ); ?></label><select id="mediarefinspector-site-audit-limit" name="site_audit_limit"><option value="25" <?php selected( $limit, 25 ); ?>>25</option><option value="50" <?php selected( $limit, 50 ); ?>>50</option><option value="100" <?php selected( $limit, 100 ); ?>>100</option></select></div><div class="mediarefinspector-field mediarefinspector-field-action"><button class="button button-primary" type="submit"><?php esc_html_e( 'Run read-only site audit', 'media-reference-inspector' ); ?></button></div></form>
			<p class="description mediarefinspector-safety-note"><span class="dashicons dashicons-performance" aria-hidden="true"></span><?php esc_html_e( 'Performance mode: the audit is deliberately capped at 100 recent media items and reuses the scanner’s short-lived status cache.', 'media-reference-inspector' ); ?></p>
			<?php
			if ( $run ) {
				$nonce = isset( $_GET['mediarefinspector_site_audit_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_site_audit_nonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'mediarefinspector_run_site_audit' ) ) { $this->render_notice( __( 'The site audit request could not be verified.', 'media-reference-inspector' ), 'error' ); return; }
				$result = ( new MediaRefInspector_Site_Audit_Service() )->run( $this->scanner, $limit );
				?>
				<div class="mediarefinspector-summary-grid mediarefinspector-site-audit-summary"><div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Scanned', 'media-reference-inspector' ); ?></span><strong><?php echo esc_html( (string) $result['scanned'] ); ?></strong></div><div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></span><strong><?php echo esc_html( (string) $result['referenced'] ); ?></strong></div><div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Potential unused review', 'media-reference-inspector' ); ?></span><strong><?php echo esc_html( (string) $result['unreferenced'] ); ?></strong></div><div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'File health review', 'media-reference-inspector' ); ?></span><strong><?php echo esc_html( (string) $result['health_review'] ); ?></strong></div><div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Broken local URLs', 'media-reference-inspector' ); ?></span><strong><?php echo esc_html( (string) $result['broken_urls'] ); ?></strong></div><div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Duplicate groups', 'media-reference-inspector' ); ?></span><strong><?php echo esc_html( (string) $result['duplicate_groups'] ); ?></strong></div></div>
				<div class="mediarefinspector-table-wrap"><table class="widefat striped mediarefinspector-table"><thead><tr><th><?php esc_html_e( 'Media', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'References', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'File health', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'Action', 'media-reference-inspector' ); ?></th></tr></thead><tbody><?php foreach ( array_slice( $result['items'], 0, 25 ) as $item ) : ?><tr><td><strong><?php echo esc_html( $item['title'] ? $item['title'] : sprintf( __( 'Media #%d', 'media-reference-inspector' ), $item['id'] ) ); ?></strong><br><code><?php echo esc_html( $item['filename'] ); ?></code></td><td><?php echo esc_html( (string) $item['references'] ); ?></td><td><span class="mediarefinspector-health-pill is-<?php echo esc_attr( $item['health'] ); ?>"><?php echo esc_html( 'healthy' === $item['health'] ? __( 'Healthy', 'media-reference-inspector' ) : __( 'Needs review', 'media-reference-inspector' ) ); ?></span></td><td><?php if ( $item['edit_url'] ) : ?><a class="button button-small" href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php esc_html_e( 'Review media', 'media-reference-inspector' ); ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
				<?php
			} else {
				?><div class="mediarefinspector-empty-state"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span><h3><?php esc_html_e( 'Ready for a bounded site audit', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Choose a batch size and run the audit when you want a current snapshot.', 'media-reference-inspector' ); ?></p></div><?php
			}
			?>
		</section>
		<?php
	}

'''
anchor = "\t/**\n\t * Gets the active tab."
if anchor not in plugin:
    raise RuntimeError('missing patch anchor: current tab method')
plugin = plugin.replace(anchor, methods + anchor, 1)

plugin = replace_once(
    plugin,
    "return in_array( $tab, array( 'scanner', 'bulk', 'audit', 'duplicates', 'broken', 'help' ), true ) ? $tab : 'scanner';",
    "return in_array( $tab, array( 'scanner', 'bulk', 'audit', 'duplicates', 'broken', 'site-audit', 'help' ), true ) ? $tab : 'scanner';",
    'current tab allowlist',
)

PLUGIN.write_text(plugin)

js = JS.read_text()
js = replace_once(js, "\tvar exportHtmlButton = document.getElementById('mediarefinspector-export-html');", "\tvar exportHtmlButton = document.getElementById('mediarefinspector-export-html');\n\tvar exportJsonButton = document.getElementById('mediarefinspector-export-json');", 'json js variable')
js = replace_once(js, "\t\tif (exportHtmlButton) { exportHtmlButton.disabled = true; }", "\t\tif (exportHtmlButton) { exportHtmlButton.disabled = true; }\n\t\tif (exportJsonButton) { exportJsonButton.disabled = true; }", 'json reset')
js = replace_once(js, "\t\tif (exportHtmlButton) { exportHtmlButton.disabled = results.length === 0; }", "\t\tif (exportHtmlButton) { exportHtmlButton.disabled = results.length === 0; }\n\t\tif (exportJsonButton) { exportJsonButton.disabled = results.length === 0; }", 'json finish')
json_fn = r'''
	function exportJson() {
		if (!results.length) { return; }
		var report = {
			tool: 'Media Reference Inspector',
			version: config.version || '',
			generatedAt: new Date().toISOString(),
			advisory: 'No supported references found does not prove that a file is unused.',
			results: results
		};
		var blob = new Blob([JSON.stringify(report, null, 2)], { type: 'application/json;charset=utf-8' });
		var link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = 'media-reference-inspector-report.json';
		document.body.appendChild(link);
		link.click();
		URL.revokeObjectURL(link.href);
		link.remove();
	}

'''
js = replace_once(js, "\tfunction exportCsv() {", json_fn + "\tfunction exportCsv() {", 'json function')
js = replace_once(js, "\tif (exportHtmlButton) { exportHtmlButton.addEventListener('click', exportHtml); }", "\tif (exportHtmlButton) { exportHtmlButton.addEventListener('click', exportHtml); }\n\tif (exportJsonButton) { exportJsonButton.addEventListener('click', exportJson); }", 'json listener')
JS.write_text(js)

# Add localized version for JSON reports.
plugin = PLUGIN.read_text()
plugin = replace_once(plugin, "\t\t\tarray(\n\t\t\t\t'ajaxUrl' => admin_url( 'admin-ajax.php' ),", "\t\t\tarray(\n\t\t\t\t'ajaxUrl' => admin_url( 'admin-ajax.php' ),\n\t\t\t\t'version' => MEDIAREFINSPECTOR_VERSION,", 'localized version')
PLUGIN.write_text(plugin)

css = CSS.read_text()
css += r'''

/* 2.4.0 audit workflow additions */
.mediarefinspector-cached-status{display:flex;align-items:center;gap:5px;margin:8px 0 0;color:#50575e;font-size:12px}.mediarefinspector-cached-status .dashicons{width:16px;height:16px;font-size:16px}.mediarefinspector-impact-preview{margin-top:16px}.mediarefinspector-impact-total{font-weight:600;white-space:nowrap}.mediarefinspector-impact-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:12px}.mediarefinspector-impact-grid span{display:flex;justify-content:space-between;gap:12px;padding:12px;border:1px solid #dcdcde;border-radius:6px;background:#fff}.mediarefinspector-site-audit-summary{grid-template-columns:repeat(6,minmax(0,1fr));margin:16px 0}.mediarefinspector-site-audit-summary .mediarefinspector-summary-card{min-width:0}.mediarefinspector-section-actions{flex-wrap:wrap}
@media (max-width:1200px){.mediarefinspector-impact-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.mediarefinspector-site-audit-summary{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:782px){.mediarefinspector-impact-grid,.mediarefinspector-site-audit-summary{grid-template-columns:1fr}.mediarefinspector-impact-total{white-space:normal}}
'''
CSS.write_text(css)

readme = README.read_text()
readme = replace_once(readme, 'Tested up to: 7.0', 'Tested up to: 7.1', 'wp compatibility')
readme = replace_once(
    readme,
    '= Media Library Status =\n\nReference checks can store a short-lived local scan-status cache for the inspected attachment so Media Library workflows can show recent on-demand reference status without running a heavy scan on every page load.\n',
    '= Media Library Status =\n\nReference checks store a short-lived local scan-status cache for inspected attachments. Media Library workflows can show a recent reference count/status and offer an explicit re-scan without running a heavy scan on every page load.\n\n= Media Impact Preview =\n\nSingle-item scans summarize supported reference categories that could be affected before a media item is replaced or removed. The preview is advisory and read-only.\n\n= Extended Metadata and Builder Coverage =\n\n2.4 adds bounded validation for media-like post meta, term meta, selected option values, known SEO/social image metadata, and supported Bricks, Divi, and Beaver Builder saved data. Candidate values are validated before a reference is reported.\n\n= Site Audit Summary =\n\nRun an explicit bounded audit of up to 100 recent Media Library items to review referenced items, potential-unused review results, file-health issues, broken local uploads URLs, and exact duplicate groups.\n',
    'description additions',
)
readme = re.sub(r"== Screenshots ==\n\n.*?\n\n== Frequently Asked Questions ==", "== Screenshots ==\n\n1. Scanner media selection and supported integration coverage.\n2. Scan result with evidence, Media Impact Preview, and file health.\n3. Bulk Scan filters, progress, results, and CSV/HTML/JSON export actions.\n4. Broken local uploads URL audit.\n5. Page & Post Audit with media and broken attachment-ID review.\n6. Site Audit Summary and duplicate/integration review tools.\n\n== Frequently Asked Questions ==", readme, flags=re.S)
changelog = """== Changelog ==\n\n= 2.4.0-beta.1 =\n* Added Media Impact Preview for already-computed single-scan results.\n* Added bounded generic media-like post meta, term meta, and selected option validation.\n* Added known Yoast SEO and Rank Math social-image metadata checks.\n* Added modular Bricks, Divi, and Beaver Builder saved-media checks with value validation.\n* Improved Media Library cached reference counts and explicit re-scan wording.\n* Added a bounded manual Site Audit Summary for up to 100 recent media items.\n* Added JSON export alongside CSV and printable HTML reports.\n* Added 2.4 NEW badges and updated What’s New guidance.\n* Updated compatibility metadata to WordPress 7.1 for beta validation.\n* Removed corrupt WordPress.org screenshot assets; real 2.4 screenshots will be captured from the tested plugin UI before publication.\n* Kept WordPress.org banner sources for re-validation and re-sync during the approved stable release.\n* Preserved read-only behavior and added stricter bounds to new metadata queries.\n\n"""
readme = replace_once(readme, '== Changelog ==\n\n', changelog, 'changelog')
README.write_text(readme)

print('2.4.0-beta.1 source patches applied')
