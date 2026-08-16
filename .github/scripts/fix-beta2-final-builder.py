from pathlib import Path

path = Path('.github/scripts/build-2.2.0-beta2-final.py')
text = path.read_text()
text = text.replace("parent_anchor = \"\\t\\t\\t<?php $this->render_attachment_parent_note( $attachment_id ); ?>\"", "parent_anchor = \"\\t\\t<?php $this->render_attachment_parent_note( $attachment_id ); ?>\"")
text = text.replace("text = text.replace(parent_anchor, \"\\t\\t\\t<?php $this->render_file_health( $attachment_id ); ?>\\n\" + parent_anchor, 1)", "text = text.replace(parent_anchor, \"\\t\\t<?php $this->render_file_health( $attachment_id ); ?>\\n\" + parent_anchor, 1)")
path.write_text(text)
