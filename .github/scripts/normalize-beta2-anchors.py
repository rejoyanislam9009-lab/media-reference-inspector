from pathlib import Path
import re

path = Path('includes/class-mediarefinspector-plugin.php')
text = path.read_text()
pattern = r"\t\t\$tabs = array\(\n.*?\t\t\);\n\t\t\?>"
replacement = "\t\t$tabs = array(\n\t\t\t'scanner' => __( 'Scanner', 'media-reference-inspector' ),\n\t\t\t'bulk'        => __( 'Bulk Scan', 'media-reference-inspector' ),\n\t\t\t'help'        => __( 'Help', 'media-reference-inspector' ),\n\t\t);\n\t\t?>"
text, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('Could not normalize tabs anchor')
path.write_text(text)
