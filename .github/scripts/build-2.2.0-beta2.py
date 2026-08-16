from pathlib import Path

admin = Path('includes/class-mediarefinspector-plugin.php')
text = admin.read_text()

old = """\t\t\t\tif ( 'bulk' === $tab ) {\n\t\t\t\t\t$this->render_bulk_tab();\n\t\t\t\t} elseif ( 'help' === $tab ) {\n\t\t\t\t\t$this->render_help_tab();\n\t\t\t\t} else {\n\t\t\t\t\t$this->render_scanner_tab();\n\t\t\t\t}\n"""
new = """\t\t\t\tif ( 'bulk' === $tab ) {\n\t\t\t\t\t$this->render_bulk_tab();\n\t\t\t\t} elseif ( 'audit' === $tab ) {\n\t\t\t\t\t$this->render_audit_tab();\n\t\t\t\t} elseif ( 'duplicates' === $tab ) {\n\t\t\t\t\t$this->render_duplicates_tab();\n\t\t\t\t} elseif ( 'help' === $tab ) {\n\t\t\t\t\t$this->render_help_tab();\n\t\t\t\t} else {\n\t\t\t\t\t$this->render_scanner_tab();\n\t\t\t\t}\n"""
assert old in text
text = text.replace(old, new, 1)

old = """\t\t\t'scanner' => __( 'Scanner', 'media-reference-inspector' ),\n\t\t\t'bulk'        => __( 'Bulk Scan', 'media-reference-inspector' ),\n\t\t\t'help'        => __( 'Help', 'media-reference-inspector' ),\n"""
new = """\t\t\t'scanner'    => __( 'Scanner', 'media-reference-inspector' ),\n\t\t\t'bulk'       => __( 'Bulk Scan', 'media-reference-inspector' ),\n\t\t\t'audit'      => __( 'Page Audit', 'media-reference-inspector' ),\n\t\t\t'duplicates' => __( 'Duplicates', 'media-reference-inspector' ),\n\t\t\t'help'       => __( 'Help', 'media-reference-inspector' ),\n"""
assert old in text
text = text.replace(old, new, 1)

old = """\t\t\t\t\t<a class=\"nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>\" href=\"<?php echo esc_url( $url ); ?>\" <?php echo $tab === $tab_key ? 'aria-current=\"page\"' : ''; ?>><?php echo esc_html( $label ); ?></a>\n"""
new = """\t\t\t\t\t<a class=\"nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>\" href=\"<?php echo esc_url( $url ); ?>\" <?php echo $tab === $tab_key ? 'aria-current=\"page\"' : ''; ?>><?php echo esc_html( $label ); ?><?php if ( $this->is_new_feature( $tab_key ) ) : ?><span class=\"mediarefinspector-new-badge\"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span><?php endif; ?></a>\n"""
assert old in text
text = text.replace(old, new, 1)

old = """\t\t\t\t<span><strong><?php esc_html_e( 'Elementor', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Validated saved media controls', 'media-reference-inspector' ); ?></span>\n\t\t\t</div>\n\n\t\t\t<?php $this->maybe_render_scan_results(); ?>\n"""
new = """\t\t\t\t<span><strong><?php esc_html_e( 'Elementor', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Validated saved media controls', 'media-reference-inspector' ); ?></span>\n\t\t\t\t<span><strong><?php esc_html_e( 'ACF', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Confirmed Image, File and Gallery field references', 'media-reference-inspector' ); ?></span>\n\t\t\t</div>\n\n\t\t\t<?php $this->render_whats_new_panel(); ?>\n\t\t\t<?php $this->maybe_render_scan_results(); ?>\n"""
assert old in text
text = text.replace(old, new, 1)

