from pathlib import Path

p = Path('includes/class-mediarefinspector-plugin.php')
s = p.read_text(encoding='utf-8')
old = "\t\t\t\t\t\t\t<?php if ( is_array( $cached_status ) && ! empty( $cached_status['status'] ) ) : ?>\n\t\t\t\t\t\t\t\t<p class=\"mediarefinspector-cached-status\"><span class=\"dashicons dashicons-clock\" aria-hidden=\"true\"></span><?php echo esc_html( 'referenced' === $cached_status['status'] ? sprintf( __( 'Recent scan: %d references', 'media-reference-inspector' ), isset( $cached_status['count'] ) ? absint( $cached_status['count'] ) : 0 ) : __( 'Recent scan: no supported references', 'media-reference-inspector' ) ); ?></p>\n\t\t\t\t\t\t\t<?php endif; ?>"
new = "\t\t\t\t\t\t\t<?php if ( is_array( $cached_status ) && ! empty( $cached_status['status'] ) ) : ?>\n\t\t\t\t\t\t\t\t<?php\n\t\t\t\t\t\t\t\tif ( 'referenced' === $cached_status['status'] ) {\n\t\t\t\t\t\t\t\t\t$recent_count = isset( $cached_status['count'] ) ? absint( $cached_status['count'] ) : 0;\n\t\t\t\t\t\t\t\t\t/* translators: %d: Number of references in the recent cached scan. */\n\t\t\t\t\t\t\t\t\t$recent_status = sprintf( __( 'Recent scan: %d references', 'media-reference-inspector' ), $recent_count );\n\t\t\t\t\t\t\t\t} else {\n\t\t\t\t\t\t\t\t\t$recent_status = __( 'Recent scan: no supported references', 'media-reference-inspector' );\n\t\t\t\t\t\t\t\t}\n\t\t\t\t\t\t\t\t?>\n\t\t\t\t\t\t\t\t<p class=\"mediarefinspector-cached-status\"><span class=\"dashicons dashicons-clock\" aria-hidden=\"true\"></span><?php echo esc_html( $recent_status ); ?></p>\n\t\t\t\t\t\t\t<?php endif; ?>"
if old not in s:
    raise SystemExit('Missing patch anchor: recent cached scan translator')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')
print('Applied remaining recent-scan translator fix.')
