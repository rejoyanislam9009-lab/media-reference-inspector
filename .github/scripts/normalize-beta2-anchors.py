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
replacement = "\t\t\t<div class=\"mediarefinspector-bulk-filter-row\" id=\"mediarefinspector-bulk-filter-row\" hidden>\n\t\t\t\t<label for=\"mediarefinspector-result-filter\"><?php esc_html_e( 'Show results', 'media-reference-inspector' ); ?></label>\n\t\t\t\t<select id=\"mediarefinspector-result-filter\">\n\t\t\t\t\t<option value=\"all\"><?php esc_html_e( 'All results', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"referenced\"><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"unreferenced\"><?php esc_html_e( 'No supported references', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"error\"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option>\n\t\t\t\t</select>\n\t\t\t</div>\n\n\t\t\t<div class=\"mediarefinspector-empty-state\""
text, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('Could not normalize bulk-filter anchor')

path.write_text(text)
