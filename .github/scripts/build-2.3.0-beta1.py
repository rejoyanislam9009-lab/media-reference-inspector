from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]


def read(path):
    return (ROOT / path).read_text(encoding="utf-8")


def write(path, text):
    (ROOT / path).write_text(text, encoding="utf-8")


def replace_once(path, old, new):
    text = read(path)
    if old not in text:
        raise RuntimeError(f"Required anchor not found in {path}: {old[:100]!r}")
    write(path, text.replace(old, new, 1))


def replace_all(path, old, new):
    text = read(path)
    if old not in text:
        raise RuntimeError(f"Required anchor not found in {path}: {old[:100]!r}")
    write(path, text.replace(old, new))


# ---------------------------------------------------------------------------
# Plugin bootstrap: beta version + Insights scanner, while Stable tag remains
# 2.2.0 so this test build cannot accidentally become a WordPress.org release.
# ---------------------------------------------------------------------------
replace_once(
    "media-reference-inspector.php",
    " * Version:           2.2.0",
    " * Version:           2.3.0-beta.1",
)
replace_once(
    "media-reference-inspector.php",
    "define( 'MEDIAREFINSPECTOR_VERSION', '2.2.0' );",
    "define( 'MEDIAREFINSPECTOR_VERSION', '2.3.0-beta.1' );",
)
replace_once(
    "media-reference-inspector.php",
    "require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-advanced-scanner.php';\n",
    "require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-advanced-scanner.php';\n"
    "require_once MEDIAREFINSPECTOR_PATH . 'includes/class-mediarefinspector-insights-scanner.php';\n",
)
replace_once(
    "media-reference-inspector.php",
    "$plugin = new MediaRefInspector_Plugin( new MediaRefInspector_Advanced_Scanner() );",
    "$plugin = new MediaRefInspector_Plugin( new MediaRefInspector_Insights_Scanner() );",
)


# ---------------------------------------------------------------------------
# Admin navigation and NEW state.
# ---------------------------------------------------------------------------
replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "\t\t\t\t} elseif ( 'duplicates' === $tab ) {\n\t\t\t\t\t$this->render_duplicates_tab();\n\t\t\t\t} elseif ( 'help' === $tab ) {",
    "\t\t\t\t} elseif ( 'duplicates' === $tab ) {\n\t\t\t\t\t$this->render_duplicates_tab();\n"
    "\t\t\t\t} elseif ( 'broken' === $tab ) {\n\t\t\t\t\t$this->render_broken_tab();\n"
    "\t\t\t\t} elseif ( 'help' === $tab ) {",
)
replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "\t\t\t'duplicates' => __( 'Duplicates', 'media-reference-inspector' ),\n\t\t\t'help'       => __( 'Help', 'media-reference-inspector' ),",
    "\t\t\t'duplicates' => __( 'Duplicates', 'media-reference-inspector' ),\n"
    "\t\t\t'broken'     => __( 'Broken URLs', 'media-reference-inspector' ),\n"
    "\t\t\t'help'       => __( 'Help', 'media-reference-inspector' ),",
)
replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "return in_array( $tab, array( 'scanner', 'bulk', 'audit', 'duplicates', 'help' ), true ) ? $tab : 'scanner';",
    "return in_array( $tab, array( 'scanner', 'bulk', 'audit', 'duplicates', 'broken', 'help' ), true ) ? $tab : 'scanner';",
)

