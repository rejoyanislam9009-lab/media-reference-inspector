from pathlib import Path
import re


def read(path):
    return Path(path).read_text(encoding='utf-8')


def write(path, text):
    Path(path).write_text(text, encoding='utf-8')


def replace_once(text, old, new, label):
    if old not in text:
        raise SystemExit('Missing patch anchor: ' + label)
    return text.replace(old, new, 1)


# Version metadata: keep the test package internally consistent for Plugin Check.
p = Path('media-reference-inspector.php')
s = p.read_text(encoding='utf-8')
s = s.replace('2.4.0-beta.1', '2.4.0-beta.2')
p.write_text(s, encoding='utf-8')

p = Path('readme.txt')
s = p.read_text(encoding='utf-8')
s = s.replace('Stable tag: 2.3.0', 'Stable tag: 2.4.0-beta.2', 1)
s = s.replace('= 2.4.0-beta.1 =', '= 2.4.0-beta.2 =', 1)
s = s.replace('2.4.0-beta.1', '2.4.0-beta.2')
p.write_text(s, encoding='utf-8')

# Integration scanner translator context.
p = Path('includes/class-mediarefinspector-integration-scanner.php')
s = p.read_text(encoding='utf-8')
old = "\t\t$title            = ! empty( $row->post_title ) ? (string) $row->post_title : sprintf( __( 'Untitled #%d', 'media-reference-inspector' ), $post_id );"
new = "\t\tif ( ! empty( $row->post_title ) ) {\n\t\t\t$title = (string) $row->post_title;\n\t\t} else {\n\t\t\t/* translators: %d: Post ID. */\n\t\t\t$title = sprintf( __( 'Untitled #%d', 'media-reference-inspector' ), $post_id );\n\t\t}"
s = replace_once(s, old, new, 'integration untitled translator')
p.write_text(s, encoding='utf-8')

# Audit-service block label translator context.
p = Path('includes/class-mediarefinspector-audit-service.php')
s = p.read_text(encoding='utf-8')
old = "\t\t\t$label = $name ? sprintf( __( 'Block: %s', 'media-reference-inspector' ), $name ) : __( 'Block content', 'media-reference-inspector' );"
new = "\t\t\tif ( $name ) {\n\t\t\t\t/* translators: %s: Block name. */\n\t\t\t\t$label = sprintf( __( 'Block: %s', 'media-reference-inspector' ), $name );\n\t\t\t} else {\n\t\t\t\t$label = __( 'Block content', 'media-reference-inspector' );\n\t\t\t}"
s = replace_once(s, old, new, 'audit block translator')
p.write_text(s, encoding='utf-8')

# Insights scanner title translator context.
p = Path('includes/class-mediarefinspector-insights-scanner.php')
s = p.read_text(encoding='utf-8')
old = "\t\t\t\t$results[]    = array(\n\t\t\t\t\t'post_id'   => $post_id,\n\t\t\t\t\t'title'     => ! empty( $row->post_title ) ? (string) $row->post_title : sprintf( __( 'Untitled #%d', 'media-reference-inspector' ), $post_id ),"
new = "\t\t\t\tif ( ! empty( $row->post_title ) ) {\n\t\t\t\t\t$title = (string) $row->post_title;\n\t\t\t\t} else {\n\t\t\t\t\t/* translators: %d: Post ID. */\n\t\t\t\t\t$title = sprintf( __( 'Untitled #%d', 'media-reference-inspector' ), $post_id );\n\t\t\t\t}\n\t\t\t\t$results[] = array(\n\t\t\t\t\t'post_id'   => $post_id,\n\t\t\t\t\t'title'     => $title,"
s = replace_once(s, old, new, 'insights untitled translator')
p.write_text(s, encoding='utf-8')