old = """\t\t\t<div class=\"mediarefinspector-bulk-filter-row\" id=\"mediarefinspector-bulk-filter-row\" hidden>\n\t\t\t\t<label for=\"mediarefinspector-result-filter\"><?php esc_html_e( 'Show results', 'media-reference-inspector' ); ?></label>\n\t\t\t\t<select id=\"mediarefinspector-result-filter\">\n\t\t\t\t\t<option value=\"all\"><?php esc_html_e( 'All results', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"referenced\"><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"unreferenced\"><?php esc_html_e( 'No supported references', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"error\"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option>\n\t\t\t\t</select>\n\t\t\t</div>\n"""
new = """\t\t\t<div class=\"mediarefinspector-bulk-filter-row\" id=\"mediarefinspector-bulk-filter-row\" hidden>\n\t\t\t\t<div><label for=\"mediarefinspector-result-filter\"><?php esc_html_e( 'Show results', 'media-reference-inspector' ); ?></label>\n\t\t\t\t<select id=\"mediarefinspector-result-filter\">\n\t\t\t\t\t<option value=\"all\"><?php esc_html_e( 'All results', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"referenced\"><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"unreferenced\"><?php esc_html_e( 'Potential unused review', 'media-reference-inspector' ); ?></option>\n\t\t\t\t\t<option value=\"error\"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option>\n\t\t\t\t</select></div>\n\t\t\t\t<div><label for=\"mediarefinspector-result-sort\"><?php esc_html_e( 'Sort results', 'media-reference-inspector' ); ?></label>\n\t\t\t\t<select id=\"mediarefinspector-result-sort\"><option value=\"scan\"><?php esc_html_e( 'Scan order', 'media-reference-inspector' ); ?></option><option value=\"references-desc\"><?php esc_html_e( 'Most references', 'media-reference-inspector' ); ?></option><option value=\"references-asc\"><?php esc_html_e( 'Fewest references', 'media-reference-inspector' ); ?></option><option value=\"title\"><?php esc_html_e( 'Title A–Z', 'media-reference-inspector' ); ?></option></select></div>\n\t\t\t</div>\n"""
assert old in text
text = text.replace(old, new, 1)

old = """\t\t\t<?php $this->render_attachment_parent_note( $attachment_id ); ?>\n"""
new = """\t\t\t<?php $this->render_file_health( $attachment_id ); ?>\n\t\t\t<?php $this->render_attachment_parent_note( $attachment_id ); ?>\n"""
assert old in text
text = text.replace(old, new, 1)

old = """\t\treturn in_array( $tab, array( 'scanner', 'bulk', 'help' ), true ) ? $tab : 'scanner';\n"""
new = """\t\treturn in_array( $tab, array( 'scanner', 'bulk', 'audit', 'duplicates', 'help' ), true ) ? $tab : 'scanner';\n"""
assert old in text
text = text.replace(old, new, 1)