old_new_methods = r'''\tprivate function is_new_feature( $feature ) {
\t\tif ( ! in_array( $feature, array( 'audit', 'duplicates' ), true ) ) { return false; }
\t\t$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_220', true );
\t\t$seen = is_array( $seen ) ? $seen : array();
\t\treturn empty( $seen[ $feature ] );
\t}

\t/**
\t * Marks the current feature tab as seen after rendering.
\t *
\t * @param string $feature Feature key.
\t * @return void
\t */
\tprivate function mark_feature_seen( $feature ) {
\t\tif ( ! in_array( $feature, array( 'audit', 'duplicates' ), true ) ) { return; }
\t\t$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_220', true );
\t\t$seen = is_array( $seen ) ? $seen : array();
\t\t$seen[ $feature ] = 1;
\t\tupdate_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_220', $seen );
\t}
'''.replace('\\t', '\t')
new_new_methods = r'''\tprivate function is_new_feature( $feature ) {
\t\tif ( ! in_array( $feature, array( 'bulk', 'broken' ), true ) ) {
\t\t\treturn false;
\t\t}
\t\t$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_230', true );
\t\t$seen = is_array( $seen ) ? $seen : array();
\t\treturn empty( $seen[ $feature ] );
\t}

\t/**
\t * Marks the current 2.3 feature tab as seen after rendering.
\t *
\t * @param string $feature Feature key.
\t * @return void
\t */
\tprivate function mark_feature_seen( $feature ) {
\t\tif ( ! in_array( $feature, array( 'bulk', 'broken' ), true ) ) {
\t\t\treturn;
\t\t}
\t\t$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_230', true );
\t\t$seen = is_array( $seen ) ? $seen : array();
\t\t$seen[ $feature ] = 1;
\t\tupdate_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_230', $seen );
\t}
'''.replace('\\t', '\t')
replace_once("includes/class-mediarefinspector-plugin.php", old_new_methods, new_new_methods)

# Remove the old 2.2 hard-coded heading badges; 2.3 badges are driven per user.
replace_all(
    "includes/class-mediarefinspector-plugin.php",
    " <span class=\"mediarefinspector-new-badge\"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span>",
    "",
)


# ---------------------------------------------------------------------------
# Scanner evidence presentation and integration coverage.
# ---------------------------------------------------------------------------
replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "\t\t\t</div>\n\n\t\t\t<?php $this->render_whats_new_panel(); ?>",
    "\t\t\t</div>\n\n\t\t\t<?php $this->render_integration_coverage(); ?>\n"
    "\t\t\t<?php $this->render_whats_new_panel(); ?>",
)

replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "\t\t\t\t\t\t\t\t<div><strong><?php echo esc_html( $usage['label'] ); ?></strong><span class=\"mediarefinspector-status-pill\"><?php echo esc_html( $usage['status'] ); ?></span></div>\n"
    "\t\t\t\t\t\t\t\t<div class=\"mediarefinspector-reference-actions\">",
    "\t\t\t\t\t\t\t\t<div><strong><?php echo esc_html( $usage['label'] ); ?></strong><span class=\"mediarefinspector-status-pill\"><?php echo esc_html( $usage['status'] ); ?></span></div>\n"
    "\t\t\t\t\t\t\t\t<?php if ( ! empty( $usage['source'] ) || ! empty( $usage['confidence'] ) ) : ?>\n"
    "\t\t\t\t\t\t\t\t\t<p class=\"mediarefinspector-evidence\"><strong><?php echo esc_html( isset( $usage['confidence'] ) ? $usage['confidence'] : __( 'High', 'media-reference-inspector' ) ); ?></strong><?php if ( ! empty( $usage['source'] ) ) : ?> · <?php echo esc_html( $usage['source'] ); ?><?php endif; ?><?php if ( ! empty( $usage['context'] ) && $usage['context'] !== $usage['label'] ) : ?><span><?php echo esc_html( $usage['context'] ); ?></span><?php endif; ?></p>\n"
    "\t\t\t\t\t\t\t\t<?php endif; ?>\n"
    "\t\t\t\t\t\t\t\t<div class=\"mediarefinspector-reference-actions\">",
)