# Advanced scanner: move the escaped underscore wildcard into a prepared parameter.
p = Path('includes/class-mediarefinspector-advanced-scanner.php')
s = p.read_text(encoding='utf-8')
old = "\t\t$serialized_id = '%' . $wpdb->esc_like( 'i:' . $attachment_id . ';' ) . '%';\n\t\t$string_id     = '%' . $wpdb->esc_like( '\"' . $attachment_id . '\"' ) . '%';"
new = "\t\t$serialized_id   = '%' . $wpdb->esc_like( 'i:' . $attachment_id . ';' ) . '%';\n\t\t$string_id       = '%' . $wpdb->esc_like( '\"' . $attachment_id . '\"' ) . '%';\n\t\t$private_key_like = $wpdb->esc_like( '_' ) . '%';"
s = replace_once(s, old, new, 'advanced wildcard variables')
s = replace_once(s, "WHERE pm.meta_key NOT LIKE '\\\\_%'", "WHERE pm.meta_key NOT LIKE %s", 'advanced wildcard query')
old_args = "\t\t\t\t$wpdb->posts,\n\t\t\t\t$wpdb->postmeta,\n\t\t\t\t(string) $attachment_id,"
new_args = "\t\t\t\t$wpdb->posts,\n\t\t\t\t$wpdb->postmeta,\n\t\t\t\t$private_key_like,\n\t\t\t\t(string) $attachment_id,"
s = replace_once(s, old_args, new_args, 'advanced wildcard arg')
p.write_text(s, encoding='utf-8')

# Enhanced scanner: document the safe dynamic placeholder fragments and translator context.
p = Path('includes/class-mediarefinspector-enhanced-scanner.php')
s = p.read_text(encoding='utf-8')
s = s.replace(
    "// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- All URL data is passed to prepare().\n\t\t$rows = $wpdb->get_results( $wpdb->prepare( $query, $query_args ) );",
    "// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic fragments contain static placeholders only; all URL values are passed to prepare().\n\t\t$rows = $wpdb->get_results( $wpdb->prepare( $query, $query_args ) );",
    1,
)
s = s.replace(
    "// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- All URL data is passed to prepare().\n\t\t$rows = $wpdb->get_results( $wpdb->prepare( $query, $query_args ) );",
    "// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic IN fragment contains placeholders only; all URL values are passed to prepare().\n\t\t$rows = $wpdb->get_results( $wpdb->prepare( $query, $query_args ) );",
    1,
)
old = "\t\t$title            = ! empty( $row->post_title ) ? $row->post_title : sprintf( __( 'Untitled #%d', 'media-reference-inspector' ), $post_id );"
new = "\t\tif ( ! empty( $row->post_title ) ) {\n\t\t\t$title = $row->post_title;\n\t\t} else {\n\t\t\t/* translators: %d: Post ID. */\n\t\t\t$title = sprintf( __( 'Untitled #%d', 'media-reference-inspector' ), $post_id );\n\t\t}"
s = replace_once(s, old, new, 'enhanced untitled translator')
p.write_text(s, encoding='utf-8')

