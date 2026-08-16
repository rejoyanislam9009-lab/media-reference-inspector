from pathlib import Path
import re

admin_path = Path('includes/class-mediarefinspector-plugin.php')
text = admin_path.read_text()

# Render new tabs.
pattern = r"\t\t\t\tif \( 'bulk' === \$tab \) \{.*?\t\t\t\t\}\n\t\t\t\t\?>"
replacement = "\t\t\t\tif ( 'bulk' === $tab ) {\n\t\t\t\t\t$this->render_bulk_tab();\n\t\t\t\t} elseif ( 'audit' === $tab ) {\n\t\t\t\t\t$this->render_audit_tab();\n\t\t\t\t} elseif ( 'duplicates' === $tab ) {\n\t\t\t\t\t$this->render_duplicates_tab();\n\t\t\t\t} elseif ( 'help' === $tab ) {\n\t\t\t\t\t$this->render_help_tab();\n\t\t\t\t} else {\n\t\t\t\t\t$this->render_scanner_tab();\n\t\t\t\t}\n\t\t\t\t?>"
text, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('render tab switch patch failed')

# Navigation tabs.
pattern = r"\t\t\$tabs = array\(\n.*?\t\t\);"
replacement = "\t\t$tabs = array(\n\t\t\t'scanner'    => __( 'Scanner', 'media-reference-inspector' ),\n\t\t\t'bulk'       => __( 'Bulk Scan', 'media-reference-inspector' ),\n\t\t\t'audit'      => __( 'Page Audit', 'media-reference-inspector' ),\n\t\t\t'duplicates' => __( 'Duplicates', 'media-reference-inspector' ),\n\t\t\t'help'       => __( 'Help', 'media-reference-inspector' ),\n\t\t);"
text, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('tab array patch failed')

anchor = "<?php echo esc_html( $label ); ?></a>"
if anchor not in text:
    raise SystemExit('tab label anchor missing')
text = text.replace(anchor, "<?php echo esc_html( $label ); ?><?php if ( $this->is_new_feature( $tab_key ) ) : ?><span class=\"mediarefinspector-new-badge\"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span><?php endif; ?></a>", 1)

# Scanner coverage + what's new.
elementor = "\t\t\t\t<span><strong><?php esc_html_e( 'Elementor', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Validated saved media controls', 'media-reference-inspector' ); ?></span>"
if elementor not in text:
    raise SystemExit('Elementor coverage anchor missing')
text = text.replace(elementor, elementor + "\n\t\t\t\t<span><strong><?php esc_html_e( 'ACF', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Confirmed Image, File and Gallery field references', 'media-reference-inspector' ); ?></span>", 1)
scan_anchor = "\t\t\t<?php $this->maybe_render_scan_results(); ?>"
if scan_anchor not in text:
    raise SystemExit('scan results anchor missing')
text = text.replace(scan_anchor, "\t\t\t<?php $this->render_whats_new_panel(); ?>\n" + scan_anchor, 1)