# Cached on-demand status is shown in the Media Library row actions after a scan.
replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "\t\t$actions['mediarefinspector'] = sprintf(\n\t\t\t'<a href=\"%1$s\">%2$s</a>',\n\t\t\tesc_url( $url ),\n\t\t\tesc_html__( 'Check references', 'media-reference-inspector' )\n\t\t);\n\n\t\treturn $actions;",
    "\t\t$actions['mediarefinspector'] = sprintf(\n\t\t\t'<a href=\"%1$s\">%2$s</a>',\n\t\t\tesc_url( $url ),\n\t\t\tesc_html__( 'Check references', 'media-reference-inspector' )\n\t\t);\n"
    "\t\t$cached = get_transient( 'mediarefinspector_scan_status_' . absint( $post->ID ) );\n"
    "\t\tif ( is_array( $cached ) && ! empty( $cached['status'] ) ) {\n"
    "\t\t\t$label = 'referenced' === $cached['status'] ? __( 'Cached: Referenced', 'media-reference-inspector' ) : __( 'Cached: Needs review', 'media-reference-inspector' );\n"
    "\t\t\t$actions['mediarefinspector_status'] = '<span class=\"mediarefinspector-row-status\">' . esc_html( $label ) . '</span>';\n"
    "\t\t}\n\n\t\treturn $actions;",
)


# ---------------------------------------------------------------------------
# Bulk Scan: selected IDs, evidence/health filters, HTML report.
# ---------------------------------------------------------------------------
replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "\t\t\t\t\t<button type=\"button\" class=\"button\" id=\"mediarefinspector-export-csv\" disabled><?php esc_html_e( 'Export CSV', 'media-reference-inspector' ); ?></button>",
    "\t\t\t\t\t<button type=\"button\" class=\"button\" id=\"mediarefinspector-export-csv\" disabled><?php esc_html_e( 'Export CSV', 'media-reference-inspector' ); ?></button>\n"
    "\t\t\t\t\t<button type=\"button\" class=\"button\" id=\"mediarefinspector-export-html\" disabled><?php esc_html_e( 'Printable HTML report', 'media-reference-inspector' ); ?></button>",
)
replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "\t\t\t\t<div class=\"mediarefinspector-field mediarefinspector-field-action\">\n\t\t\t\t\t<button type=\"button\" class=\"button button-primary\" id=\"mediarefinspector-start-bulk\">",
    "\t\t\t\t<div class=\"mediarefinspector-field mediarefinspector-field-grow\">\n"
    "\t\t\t\t\t<label for=\"mediarefinspector-selected-ids\"><?php esc_html_e( 'Specific media IDs', 'media-reference-inspector' ); ?></label>\n"
    "\t\t\t\t\t<input type=\"text\" id=\"mediarefinspector-selected-ids\" inputmode=\"numeric\" placeholder=\"<?php echo esc_attr__( 'Optional: 12, 34, 56', 'media-reference-inspector' ); ?>\" />\n"
    "\t\t\t\t\t<span class=\"description\"><?php esc_html_e( 'Enter attachment IDs to scan only those items. Other search filters are ignored when IDs are supplied.', 'media-reference-inspector' ); ?></span>\n"
    "\t\t\t\t</div>\n"
    "\t\t\t\t<div class=\"mediarefinspector-field mediarefinspector-field-action\">\n\t\t\t\t\t<button type=\"button\" class=\"button button-primary\" id=\"mediarefinspector-start-bulk\">",
)