# Extended scanner SQL and translator fixes.
p = Path('includes/class-mediarefinspector-extended-scanner.php')
s = p.read_text(encoding='utf-8')
s = s.replace(
    "// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only reference audit.\n\t\t$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );",
    "// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query structure is static apart from generated placeholders; all values are passed to prepare().\n\t\t$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );",
    1,
)
s = s.replace(
    "// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only builder audit.\n\t\t$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );",
    "// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Builder key list is converted to placeholders and all values are passed to prepare().\n\t\t$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );",
    1,
)
old = "\t\t$id_like = '%' . $wpdb->esc_like( (string) $attachment_id ) . '%';\n\t\t$url_like = '%' . $wpdb->esc_like( $url ) . '%';\n\t\t// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only builder audit.\n\t\t$divi_rows = $wpdb->get_results( $wpdb->prepare( \"SELECT ID, post_title, post_type, post_status, post_content FROM %i WHERE post_content LIKE '%%[et_pb_%%' AND ( post_content LIKE %s OR post_content LIKE %s ) AND post_status NOT IN ( 'auto-draft', 'trash' ) ORDER BY ID DESC LIMIT 200\", $wpdb->posts, $id_like, $url_like ) );"
new = "\t\t$id_like     = '%' . $wpdb->esc_like( (string) $attachment_id ) . '%';\n\t\t$url_like    = '%' . $wpdb->esc_like( $url ) . '%';\n\t\t$divi_marker = '%' . $wpdb->esc_like( '[et_pb_' ) . '%';\n\t\t// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only builder audit.\n\t\t$divi_rows = $wpdb->get_results( $wpdb->prepare( \"SELECT ID, post_title, post_type, post_status, post_content FROM %i WHERE post_content LIKE %s AND ( post_content LIKE %s OR post_content LIKE %s ) AND post_status NOT IN ( 'auto-draft', 'trash' ) ORDER BY ID DESC LIMIT 200\", $wpdb->posts, $divi_marker, $id_like, $url_like ) );"
s = replace_once(s, old, new, 'Divi LIKE wildcard')
s = replace_once(s, "\t\t\t\t'label' => sprintf( __( '%1$s: %2$s', 'media-reference-inspector' ), (string) $row->taxonomy, (string) $row->name ),", "\t\t\t\t/* translators: 1: Taxonomy name, 2: Term name. */\n\t\t\t\t'label' => sprintf( __( '%1$s: %2$s', 'media-reference-inspector' ), (string) $row->taxonomy, (string) $row->name ),", 'term label translator')
s = replace_once(s, "\t\t\t\t'label' => sprintf( __( 'Option: %s', 'media-reference-inspector' ), (string) $row->option_name ), 'status' => __( 'Site option', 'media-reference-inspector' ),", "\t\t\t\t/* translators: %s: WordPress option name. */\n\t\t\t\t'label' => sprintf( __( 'Option: %s', 'media-reference-inspector' ), (string) $row->option_name ), 'status' => __( 'Site option', 'media-reference-inspector' ),", 'option label translator')
s = replace_once(s, "\t\t\t'label' => sprintf( __( '%1$s (ID %2$d)', 'media-reference-inspector' ), get_the_title( $post_id ) ? get_the_title( $post_id ) : __( 'Untitled', 'media-reference-inspector' ), $post_id ),", "\t\t\t/* translators: 1: Post title, 2: Post ID. */\n\t\t\t'label' => sprintf( __( '%1$s (ID %2$d)', 'media-reference-inspector' ), get_the_title( $post_id ) ? get_the_title( $post_id ) : __( 'Untitled', 'media-reference-inspector' ), $post_id ),", 'extended post label translator')
p.write_text(s, encoding='utf-8')

# Plugin admin: translator comments, direct nonce visibility, and read-only query arg handling.
p = Path('includes/class-mediarefinspector-plugin.php')
s = p.read_text(encoding='utf-8')
old = "\t\t\t$status_label = 'referenced' === $cached['status']\n\t\t\t\t? sprintf( _n( 'Cached: %d reference', 'Cached: %d references', $count, 'media-reference-inspector' ), $count )\n\t\t\t\t: __( 'Cached: Needs review', 'media-reference-inspector' );"
new = "\t\t\tif ( 'referenced' === $cached['status'] ) {\n\t\t\t\t/* translators: %d: Cached reference count. */\n\t\t\t\t$status_label = sprintf( _n( 'Cached: %d reference', 'Cached: %d references', $count, 'media-reference-inspector' ), $count );\n\t\t\t} else {\n\t\t\t\t$status_label = __( 'Cached: Needs review', 'media-reference-inspector' );\n\t\t\t}"
s = replace_once(s, old, new, 'cached count translator')