# Bulk filter/sort UI.
pattern = r"\t\t\t<div class=\"mediarefinspector-bulk-filter-row\" id=\"mediarefinspector-bulk-filter-row\" hidden>\n.*?\t\t\t</div>\n\n\t\t\t<div class=\"mediarefinspector-empty-state\""
bulk = "\t\t\t<div class=\"mediarefinspector-bulk-filter-row\" id=\"mediarefinspector-bulk-filter-row\" hidden>\n\t\t\t\t<div><label for=\"mediarefinspector-result-filter\"><?php esc_html_e( 'Show results', 'media-reference-inspector' ); ?></label>\n\t\t\t\t<select id=\"mediarefinspector-result-filter\"><option value=\"all\"><?php esc_html_e( 'All results', 'media-reference-inspector' ); ?></option><option value=\"referenced\"><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></option><option value=\"unreferenced\"><?php esc_html_e( 'Potential unused review', 'media-reference-inspector' ); ?></option><option value=\"error\"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option></select></div>\n\t\t\t\t<div><label for=\"mediarefinspector-result-sort\"><?php esc_html_e( 'Sort results', 'media-reference-inspector' ); ?></label>\n\t\t\t\t<select id=\"mediarefinspector-result-sort\"><option value=\"scan\"><?php esc_html_e( 'Scan order', 'media-reference-inspector' ); ?></option><option value=\"references-desc\"><?php esc_html_e( 'Most references', 'media-reference-inspector' ); ?></option><option value=\"references-asc\"><?php esc_html_e( 'Fewest references', 'media-reference-inspector' ); ?></option><option value=\"title\"><?php esc_html_e( 'Title A–Z', 'media-reference-inspector' ); ?></option></select></div>\n\t\t\t</div>\n\n\t\t\t<div class=\"mediarefinspector-empty-state\""
text, count = re.subn(pattern, bulk, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('bulk filter patch failed')

# File health on single scan.
parent_anchor = "\t\t\t<?php $this->render_attachment_parent_note( $attachment_id ); ?>"
if parent_anchor not in text:
    raise SystemExit('attachment parent anchor missing')
text = text.replace(parent_anchor, "\t\t\t<?php $this->render_file_health( $attachment_id ); ?>\n" + parent_anchor, 1)

# Current tab allow-list.
text, count = re.subn(r"return in_array\( \$tab, array\( 'scanner', 'bulk', 'help' \), true \) \? \$tab : 'scanner';", "return in_array( $tab, array( 'scanner', 'bulk', 'audit', 'duplicates', 'help' ), true ) ? $tab : 'scanner';", text, count=1)
if count != 1:
    raise SystemExit('current tab allow-list patch failed')

# Reuse the prepared methods from the first builder but convert literal tab escapes.
old_builder = Path('.github/scripts/build-2.2.0-beta2.py').read_text()
start = old_builder.index("methods = r'''", 0) + len("methods = r'''")
end = old_builder.index("'''", start)
methods = old_builder[start:end].replace('\\t', '\t')
marker = "\n\t/**\n\t * Renders contextual help and plugin support.\n"
if marker not in text:
    raise SystemExit('help methods marker missing')
text = text.replace(marker, methods + marker, 1)

# Mark NEW feature as seen after rendering the selected tab.
mark_anchor = "\t\t</div>\n\t\t<?php\n\t}\n\n\t/**\n\t * Renders the branded WordPress-native page header and navigation."
mark_replace = "\t\t</div>\n\t\t<?php\n\t\t$this->mark_feature_seen( $tab );\n\t}\n\n\t/**\n\t * Renders the branded WordPress-native page header and navigation."
if mark_anchor not in text:
    raise SystemExit('mark feature anchor missing')
text = text.replace(mark_anchor, mark_replace, 1)
admin_path.write_text(text)

# CSS: 5 coverage cards and prepared audit styles from original builder.
css_path = Path('assets/css/admin.css')
css = css_path.read_text()
css = css.replace('grid-template-columns: repeat(4, minmax(0, 1fr));', 'grid-template-columns: repeat(5, minmax(0, 1fr));', 1)
css_start = old_builder.index("c += r'''", end) + len("c += r'''")
css_end = old_builder.index("'''", css_start)
audit_css = old_builder[css_start:css_end].replace('\\t', '\t')
if 'mediarefinspector-new-badge' not in css:
    css += audit_css
css_path.write_text(css)

# JS sorting.
js_path = Path('assets/js/admin.js')
js = js_path.read_text()
js = js.replace("var resultFilter = document.getElementById('mediarefinspector-result-filter');", "var resultFilter = document.getElementById('mediarefinspector-result-filter');\n\tvar resultSort = document.getElementById('mediarefinspector-result-sort');", 1)
js = js.replace("row.dataset.status = item.status;", "row.dataset.status = item.status;\n\t\trow.dataset.references = String(item.referenceCount || 0);\n\t\trow.dataset.title = String(item.title || '').toLowerCase();", 1)
pattern = r"\tfunction applyResultFilter\(\) \{.*?\n\t\}\n\n\tfunction csvEscape"
replacement = "\tfunction applyResultFilter() {\n\t\tvar filter = resultFilter.value || 'all';\n\t\tvar rows = Array.prototype.slice.call(resultsBody.querySelectorAll('tr'));\n\t\tvar sort = resultSort ? (resultSort.value || 'scan') : 'scan';\n\t\tif (sort !== 'scan') {\n\t\t\trows.sort(function (a, b) {\n\t\t\t\tif (sort === 'title') { return (a.dataset.title || '').localeCompare(b.dataset.title || ''); }\n\t\t\t\tvar av = parseInt(a.dataset.references || '0', 10);\n\t\t\t\tvar bv = parseInt(b.dataset.references || '0', 10);\n\t\t\t\treturn sort === 'references-asc' ? av - bv : bv - av;\n\t\t\t});\n\t\t\trows.forEach(function (row) { resultsBody.appendChild(row); });\n\t\t}\n\t\trows.forEach(function (row) { row.hidden = filter !== 'all' && row.dataset.status !== filter; });\n\t}\n\n\tfunction csvEscape"
js, count = re.subn(pattern, replacement, js, count=1, flags=re.S)
if count != 1:
    raise SystemExit('JS filter/sort patch failed')
js = js.replace("resultFilter.addEventListener('change', applyResultFilter);", "resultFilter.addEventListener('change', applyResultFilter);\n\tif (resultSort) { resultSort.addEventListener('change', applyResultFilter); }", 1)
js_path.write_text(js)

# Readme additions; keep Stable tag on public 2.1.0 while beta is tested.
readme_path = Path('readme.txt')
r = readme_path.read_text()
if 'Advanced Custom Fields (ACF)' not in r:
    r = r.replace("* Elementor saved media-control references when the saved JSON confirms the attachment ID.\n", "* Elementor saved media-control references when the saved JSON confirms the attachment ID.\n* Advanced Custom Fields (ACF) Image, File, and Gallery fields when ACF confirms the field type and saved attachment ID.\n")
if '= Page & Post Audit =' not in r:
    r = r.replace("= Bulk Scan =\n\nBulk Scan processes media items one at a time through bounded AJAX requests. It supports media type, upload-age, search, and maximum-item filters, live progress, result filtering, and CSV export.\n", "= Bulk Scan =\n\nBulk Scan processes media items one at a time through bounded AJAX requests. It supports media type, upload-age, search, maximum-item filters, live progress, potential-unused review filtering, result sorting, and CSV export.\n\n= Page & Post Audit =\n\nAudit a post or page to list supported media attachment IDs, flag broken attachment IDs, and review local file health without modifying content.\n\n= Duplicate Finder =\n\nRun an on-demand, bounded exact-file hash scan of recent local Media Library files. The tool reports exact matches only and never deletes files.\n")
if '= 2.2.0-beta.2 =' not in r:
    r = r.replace("= 2.2.0-beta.1 =\n", "= 2.2.0-beta.2 =\n* Added per-user NEW badges and a What's New card that disappear after the new feature tabs are visited.\n* Added Page & Post Media Audit with supported media listing, broken attachment-ID review, and file-health status.\n* Added Media File Health to single-item scan results.\n* Added a bounded exact Duplicate Finder for recent readable local files.\n* Added confirmed ACF Image, File, and Gallery field reference detection when ACF is active.\n* Added Bulk Scan result sorting and clearer Potential unused review wording.\n\n= 2.2.0-beta.1 =\n", 1)
readme_path.write_text(r)