old_filter_row = "\t\t\t<div class=\"mediarefinspector-bulk-filter-row\" id=\"mediarefinspector-bulk-filter-row\" hidden>\n\t\t\t\t<div><label for=\"mediarefinspector-result-filter\"><?php esc_html_e( 'Show results', 'media-reference-inspector' ); ?></label>\n\t\t\t\t<select id=\"mediarefinspector-result-filter\"><option value=\"all\"><?php esc_html_e( 'All results', 'media-reference-inspector' ); ?></option><option value=\"referenced\"><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></option><option value=\"unreferenced\"><?php esc_html_e( 'Potential unused review', 'media-reference-inspector' ); ?></option><option value=\"error\"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option></select></div>\n\t\t\t\t<div><label for=\"mediarefinspector-result-sort\"><?php esc_html_e( 'Sort results', 'media-reference-inspector' ); ?></label>\n\t\t\t\t<select id=\"mediarefinspector-result-sort\"><option value=\"scan\"><?php esc_html_e( 'Scan order', 'media-reference-inspector' ); ?></option><option value=\"references-desc\"><?php esc_html_e( 'Most references', 'media-reference-inspector' ); ?></option><option value=\"references-asc\"><?php esc_html_e( 'Fewest references', 'media-reference-inspector' ); ?></option><option value=\"title\"><?php esc_html_e( 'Title A–Z', 'media-reference-inspector' ); ?></option></select></div>\n\t\t\t</div>"
new_filter_row = old_filter_row[:-7] + "\n\t\t\t\t<div><label for=\"mediarefinspector-source-filter\"><?php esc_html_e( 'Evidence source', 'media-reference-inspector' ); ?></label><select id=\"mediarefinspector-source-filter\"><option value=\"all\"><?php esc_html_e( 'All sources', 'media-reference-inspector' ); ?></option><option value=\"core-id\"><?php esc_html_e( 'Exact ID / block', 'media-reference-inspector' ); ?></option><option value=\"core-url\"><?php esc_html_e( 'URL / content marker', 'media-reference-inspector' ); ?></option><option value=\"integration\"><?php esc_html_e( 'Integration metadata', 'media-reference-inspector' ); ?></option><option value=\"widget\"><?php esc_html_e( 'Widget data', 'media-reference-inspector' ); ?></option><option value=\"setting\"><?php esc_html_e( 'Site/theme setting', 'media-reference-inspector' ); ?></option></select></div>\n\t\t\t\t<div><label for=\"mediarefinspector-health-filter\"><?php esc_html_e( 'File health', 'media-reference-inspector' ); ?></label><select id=\"mediarefinspector-health-filter\"><option value=\"all\"><?php esc_html_e( 'Any health', 'media-reference-inspector' ); ?></option><option value=\"healthy\"><?php esc_html_e( 'Healthy', 'media-reference-inspector' ); ?></option><option value=\"review\"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option></select></div>\n\t\t\t</div>"
replace_once("includes/class-mediarefinspector-plugin.php", old_filter_row, new_filter_row)

# Server-side selected-item workflow.
replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "\t\tif ( ! in_array( $type, array( '', 'image', 'video', 'audio', 'application' ), true ) ) {\n\t\t\t$type = '';\n\t\t}\n\n\t\t$args = array(",
    "\t\tif ( ! in_array( $type, array( '', 'image', 'video', 'audio', 'application' ), true ) ) {\n\t\t\t$type = '';\n\t\t}\n"
    "\t\t$selected_raw = isset( $_POST['selected_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_ids'] ) ) : '';\n"
    "\t\tif ( '' !== $selected_raw ) {\n"
    "\t\t\t$selected = array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[^0-9]+/', $selected_raw ) ) ) ) );\n"
    "\t\t\t$selected = array_slice( $selected, 0, $limit );\n"
    "\t\t\t$valid = array();\n"
    "\t\t\tforeach ( $selected as $candidate_id ) {\n"
    "\t\t\t\tif ( 'attachment' === get_post_type( $candidate_id ) ) { $valid[] = $candidate_id; }\n"
    "\t\t\t}\n"
    "\t\t\twp_send_json_success( array( 'ids' => $valid ) );\n"
    "\t\t}\n\n\t\t$args = array(",
)