# Help status is display-only; filter_input avoids treating it as nonce-protected form processing.
old = "\t\t$current_user  = wp_get_current_user();\n\t\t$support_state = isset( $_GET['support_status'] ) ? sanitize_key( wp_unslash( $_GET['support_status'] ) ) : '';"
new = "\t\t$current_user     = wp_get_current_user();\n\t\t$support_state_raw = filter_input( INPUT_GET, 'support_status', FILTER_UNSAFE_RAW );\n\t\t$support_state     = is_string( $support_state_raw ) ? sanitize_key( $support_state_raw ) : '';"
s = replace_once(s, old, new, 'support status input')

# Make AJAX nonce/capability verification visible to static analysis in each handler.
verify_block = "\t\tcheck_ajax_referer( 'mediarefinspector_bulk_scan', 'nonce' );\n\t\tif ( ! current_user_can( 'manage_options' ) ) {\n\t\t\twp_send_json_error( array( 'message' => __( 'You do not have permission to run this scan.', 'media-reference-inspector' ) ), 403 );\n\t\t}"
s = s.replace("\t\t$this->verify_bulk_ajax_request();", verify_block, 2)
# Remove now-unused helper.
s = re.sub(r"\n\t/\*\*\n\t \* Verifies the shared bulk AJAX nonce and capability\..*?\n\tprivate function verify_bulk_ajax_request\(\) \{.*?\n\t\}\n", "\n", s, count=1, flags=re.S)

# Translator comments for compact template expressions.
s = replace_once(s, "get_the_title( $post ) ? get_the_title( $post ) : sprintf( __( 'Post #%d', 'media-reference-inspector' ), $post->ID )", "get_the_title( $post ) ? get_the_title( $post ) : sprintf( /* translators: %d: Post ID. */ __( 'Post #%d', 'media-reference-inspector' ), $post->ID )", 'post audit fallback translator')
s = replace_once(s, "sprintf( __( 'Media #%d', 'media-reference-inspector' ), $item['id'] )", "sprintf( /* translators: %d: Media attachment ID. */ __( 'Media #%d', 'media-reference-inspector' ), $item['id'] )", 'audit media fallback translator')
s = replace_once(s, "sprintf( __( 'Duplicate group %1$d · %2$s each', 'media-reference-inspector' ), $index + 1, size_format( $group['size'], 1 ) )", "sprintf( /* translators: 1: Duplicate group number, 2: File size. */ __( 'Duplicate group %1$d · %2$s each', 'media-reference-inspector' ), $index + 1, size_format( $group['size'], 1 ) )", 'duplicate group translator')

old = "\t\t$filename  = $file ? wp_basename( $file ) : sprintf( __( 'Media item #%d', 'media-reference-inspector' ), $attachment_id );"
new = "\t\tif ( $file ) {\n\t\t\t$filename = wp_basename( $file );\n\t\t} else {\n\t\t\t/* translators: %d: Media attachment ID. */\n\t\t\t$filename = sprintf( __( 'Media item #%d', 'media-reference-inspector' ), $attachment_id );\n\t\t}"
s = replace_once(s, old, new, 'attachment summary translator')
s = replace_once(s, "\t\t\t$title = sprintf( __( 'Post #%d', 'media-reference-inspector' ), $parent_id );", "\t\t\t/* translators: %d: Parent post ID. */\n\t\t\t$title = sprintf( __( 'Post #%d', 'media-reference-inspector' ), $parent_id );", 'parent post translator')
s = replace_once(s, "\t\t?><p class=\"description mediarefinspector-safety-note\"><span class=\"dashicons dashicons-admin-links\" aria-hidden=\"true\"></span><?php echo esc_html( sprintf( __( 'Attachment relationship: uploaded to %s. This relationship alone does not prove the media item is displayed there.', 'media-reference-inspector' ), $title ) ); ?></p><?php", "\t\t/* translators: %s: Parent post title. */\n\t\t$relationship_note = sprintf( __( 'Attachment relationship: uploaded to %s. This relationship alone does not prove the media item is displayed there.', 'media-reference-inspector' ), $title );\n\t\t?><p class=\"description mediarefinspector-safety-note\"><span class=\"dashicons dashicons-admin-links\" aria-hidden=\"true\"></span><?php echo esc_html( $relationship_note ); ?></p><?php", 'relationship translator')