marker = """\n\t/**\n\t * Renders contextual help and plugin support.\n"""
assert marker in text
methods = r'''

	/**
	 * Shows a compact What's New card until new feature tabs have been visited.
	 *
	 * @return void
	 */
	private function render_whats_new_panel() {
		if ( ! $this->is_new_feature( 'audit' ) && ! $this->is_new_feature( 'duplicates' ) ) {
			return;
		}
		?>
		<div class="mediarefinspector-whats-new mediarefinspector-panel">
			<div><span class="mediarefinspector-new-badge"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span><strong><?php esc_html_e( 'New audit tools are ready to test', 'media-reference-inspector' ); ?></strong></div>
			<p><?php esc_html_e( 'Audit media used by a page or post, check broken attachment IDs and file health, find exact duplicate files, and detect confirmed ACF media fields.', 'media-reference-inspector' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders page/post media audit results.
	 *
	 * @return void
	 */
	private function render_audit_tab() {
		$search  = isset( $_GET['audit_s'] ) ? sanitize_text_field( wp_unslash( $_GET['audit_s'] ) ) : '';
		$post_id = isset( $_GET['audit_post_id'] ) ? absint( $_GET['audit_post_id'] ) : 0;
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-audit-heading">
			<div class="mediarefinspector-section-heading"><div><h2 id="mediarefinspector-audit-heading"><?php esc_html_e( 'Page & post media audit', 'media-reference-inspector' ); ?> <span class="mediarefinspector-new-badge"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span></h2><p><?php esc_html_e( 'Choose a post or page to see supported media references, broken attachment IDs, and local file-health information. Nothing is modified.', 'media-reference-inspector' ); ?></p></div></div>
			<form method="get" class="mediarefinspector-filter-form"><input type="hidden" name="page" value="media-reference-inspector" /><input type="hidden" name="tab" value="audit" /><div class="mediarefinspector-field mediarefinspector-field-grow"><label for="mediarefinspector-audit-search"><?php esc_html_e( 'Search posts/pages', 'media-reference-inspector' ); ?></label><input id="mediarefinspector-audit-search" type="search" name="audit_s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search title', 'media-reference-inspector' ); ?>" /></div><div class="mediarefinspector-field mediarefinspector-field-action"><button class="button button-secondary" type="submit"><?php esc_html_e( 'Search', 'media-reference-inspector' ); ?></button></div></form>
			<?php
			if ( $post_id ) {
				$nonce = isset( $_GET['mediarefinspector_audit_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_audit_nonce'] ) ) : '';
				if ( wp_verify_nonce( $nonce, 'mediarefinspector_audit_post_' . $post_id ) ) {
					$this->render_post_audit_result( $post_id );
				} else {
					$this->render_notice( __( 'The audit request could not be verified. Please choose the post again.', 'media-reference-inspector' ), 'error' );
				}
			}
			$args = array( 'post_type' => get_post_types( array( 'public' => true ), 'names' ), 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 30, 'orderby' => 'modified', 'order' => 'DESC', 'post_type__not_in' => array( 'attachment' ) );
			if ( $search ) { $args['s'] = $search; }
			$query = new WP_Query( $args );
			?>
			<div class="mediarefinspector-audit-list">
			<?php foreach ( $query->posts as $post ) : $url = wp_nonce_url( add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'audit', 'audit_post_id' => $post->ID, 'audit_s' => $search ), admin_url( 'upload.php' ) ), 'mediarefinspector_audit_post_' . $post->ID, 'mediarefinspector_audit_nonce' ); ?>
				<article class="mediarefinspector-panel mediarefinspector-audit-item"><div><strong><?php echo esc_html( get_the_title( $post ) ? get_the_title( $post ) : sprintf( __( 'Post #%d', 'media-reference-inspector' ), $post->ID ) ); ?></strong><p><?php echo esc_html( $post->post_type . ' · ' . $post->post_status . ' · #' . $post->ID ); ?></p></div><a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Audit media', 'media-reference-inspector' ); ?></a></article>
			<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Renders one post audit result.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_post_audit_result( $post_id ) {
		$service = new MediaRefInspector_Audit_Service();
		$result  = $service->audit_post( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post ) { return; }
		?>
		<div class="mediarefinspector-panel mediarefinspector-audit-result"><div class="mediarefinspector-section-heading mediarefinspector-section-heading-split"><div><span class="mediarefinspector-eyebrow"><?php esc_html_e( 'Audit result', 'media-reference-inspector' ); ?></span><h3><?php echo esc_html( get_the_title( $post ) ? get_the_title( $post ) : '#' . $post_id ); ?></h3></div><div class="mediarefinspector-audit-counts"><span><strong><?php echo esc_html( (string) count( $result['media'] ) ); ?></strong> <?php esc_html_e( 'media found', 'media-reference-inspector' ); ?></span><span class="<?php echo empty( $result['broken'] ) ? 'is-success' : 'is-warning'; ?>"><strong><?php echo esc_html( (string) count( $result['broken'] ) ); ?></strong> <?php esc_html_e( 'broken IDs', 'media-reference-inspector' ); ?></span></div></div>
		<?php if ( ! empty( $result['broken'] ) ) : ?><div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Broken media references need review.', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'These attachment IDs are referenced in supported content but no longer resolve to Media Library attachments:', 'media-reference-inspector' ); ?> <?php echo esc_html( implode( ', ', wp_list_pluck( $result['broken'], 'id' ) ) ); ?></p></div><?php endif; ?>
		<div class="mediarefinspector-audit-media-grid">
		<?php foreach ( $result['media'] as $item ) : ?>
			<article class="mediarefinspector-audit-media"><div><strong><?php echo esc_html( $item['title'] ? $item['title'] : sprintf( __( 'Media #%d', 'media-reference-inspector' ), $item['id'] ) ); ?></strong><p><?php echo esc_html( implode( ' · ', $item['sources'] ) ); ?></p></div><span class="mediarefinspector-health-pill is-<?php echo esc_attr( $item['health']['status'] ); ?>"><?php echo esc_html( 'healthy' === $item['health']['status'] ? __( 'File healthy', 'media-reference-inspector' ) : __( 'Needs review', 'media-reference-inspector' ) ); ?></span><a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $item['id'], 'raw' ) ); ?>"><?php esc_html_e( 'Edit media', 'media-reference-inspector' ); ?></a></article>
		<?php endforeach; ?>
		</div></div>
		<?php
	}

	/**
	 * Renders the exact duplicate-file finder.
	 *
	 * @return void
	 */
	private function render_duplicates_tab() {
		$run = isset( $_GET['run_duplicates'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['run_duplicates'] ) );
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-duplicates-heading"><div class="mediarefinspector-section-heading mediarefinspector-section-heading-split"><div><h2 id="mediarefinspector-duplicates-heading"><?php esc_html_e( 'Potential duplicate media', 'media-reference-inspector' ); ?> <span class="mediarefinspector-new-badge"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span></h2><p><?php esc_html_e( 'Compare a bounded set of local media files by exact file hash. This tool reports matches only and never deletes anything.', 'media-reference-inspector' ); ?></p></div><?php $run_url = wp_nonce_url( add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'duplicates', 'run_duplicates' => '1' ), admin_url( 'upload.php' ) ), 'mediarefinspector_run_duplicates', 'mediarefinspector_duplicates_nonce' ); ?><a class="button button-primary" href="<?php echo esc_url( $run_url ); ?>"><?php esc_html_e( 'Scan recent 150 files', 'media-reference-inspector' ); ?></a></div>
		<?php
		if ( $run ) {
			$nonce = isset( $_GET['mediarefinspector_duplicates_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_duplicates_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'mediarefinspector_run_duplicates' ) ) { $this->render_notice( __( 'The duplicate scan request could not be verified.', 'media-reference-inspector' ), 'error' ); return; }
			$groups = ( new MediaRefInspector_Audit_Service() )->find_exact_duplicates( 150 );
			if ( empty( $groups ) ) { $this->render_notice( __( 'No exact duplicate files were found in the bounded scan.', 'media-reference-inspector' ), 'success' ); }
			foreach ( $groups as $index => $group ) : ?>
				<div class="mediarefinspector-panel mediarefinspector-duplicate-group"><h3><?php echo esc_html( sprintf( __( 'Duplicate group %1$d · %2$s each', 'media-reference-inspector' ), $index + 1, size_format( $group['size'], 1 ) ) ); ?></h3><?php foreach ( $group['items'] as $item ) : ?><div class="mediarefinspector-duplicate-item"><div><strong><?php echo esc_html( $item['title'] ? $item['title'] : $item['filename'] ); ?></strong><code><?php echo esc_html( $item['filename'] ); ?></code></div><?php if ( $item['edit_url'] ) : ?><a class="button button-small" href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php esc_html_e( 'Review media', 'media-reference-inspector' ); ?></a><?php endif; ?></div><?php endforeach; ?></div>
			<?php endforeach;
		} else { ?>
			<div class="mediarefinspector-empty-state"><span class="dashicons dashicons-images-alt2" aria-hidden="true"></span><h3><?php esc_html_e( 'Ready for an exact duplicate scan', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'For performance, the scan is limited to the most recent 150 readable files and skips files larger than 25 MB.', 'media-reference-inspector' ); ?></p></div>
		<?php } ?>
		</section>
		<?php
	}

	/**
	 * Renders local file-health details for one attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function render_file_health( $attachment_id ) {
		$health = ( new MediaRefInspector_Audit_Service() )->get_file_health( $attachment_id );
		?>
		<div class="mediarefinspector-panel mediarefinspector-file-health"><div class="mediarefinspector-file-health-title"><h3><?php esc_html_e( 'Media file health', 'media-reference-inspector' ); ?></h3><span class="mediarefinspector-health-pill is-<?php echo esc_attr( $health['status'] ); ?>"><?php echo esc_html( 'healthy' === $health['status'] ? __( 'Healthy', 'media-reference-inspector' ) : __( 'Needs review', 'media-reference-inspector' ) ); ?></span></div><div class="mediarefinspector-health-grid"><span><strong><?php esc_html_e( 'Local file', 'media-reference-inspector' ); ?></strong><?php echo esc_html( $health['file_exists'] ? __( 'Found', 'media-reference-inspector' ) : __( 'Missing', 'media-reference-inspector' ) ); ?></span><span><strong><?php esc_html_e( 'Original image', 'media-reference-inspector' ); ?></strong><?php echo esc_html( $health['original_exists'] ? __( 'Available / not required', 'media-reference-inspector' ) : __( 'Missing', 'media-reference-inspector' ) ); ?></span><span><strong><?php esc_html_e( 'Metadata', 'media-reference-inspector' ); ?></strong><?php echo esc_html( $health['metadata_ok'] ? __( 'Looks valid', 'media-reference-inspector' ) : __( 'Incomplete', 'media-reference-inspector' ) ); ?></span><span><strong><?php esc_html_e( 'File details', 'media-reference-inspector' ); ?></strong><?php echo esc_html( trim( ( $health['width'] && $health['height'] ? $health['width'] . '×' . $health['height'] . ' · ' : '' ) . ( $health['file_size'] ? size_format( $health['file_size'], 1 ) : __( 'Size unavailable', 'media-reference-inspector' ) ) ) ); ?></span></div></div>
		<?php
	}

	/**
	 * Returns whether a feature should still display its per-user NEW badge.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	private function is_new_feature( $feature ) {
		if ( ! in_array( $feature, array( 'audit', 'duplicates' ), true ) ) { return false; }
		$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_220', true );
		$seen = is_array( $seen ) ? $seen : array();
		return empty( $seen[ $feature ] );
	}

	/**
	 * Marks the current feature tab as seen after rendering.
	 *
	 * @param string $feature Feature key.
	 * @return void
	 */
	private function mark_feature_seen( $feature ) {
		if ( ! in_array( $feature, array( 'audit', 'duplicates' ), true ) ) { return; }
		$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_220', true );
		$seen = is_array( $seen ) ? $seen : array();
		$seen[ $feature ] = 1;
		update_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_220', $seen );
	}
'''
text = text.replace(marker, methods + marker, 1)