# Bulk result evidence and file-health payload.
replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "\t\t$types  = array();\n\t\tforeach ( $usages as $usage ) {\n\t\t\tif ( ! empty( $usage['type'] ) ) {\n\t\t\t\t$types[] = (string) $usage['type'];\n\t\t\t}\n\t\t}\n\n\t\t$edit_attachment = get_edit_post_link( $attachment_id, 'raw' );",
    "\t\t$types      = array();\n\t\t$sources    = array();\n\t\t$confidence = array();\n"
    "\t\tforeach ( $usages as $usage ) {\n"
    "\t\t\tif ( ! empty( $usage['type'] ) ) { $types[] = (string) $usage['type']; }\n"
    "\t\t\tif ( ! empty( $usage['source_category'] ) ) { $sources[] = sanitize_key( $usage['source_category'] ); }\n"
    "\t\t\tif ( ! empty( $usage['confidence'] ) ) { $confidence[] = (string) $usage['confidence']; }\n"
    "\t\t}\n"
    "\t\t$health = ( new MediaRefInspector_Audit_Service() )->get_file_health( $attachment_id );\n\n"
    "\t\t$edit_attachment = get_edit_post_link( $attachment_id, 'raw' );",
)
replace_once(
    "includes/class-mediarefinspector-plugin.php",
    "\t\t\t\t'referenceTypes' => array_values( array_unique( $types ) ),\n\t\t\t\t'status'         => empty( $usages ) ? 'unreferenced' : 'referenced',",
    "\t\t\t\t'referenceTypes'   => array_values( array_unique( $types ) ),\n"
    "\t\t\t\t'sourceCategories' => array_values( array_unique( $sources ) ),\n"
    "\t\t\t\t'confidence'       => array_values( array_unique( $confidence ) ),\n"
    "\t\t\t\t'healthStatus'     => isset( $health['status'] ) ? sanitize_key( $health['status'] ) : 'review',\n"
    "\t\t\t\t'status'           => empty( $usages ) ? 'unreferenced' : 'referenced',",
)


# ---------------------------------------------------------------------------
# Insert Integration Coverage and Broken URLs tab before Help.
# ---------------------------------------------------------------------------
methods = r'''
\t/**
\t * Renders active/not-installed coverage for supported integrations.
\t *
\t * @return void
\t */
\tprivate function render_integration_coverage() {
\t\tif ( ! method_exists( $this->scanner, 'get_integration_coverage' ) ) {
\t\t\treturn;
\t\t}
\t\t$coverage = $this->scanner->get_integration_coverage();
\t\t?>
\t\t<div class="mediarefinspector-panel mediarefinspector-integration-coverage">
\t\t\t<div class="mediarefinspector-section-heading"><div><h3><?php esc_html_e( 'Integration coverage', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'See which supported reference sources are available on this site. No external service is contacted.', 'media-reference-inspector' ); ?></p></div></div>
\t\t\t<div class="mediarefinspector-coverage-grid">
\t\t\t\t<?php foreach ( $coverage as $item ) : ?>
\t\t\t\t\t<div class="mediarefinspector-coverage-card"><div><strong><?php echo esc_html( $item['name'] ); ?></strong><span class="mediarefinspector-health-pill <?php echo ! empty( $item['active'] ) ? 'is-healthy' : 'is-review'; ?>"><?php echo esc_html( ! empty( $item['active'] ) ? __( 'Active', 'media-reference-inspector' ) : __( 'Not installed / inactive', 'media-reference-inspector' ) ); ?></span></div><p><?php echo esc_html( $item['detail'] ); ?></p></div>
\t\t\t\t<?php endforeach; ?>
\t\t\t</div>
\t\t</div>
\t\t<?php
\t}

\t/**
\t * Renders a bounded read-only scan for broken local uploads URLs in content.
\t *
\t * @return void
\t */
\tprivate function render_broken_tab() {
\t\t$run = isset( $_GET['run_broken'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['run_broken'] ) );
\t\t$run_url = wp_nonce_url( add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'broken', 'run_broken' => '1' ), admin_url( 'upload.php' ) ), 'mediarefinspector_run_broken', 'mediarefinspector_broken_nonce' );
\t\t?>
\t\t<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-broken-heading">
\t\t\t<div class="mediarefinspector-section-heading mediarefinspector-section-heading-split"><div><h2 id="mediarefinspector-broken-heading"><?php esc_html_e( 'Broken local media URLs', 'media-reference-inspector' ); ?></h2><p><?php esc_html_e( 'Check recent supported post content for URLs inside this site’s uploads directory whose local files no longer exist. The scan is read-only and makes no external requests.', 'media-reference-inspector' ); ?></p></div><a class="button button-primary" href="<?php echo esc_url( $run_url ); ?>"><?php esc_html_e( 'Scan for broken URLs', 'media-reference-inspector' ); ?></a></div>
\t\t\t<?php if ( $run ) : ?>
\t\t\t\t<?php $nonce = isset( $_GET['mediarefinspector_broken_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_broken_nonce'] ) ) : ''; ?>
\t\t\t\t<?php if ( ! wp_verify_nonce( $nonce, 'mediarefinspector_run_broken' ) ) : ?>
\t\t\t\t\t<?php $this->render_notice( __( 'The broken URL scan request could not be verified.', 'media-reference-inspector' ), 'error' ); ?>
\t\t\t\t<?php elseif ( ! method_exists( $this->scanner, 'find_broken_local_upload_urls' ) ) : ?>
\t\t\t\t\t<?php $this->render_notice( __( 'Broken URL scanning is not available in this build.', 'media-reference-inspector' ), 'error' ); ?>
\t\t\t\t<?php else : ?>
\t\t\t\t\t<?php $items = $this->scanner->find_broken_local_upload_urls( 100 ); ?>
\t\t\t\t\t<?php if ( empty( $items ) ) : ?>
\t\t\t\t\t\t<?php $this->render_notice( __( 'No broken local uploads URLs were found in the bounded scan.', 'media-reference-inspector' ), 'success' ); ?>
\t\t\t\t\t<?php else : ?>
\t\t\t\t\t\t<div class="mediarefinspector-reference-list mediarefinspector-broken-list">
\t\t\t\t\t\t<?php foreach ( $items as $item ) : ?>
\t\t\t\t\t\t\t<article class="mediarefinspector-reference-item"><div><strong><?php echo esc_html( $item['title'] ); ?></strong><span class="mediarefinspector-status-pill"><?php esc_html_e( 'Local file missing', 'media-reference-inspector' ); ?></span><p class="mediarefinspector-evidence"><code><?php echo esc_html( $item['url'] ); ?></code></p></div><div class="mediarefinspector-reference-actions"><?php if ( ! empty( $item['edit_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'media-reference-inspector' ); ?></a><?php endif; ?><?php if ( ! empty( $item['view_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $item['view_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'media-reference-inspector' ); ?></a><?php endif; ?></div></article>
\t\t\t\t\t\t<?php endforeach; ?>
\t\t\t\t\t\t</div>
\t\t\t\t\t<?php endif; ?>
\t\t\t\t<?php endif; ?>
\t\t\t<?php else : ?>
\t\t\t\t<div class="mediarefinspector-empty-state"><span class="dashicons dashicons-warning" aria-hidden="true"></span><h3><?php esc_html_e( 'Ready for a local broken-link audit', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Only URLs under this site’s WordPress uploads directory are checked. Remote URLs are ignored.', 'media-reference-inspector' ); ?></p></div>
\t\t\t<?php endif; ?>
\t\t</section>
\t\t<?php
\t}

'''.replace('\\t', '\t')
anchor = "\n\t/**\n\t * Renders contextual help and plugin support."
replace_once("includes/class-mediarefinspector-plugin.php", anchor, "\n" + methods + anchor)