old = "\t\t?>\n\t\t<div class=\"mediarefinspector-panel mediarefinspector-impact-preview\">\n\t\t\t<div class=\"mediarefinspector-section-heading mediarefinspector-section-heading-split\"><div><h3><?php esc_html_e( 'Media Impact Preview', 'media-reference-inspector' ); ?> <span class=\"mediarefinspector-new-badge\"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span></h3><p><?php esc_html_e( 'Read-only summary of supported places that could be affected if this media item is replaced or removed.', 'media-reference-inspector' ); ?></p></div><span class=\"mediarefinspector-impact-total\"><?php echo esc_html( sprintf( _n( '%d supported reference', '%d supported references', count( $usages ), 'media-reference-inspector' ), count( $usages ) ) ); ?></span></div>"
new = "\t\t/* translators: %d: Number of supported references. */\n\t\t$impact_total = sprintf( _n( '%d supported reference', '%d supported references', count( $usages ), 'media-reference-inspector' ), count( $usages ) );\n\t\t?>\n\t\t<div class=\"mediarefinspector-panel mediarefinspector-impact-preview\">\n\t\t\t<div class=\"mediarefinspector-section-heading mediarefinspector-section-heading-split\"><div><h3><?php esc_html_e( 'Media Impact Preview', 'media-reference-inspector' ); ?> <span class=\"mediarefinspector-new-badge\"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span></h3><p><?php esc_html_e( 'Read-only summary of supported places that could be affected if this media item is replaced or removed.', 'media-reference-inspector' ); ?></p></div><span class=\"mediarefinspector-impact-total\"><?php echo esc_html( $impact_total ); ?></span></div>"
s = replace_once(s, old, new, 'impact plural translator')

# Site audit GET values are read-only but nonce-protected when an audit runs; use filter_input for static-analysis clarity.
old = "\t\t$limit = isset( $_GET['site_audit_limit'] ) ? absint( $_GET['site_audit_limit'] ) : 50;\n\t\tif ( ! in_array( $limit, array( 25, 50, 100 ), true ) ) { $limit = 50; }\n\t\t$run = isset( $_GET['run_site_audit'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['run_site_audit'] ) );"
new = "\t\t$limit_raw = filter_input( INPUT_GET, 'site_audit_limit', FILTER_VALIDATE_INT );\n\t\t$limit     = false !== $limit_raw && null !== $limit_raw ? absint( $limit_raw ) : 50;\n\t\tif ( ! in_array( $limit, array( 25, 50, 100 ), true ) ) { $limit = 50; }\n\t\t$run_raw = filter_input( INPUT_GET, 'run_site_audit', FILTER_UNSAFE_RAW );\n\t\t$run     = is_string( $run_raw ) && '1' === sanitize_text_field( $run_raw );"
s = replace_once(s, old, new, 'site audit GET inputs')
old = "\t\t\t\t$nonce = isset( $_GET['mediarefinspector_site_audit_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_site_audit_nonce'] ) ) : '';"
new = "\t\t\t\t$nonce_raw = filter_input( INPUT_GET, 'mediarefinspector_site_audit_nonce', FILTER_UNSAFE_RAW );\n\t\t\t\t$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( $nonce_raw ) : '';"
s = replace_once(s, old, new, 'site audit nonce input')
# Remaining site-audit media title translator in compact row.
s = replace_once(s, "sprintf( __( 'Media #%d', 'media-reference-inspector' ), $item['id'] )", "sprintf( /* translators: %d: Media attachment ID. */ __( 'Media #%d', 'media-reference-inspector' ), $item['id'] )", 'site audit media translator')

p.write_text(s, encoding='utf-8')

print('Applied Media Reference Inspector 2.4.0-beta.2 Plugin Check fixes.')
