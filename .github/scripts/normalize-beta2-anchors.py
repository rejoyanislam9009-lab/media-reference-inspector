from pathlib import Path
import re

path = Path('includes/class-mediarefinspector-plugin.php')
text = path.read_text()

pattern = r"\t\t\$tabs = array\(\n.*?\t\t\);\n\t\t\?>"
replacement = "\t\t$tabs = array(\n\t\t\t'scanner' => __( 'Scanner', 'media-reference-inspector' ),\n\t\t\t'bulk'        => __( 'Bulk Scan', 'media-reference-inspector' ),\n\t\t\t'help'        => __( 'Help', 'media-reference-inspector' ),\n\t\t);\n\t\t?>"
text, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('Could not normalize tabs anchor')

pattern = r"\t\t\t<div class=\"mediarefinspector-bulk-filter-row\" id=\"mediarefinspector-bulk-filter-row\" hidden>\n.*?\t\t\t</div>\n\n\t\t\t<div class=\"mediarefinspector-empty-state\""
replacement = "\t\t\t<div class=\"mediarefinspector-bulk-filter-row\" id=\"mediarefinspector-bulk-filter-row\" hidden>\n\t\t\t\t<div><label for=\"mediarefinspector-result-filter\"><?php esc_html_e( 'Show results', 'media-reference-inspector' ); ?></label>\n\t\t\t\t<select id=\"mediarefinspector-result-filter\">\n\t\t\t\t\t<option value=\"all\"><?php esc_html_e( 'All results', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"referenced\"><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"unreferenced\"><?php esc_html_e( 'Potential unused review', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"error\"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option>\n\t\t\t\t</select></div>\n\t\t\t\t<div><label for=\"mediarefinspector-result-sort\"><?php esc_html_e( 'Sort results', 'media-reference-inspector' ); ?></label>\n\t\t\t\t<select id=\"mediarefinspector-result-sort\"><option value=\"scan\"><?php esc_html_e( 'Scan order', 'media-reference-inspector' ); ?></option><option value=\"references-desc\"><?php esc_html_e( 'Most references', 'media-reference-inspector' ); ?></option><option value=\"references-asc\"><?php esc_html_e( 'Fewest references', 'media-reference-inspector' ); ?></option><option value=\"title\"><?php esc_html_e( 'Title A–Z', 'media-reference-inspector' ); ?></option></select></div>\n\t\t\t</div>\n\n\t\t\t<div class=\"mediarefinspector-empty-state\""
text, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('Could not apply bulk-filter patch')
path.write_text(text)

builder = Path('.github/scripts/build-2.2.0-beta2.py')
source = builder.read_text()
start_marker = 'old = """\\t\\t\\t<div class=\\"mediarefinspector-bulk-filter-row\\"'
next_marker = 'old = """\\t\\t\\t<?php $this->render_attachment_parent_note( $attachment_id ); ?>\\n"""'
start = source.find(start_marker)
end = source.find(next_marker, start)
if start < 0 or end < 0:
    raise SystemExit('Could not remove fragile bulk-filter builder block')
source = source[:start] + source[end:]
builder.write_text(source)