# ---------------------------------------------------------------------------
# JS enhancements for selected IDs, filters, and printable HTML export.
# ---------------------------------------------------------------------------
replace_once(
    "assets/js/admin.js",
    "\tvar exportButton = document.getElementById('mediarefinspector-export-csv');",
    "\tvar exportButton = document.getElementById('mediarefinspector-export-csv');\n"
    "\tvar exportHtmlButton = document.getElementById('mediarefinspector-export-html');",
)
replace_once(
    "assets/js/admin.js",
    "\tvar resultSort = document.getElementById('mediarefinspector-result-sort');",
    "\tvar resultSort = document.getElementById('mediarefinspector-result-sort');\n"
    "\tvar sourceFilter = document.getElementById('mediarefinspector-source-filter');\n"
    "\tvar healthFilter = document.getElementById('mediarefinspector-health-filter');",
)
replace_once(
    "assets/js/admin.js",
    "\tvar ageInput = document.getElementById('mediarefinspector-bulk-age');",
    "\tvar ageInput = document.getElementById('mediarefinspector-bulk-age');\n"
    "\tvar selectedIdsInput = document.getElementById('mediarefinspector-selected-ids');",
)
replace_once(
    "assets/js/admin.js",
    "\t\tif (ageInput) {\n\t\t\tageInput.disabled = isRunning;\n\t\t}\n",
    "\t\tif (ageInput) { ageInput.disabled = isRunning; }\n"
    "\t\tif (selectedIdsInput) { selectedIdsInput.disabled = isRunning; }\n",
)
replace_once(
    "assets/js/admin.js",
    "\t\texportButton.disabled = true;",
    "\t\texportButton.disabled = true;\n\t\tif (exportHtmlButton) { exportHtmlButton.disabled = true; }",
)
replace_once(
    "assets/js/admin.js",
    "\t\texportButton.disabled = results.length === 0;",
    "\t\texportButton.disabled = results.length === 0;\n\t\tif (exportHtmlButton) { exportHtmlButton.disabled = results.length === 0; }",
)
replace_once(
    "assets/js/admin.js",
    "\t\trow.dataset.title = String(item.title || '').toLowerCase();",
    "\t\trow.dataset.title = String(item.title || '').toLowerCase();\n"
    "\t\trow.dataset.sources = Array.isArray(item.sourceCategories) ? item.sourceCategories.join(' ') : '';\n"
    "\t\trow.dataset.health = item.healthStatus || 'review';",
)
replace_once(
    "assets/js/admin.js",
    "\t\tvar filter = resultFilter.value || 'all';\n\t\tvar rows = Array.prototype.slice.call(resultsBody.querySelectorAll('tr'));",
    "\t\tvar filter = resultFilter.value || 'all';\n"
    "\t\tvar source = sourceFilter ? (sourceFilter.value || 'all') : 'all';\n"
    "\t\tvar health = healthFilter ? (healthFilter.value || 'all') : 'all';\n"
    "\t\tvar rows = Array.prototype.slice.call(resultsBody.querySelectorAll('tr'));",
)
replace_once(
    "assets/js/admin.js",
    "\t\trows.forEach(function (row) { row.hidden = filter !== 'all' && row.dataset.status !== filter; });",
    "\t\trows.forEach(function (row) {\n"
    "\t\t\tvar statusMatch = filter === 'all' || row.dataset.status === filter;\n"
    "\t\t\tvar sourceMatch = source === 'all' || String(row.dataset.sources || '').split(' ').indexOf(source) !== -1;\n"
    "\t\t\tvar healthMatch = health === 'all' || row.dataset.health === health;\n"
    "\t\t\trow.hidden = !(statusMatch && sourceMatch && healthMatch);\n"
    "\t\t});",
)
replace_once(
    "assets/js/admin.js",
    "\tfunction exportCsv() {",
    "\tfunction htmlEscape(value) {\n"
    "\t\treturn String(value == null ? '' : value).replace(/[&<>\"']/g, function (char) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',\"'\":'&#039;'})[char]; });\n"
    "\t}\n\n"
    "\tfunction exportHtml() {\n"
    "\t\tif (!results.length) { return; }\n"
    "\t\tvar body = results.map(function (item) {\n"
    "\t\t\treturn '<tr><td>' + htmlEscape(item.id) + '</td><td>' + htmlEscape(item.title || '') + '</td><td>' + htmlEscape(makeStatusLabel(item.status)) + '</td><td>' + htmlEscape(item.referenceCount || 0) + '</td><td>' + htmlEscape((item.referenceTypes || []).join(', ')) + '</td><td>' + htmlEscape(item.healthStatus || 'review') + '</td></tr>';\n"
    "\t\t}).join('');\n"
    "\t\tvar html = '<!doctype html><html><head><meta charset=\"utf-8\"><title>Media Reference Inspector report</title><style>body{font-family:system-ui,sans-serif;margin:32px;color:#1d2327}table{border-collapse:collapse;width:100%}th,td{border:1px solid #dcdcde;padding:8px;text-align:left}th{background:#f6f7f7}small{color:#646970}</style></head><body><h1>Media Reference Inspector</h1><p>Read-only audit report. No supported references found does not prove a file is unused.</p><table><thead><tr><th>ID</th><th>Media</th><th>Status</th><th>References</th><th>Reference types</th><th>File health</th></tr></thead><tbody>' + body + '</tbody></table></body></html>';\n"
    "\t\tvar blob = new Blob([html], { type: 'text/html;charset=utf-8' });\n"
    "\t\tvar link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'media-reference-inspector-report.html'; document.body.appendChild(link); link.click(); URL.revokeObjectURL(link.href); link.remove();\n"
    "\t}\n\n"
    "\tfunction exportCsv() {",
)
replace_once(
    "assets/js/admin.js",
    "\t\t\tlimit: limitInput.value || '100'",
    "\t\t\tlimit: limitInput.value || '100',\n\t\t\tselected_ids: selectedIdsInput ? (selectedIdsInput.value || '') : ''",
)
replace_once(
    "assets/js/admin.js",
    "\tresultFilter.addEventListener('change', applyResultFilter);\n\tif (resultSort) { resultSort.addEventListener('change', applyResultFilter); }\n\texportButton.addEventListener('click', exportCsv);",
    "\tresultFilter.addEventListener('change', applyResultFilter);\n"
    "\tif (resultSort) { resultSort.addEventListener('change', applyResultFilter); }\n"
    "\tif (sourceFilter) { sourceFilter.addEventListener('change', applyResultFilter); }\n"
    "\tif (healthFilter) { healthFilter.addEventListener('change', applyResultFilter); }\n"
    "\texportButton.addEventListener('click', exportCsv);\n"
    "\tif (exportHtmlButton) { exportHtmlButton.addEventListener('click', exportHtml); }",
)