old = """\t\t</div>\n\t\t<?php\n\t}\n\n\t/**\n\t * Renders the branded WordPress-native page header and navigation.\n"""
new = """\t\t</div>\n\t\t<?php\n\t\t$this->mark_feature_seen( $tab );\n\t}\n\n\t/**\n\t * Renders the branded WordPress-native page header and navigation.\n"""
assert old in text
text = text.replace(old, new, 1)
admin.write_text(text)

css = Path('assets/css/admin.css')
c = css.read_text()
c = c.replace('grid-template-columns: repeat(4, minmax(0, 1fr));', 'grid-template-columns: repeat(5, minmax(0, 1fr));', 1)
c += r'''

/* 2.2.0 beta.2 audit tools. */
.mediarefinspector-new-badge { display: inline-flex; margin-left: 6px; padding: 1px 6px; border-radius: 999px; background: #d63638; color: #fff; font-size: 10px; font-weight: 700; line-height: 1.6; vertical-align: middle; }
.mediarefinspector-whats-new { margin: 0 0 18px; border-left: 4px solid #2271b1; }
.mediarefinspector-whats-new > div { display: flex; gap: 8px; align-items: center; }
.mediarefinspector-whats-new p { margin: 8px 0 0; color: var(--mri-muted); }
.mediarefinspector-audit-list { display: grid; gap: 10px; }
.mediarefinspector-audit-item, .mediarefinspector-duplicate-item, .mediarefinspector-audit-media { display: flex; gap: 14px; align-items: center; justify-content: space-between; }
.mediarefinspector-audit-item p, .mediarefinspector-audit-media p { margin: 4px 0 0; color: var(--mri-muted); }
.mediarefinspector-audit-result { margin: 0 0 18px; }
.mediarefinspector-audit-counts { display: flex; gap: 12px; flex-wrap: wrap; }
.mediarefinspector-audit-counts span { padding: 8px 10px; border: 1px solid var(--mri-border); border-radius: 6px; background: #fff; }
.mediarefinspector-audit-counts .is-warning { border-color: #dba617; background: #fff8e5; }
.mediarefinspector-audit-media-grid { display: grid; gap: 8px; margin-top: 14px; }
.mediarefinspector-audit-media { padding: 12px; border: 1px solid var(--mri-border); border-radius: 7px; }
.mediarefinspector-health-pill { display: inline-flex; flex: 0 0 auto; padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.mediarefinspector-health-pill.is-healthy { background: #edfaef; color: #0a6b2b; }
.mediarefinspector-health-pill.is-review { background: #fff8e5; color: #8a6116; }
.mediarefinspector-file-health { margin: 16px 0; }
.mediarefinspector-file-health-title { display: flex; gap: 12px; align-items: center; justify-content: space-between; }
.mediarefinspector-file-health-title h3 { margin: 0; }
.mediarefinspector-health-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
.mediarefinspector-health-grid span { display: flex; flex-direction: column; gap: 3px; padding: 10px; border: 1px solid var(--mri-border); border-radius: 6px; }
.mediarefinspector-health-grid strong { font-size: 12px; }
.mediarefinspector-duplicate-group { margin-bottom: 12px; }
.mediarefinspector-duplicate-group h3 { margin-top: 0; }
.mediarefinspector-duplicate-item { padding: 10px 0; border-top: 1px solid var(--mri-border); }
.mediarefinspector-duplicate-item code { display: block; margin-top: 4px; overflow-wrap: anywhere; }
.mediarefinspector-bulk-filter-row { display: flex; gap: 14px; align-items: end; flex-wrap: wrap; }
.mediarefinspector-bulk-filter-row > div { display: flex; min-width: 180px; flex-direction: column; gap: 5px; }
@media screen and (max-width: 900px) { .mediarefinspector-health-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media screen and (max-width: 782px) { .mediarefinspector-audit-item, .mediarefinspector-duplicate-item, .mediarefinspector-audit-media, .mediarefinspector-file-health-title { align-items: stretch; flex-direction: column; } .mediarefinspector-health-grid { grid-template-columns: 1fr; } .mediarefinspector-bulk-filter-row > div { width: 100%; min-width: 0; } }
'''
css.write_text(c)