# ---------------------------------------------------------------------------
# CSS for evidence, integration coverage and broken URL rows.
# ---------------------------------------------------------------------------
css = read("assets/css/admin.css")
append_css = r'''

/* 2.3.0 beta audit workflow */
.mediarefinspector-evidence { margin: 6px 0 0; color: #50575e; font-size: 12px; line-height: 1.5; overflow-wrap: anywhere; }
.mediarefinspector-evidence span { display: block; margin-top: 3px; }
.mediarefinspector-evidence code { white-space: normal; overflow-wrap: anywhere; }
.mediarefinspector-integration-coverage { margin: 0 0 20px; }
.mediarefinspector-coverage-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.mediarefinspector-coverage-card { border: 1px solid #dcdcde; border-radius: 6px; padding: 12px; background: #fff; }
.mediarefinspector-coverage-card > div { display: flex; gap: 8px; align-items: center; justify-content: space-between; }
.mediarefinspector-coverage-card p { margin: 8px 0 0; color: #646970; }
.mediarefinspector-row-status { color: #50575e; font-weight: 600; }
.mediarefinspector-broken-list .mediarefinspector-reference-item { align-items: flex-start; }
.mediarefinspector-bulk-filter-row { grid-template-columns: repeat(4, minmax(160px, 1fr)); }
@media (max-width: 1100px) { .mediarefinspector-coverage-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .mediarefinspector-bulk-filter-row { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 700px) { .mediarefinspector-coverage-grid, .mediarefinspector-bulk-filter-row { grid-template-columns: 1fr; } }
'''
if "/* 2.3.0 beta audit workflow */" not in css:
    write("assets/css/admin.css", css.rstrip() + append_css)


# ---------------------------------------------------------------------------
# Readme beta notes. Stable tag intentionally remains 2.2.0.
# ---------------------------------------------------------------------------
readme = read("readme.txt")
if "= 2.3.0 beta testing =" not in readme:
    marker = "== Changelog =="
    if marker not in readme:
        raise RuntimeError("Changelog anchor missing from readme.txt")
    beta = """= 2.3.0 beta testing =\n\nThis test build adds reference confidence/source context, integration coverage, a bounded broken local uploads URL scanner, cached Media Library scan status, selected-ID bulk scans, evidence/file-health filters, and a printable HTML report. It remains read-only.\n\n"""
    readme = readme.replace(marker, beta + marker, 1)
    write("readme.txt", readme)

print("Applied Media Reference Inspector 2.3.0-beta.1 source changes.")