js = Path('assets/js/admin.js')
j = js.read_text()
j = j.replace("var resultFilter = document.getElementById('mediarefinspector-result-filter');", "var resultFilter = document.getElementById('mediarefinspector-result-filter');\n\tvar resultSort = document.getElementById('mediarefinspector-result-sort');", 1)
j = j.replace("row.dataset.status = item.status;", "row.dataset.status = item.status;\n\t\trow.dataset.references = String(item.referenceCount || 0);\n\t\trow.dataset.title = String(item.title || '').toLowerCase();", 1)
old_js = """\tfunction applyResultFilter() {\n\t\tvar filter = resultFilter.value || 'all';\n\t\tArray.prototype.forEach.call(resultsBody.querySelectorAll('tr'), function (row) {\n\t\t\trow.hidden = filter !== 'all' && row.dataset.status !== filter;\n\t\t});\n\t}\n"""
new_js = """\tfunction applyResultFilter() {\n\t\tvar filter = resultFilter.value || 'all';\n\t\tvar rows = Array.prototype.slice.call(resultsBody.querySelectorAll('tr'));\n\t\tvar sort = resultSort ? (resultSort.value || 'scan') : 'scan';\n\t\tif (sort !== 'scan') {\n\t\t\trows.sort(function (a, b) {\n\t\t\t\tif (sort === 'title') { return (a.dataset.title || '').localeCompare(b.dataset.title || ''); }\n\t\t\t\tvar av = parseInt(a.dataset.references || '0', 10);\n\t\t\t\tvar bv = parseInt(b.dataset.references || '0', 10);\n\t\t\t\treturn sort === 'references-asc' ? av - bv : bv - av;\n\t\t\t});\n\t\t\trows.forEach(function (row) { resultsBody.appendChild(row); });\n\t\t}\n\t\trows.forEach(function (row) { row.hidden = filter !== 'all' && row.dataset.status !== filter; });\n\t}\n"""
assert old_js in j
j = j.replace(old_js, new_js, 1)
j = j.replace("resultFilter.addEventListener('change', applyResultFilter);", "resultFilter.addEventListener('change', applyResultFilter);\n\tif (resultSort) { resultSort.addEventListener('change', applyResultFilter); }", 1)
js.write_text(j)

readme = Path('readme.txt')
r = readme.read_text()
r = r.replace("* Elementor saved media-control references when the saved JSON confirms the attachment ID.\n", "* Elementor saved media-control references when the saved JSON confirms the attachment ID.\n* Advanced Custom Fields (ACF) Image, File, and Gallery fields when ACF confirms the field type and saved attachment ID.\n")
r = r.replace("= Bulk Scan =\n\nBulk Scan processes media items one at a time through bounded AJAX requests. It supports media type, upload-age, search, and maximum-item filters, live progress, result filtering, and CSV export.\n", "= Bulk Scan =\n\nBulk Scan processes media items one at a time through bounded AJAX requests. It supports media type, upload-age, search, maximum-item filters, live progress, potential-unused review filtering, result sorting, and CSV export.\n\n= Page & Post Audit =\n\nAudit a post or page to list supported media attachment IDs, flag broken attachment IDs, and review local file health without modifying content.\n\n= Duplicate Finder =\n\nRun an on-demand, bounded exact-file hash scan of recent local Media Library files. The tool reports exact matches only and never deletes files.\n")
r = r.replace("= 2.2.0-beta.1 =\n", "= 2.2.0-beta.2 =\n* Added per-user NEW badges and a What's New card that disappear after the new feature tabs are visited.\n* Added Page & Post Media Audit with supported media listing, broken attachment-ID review, and file-health status.\n* Added Media File Health to single-item scan results.\n* Added a bounded exact Duplicate Finder for recent readable local files.\n* Added confirmed ACF Image, File, and Gallery field reference detection when ACF is active.\n* Added Bulk Scan result sorting and clearer Potential unused review wording.\n\n= 2.2.0-beta.1 =\n", 1)
readme.write_text(r)
