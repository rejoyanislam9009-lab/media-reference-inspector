from pathlib import Path
import re

plugin = Path('media-reference-inspector.php')
text = plugin.read_text()
text = text.replace(' * Version:           2.1.0', ' * Version:           2.2.0-beta.1')
text = text.replace("define( 'MEDIAREFINSPECTOR_VERSION', '2.1.0' );", "define( 'MEDIAREFINSPECTOR_VERSION', '2.2.0-beta.1' );")
plugin.write_text(text)

admin = Path('includes/class-mediarefinspector-plugin.php')
text = admin.read_text()
text = text.replace("\t\tadd_action( 'admin_post_mediarefinspector_refresh_updates', array( $this, 'handle_refresh_updates' ) );\n", "\t\tadd_action( 'admin_post_mediarefinspector_send_support_email', array( $this, 'handle_support_email' ) );\n")
text = text.replace("\t\t\t\t} elseif ( 'diagnostics' === $tab ) {\n\t\t\t\t\t$this->render_diagnostics_tab();\n", '')
text = text.replace("\t\t\t'diagnostics' => __( 'Diagnostics', 'media-reference-inspector' ),\n", '')
text = text.replace("return in_array( $tab, array( 'scanner', 'bulk', 'diagnostics', 'help' ), true ) ? $tab : 'scanner';", "return in_array( $tab, array( 'scanner', 'bulk', 'help' ), true ) ? $tab : 'scanner';")

help_start = text.index("\t/**\n\t * Renders contextual help and supported checks.")
diag_start = text.index("\n\n\t/**\n\t * Handles a manual refresh of WordPress core plugin update metadata.", help_start)
new_help = r'''\t/**
\t * Renders contextual help and plugin support.
\t *
\t * @return void
\t */
\tprivate function render_help_tab() {
\t\t$current_user  = wp_get_current_user();
\t\t$support_state = isset( $_GET['support_status'] ) ? sanitize_key( wp_unslash( $_GET['support_status'] ) ) : '';
\t\t?>
\t\t<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-help-heading">
\t\t\t<div class="mediarefinspector-section-heading">
\t\t\t\t<div>
\t\t\t\t\t<h2 id="mediarefinspector-help-heading"><?php esc_html_e( 'How the inspector works', 'media-reference-inspector' ); ?></h2>
\t\t\t\t\t<p><?php esc_html_e( 'The scanner checks supported WordPress and integration data locally. It does not send media or site data to an external service.', 'media-reference-inspector' ); ?></p>
\t\t\t\t</div>
\t\t\t</div>

\t\t\t<?php if ( 'sent' === $support_state ) : ?>
\t\t\t\t<div class="notice notice-success inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'Your support message was sent successfully.', 'media-reference-inspector' ); ?></p></div>
\t\t\t<?php elseif ( 'invalid' === $support_state ) : ?>
\t\t\t\t<div class="notice notice-error inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'Please enter a valid email address, subject, and message.', 'media-reference-inspector' ); ?></p></div>
\t\t\t<?php elseif ( 'rate_limited' === $support_state ) : ?>
\t\t\t\t<div class="notice notice-warning inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'Please wait a minute before sending another support message.', 'media-reference-inspector' ); ?></p></div>
\t\t\t<?php elseif ( 'failed' === $support_state ) : ?>
\t\t\t\t<div class="notice notice-error inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'WordPress could not send the email. Your hosting mail configuration may need attention.', 'media-reference-inspector' ); ?></p></div>
\t\t\t<?php endif; ?>

\t\t\t<div class="mediarefinspector-help-grid">
\t\t\t\t<div class="mediarefinspector-panel">
\t\t\t\t\t<h3><?php esc_html_e( 'Standard WordPress checks', 'media-reference-inspector' ); ?></h3>
\t\t\t\t\t<ul class="mediarefinspector-check-list">
\t\t\t\t\t\t<li><?php esc_html_e( 'Post, page, and custom post type content and excerpts', 'media-reference-inspector' ); ?></li>
\t\t\t\t\t\t<li><?php esc_html_e( 'Generated image-size URLs and WordPress media blocks', 'media-reference-inspector' ); ?></li>
\t\t\t\t\t\t<li><?php esc_html_e( 'Featured images and navigation menu URLs', 'media-reference-inspector' ); ?></li>
\t\t\t\t\t\t<li><?php esc_html_e( 'Core media widgets and block widgets', 'media-reference-inspector' ); ?></li>
\t\t\t\t\t\t<li><?php esc_html_e( 'Site Icon, Site Logo, Custom Logo, Header Image, and Background Image', 'media-reference-inspector' ); ?></li>
\t\t\t\t\t</ul>
\t\t\t\t</div>
\t\t\t\t<div class="mediarefinspector-panel">
\t\t\t\t\t<h3><?php esc_html_e( 'Integration-aware checks', 'media-reference-inspector' ); ?></h3>
\t\t\t\t\t<ul class="mediarefinspector-check-list">
\t\t\t\t\t\t<li><?php esc_html_e( 'WooCommerce product gallery and product-category thumbnail attachment IDs', 'media-reference-inspector' ); ?></li>
\t\t\t\t\t\t<li><?php esc_html_e( 'Elementor media-control data saved in Elementor JSON', 'media-reference-inspector' ); ?></li>
\t\t\t\t\t</ul>
\t\t\t\t\t<p class="description"><?php esc_html_e( 'These checks are passive: if matching plugin data is not present, no extra work is performed beyond the focused lookups.', 'media-reference-inspector' ); ?></p>
\t\t\t\t</div>
\t\t\t\t<div class="mediarefinspector-panel mediarefinspector-panel-warning">
\t\t\t\t\t<h3><?php esc_html_e( 'Important limitation', 'media-reference-inspector' ); ?></h3>
\t\t\t\t\t<p><?php esc_html_e( 'No scanner can prove that media is unused across every custom table, external service, theme, builder, shortcode, cache, or custom code path. Treat results as evidence for review, not as automatic deletion instructions.', 'media-reference-inspector' ); ?></p>
\t\t\t\t</div>
\t\t\t\t<div class="mediarefinspector-panel mediarefinspector-support-panel">
\t\t\t\t\t<div class="mediarefinspector-support-heading">
\t\t\t\t\t\t<div><h3><?php esc_html_e( 'Contact plugin support', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Report a bug or request a feature directly from WordPress admin.', 'media-reference-inspector' ); ?></p></div>
\t\t\t\t\t\t<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
\t\t\t\t\t</div>
\t\t\t\t\t<form class="mediarefinspector-support-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
\t\t\t\t\t\t<input type="hidden" name="action" value="mediarefinspector_send_support_email" />
\t\t\t\t\t\t<?php wp_nonce_field( 'mediarefinspector_send_support_email' ); ?>
\t\t\t\t\t\t<div class="mediarefinspector-support-field"><label for="mediarefinspector-support-type"><?php esc_html_e( 'Request type', 'media-reference-inspector' ); ?></label><select id="mediarefinspector-support-type" name="support_type"><option value="bug"><?php esc_html_e( 'Bug report', 'media-reference-inspector' ); ?></option><option value="feature"><?php esc_html_e( 'Feature request', 'media-reference-inspector' ); ?></option><option value="question"><?php esc_html_e( 'General question', 'media-reference-inspector' ); ?></option></select></div>
\t\t\t\t\t\t<div class="mediarefinspector-support-field"><label for="mediarefinspector-support-email"><?php esc_html_e( 'Your email', 'media-reference-inspector' ); ?></label><input id="mediarefinspector-support-email" name="support_email" type="email" required value="<?php echo esc_attr( $current_user->user_email ); ?>" autocomplete="email" /></div>
\t\t\t\t\t\t<div class="mediarefinspector-support-field mediarefinspector-support-field-wide"><label for="mediarefinspector-support-subject"><?php esc_html_e( 'Subject', 'media-reference-inspector' ); ?></label><input id="mediarefinspector-support-subject" name="support_subject" type="text" maxlength="120" required placeholder="<?php echo esc_attr__( 'Briefly describe the issue or feature', 'media-reference-inspector' ); ?>" /></div>
\t\t\t\t\t\t<div class="mediarefinspector-support-field mediarefinspector-support-field-wide"><label for="mediarefinspector-support-message"><?php esc_html_e( 'Message', 'media-reference-inspector' ); ?></label><textarea id="mediarefinspector-support-message" name="support_message" rows="7" maxlength="4000" required placeholder="<?php echo esc_attr__( 'Tell us what happened, what you expected, or what feature you would like.', 'media-reference-inspector' ); ?>"></textarea></div>
\t\t\t\t\t\t<div class="mediarefinspector-support-actions mediarefinspector-support-field-wide"><button type="submit" class="button button-primary"><?php esc_html_e( 'Send support email', 'media-reference-inspector' ); ?></button><p class="description"><?php esc_html_e( 'Nothing is sent until you press this button. Delivery uses this WordPress site’s configured mail system.', 'media-reference-inspector' ); ?></p></div>
\t\t\t\t\t</form>
\t\t\t\t</div>
\t\t\t</div>
\t\t</section>
\t\t<?php
\t}

\t/**
\t * Sends an explicitly submitted support email.
\t *
\t * @return void
\t */
\tpublic function handle_support_email() {
\t\tif ( ! current_user_can( 'manage_options' ) ) {
\t\t\twp_die( esc_html__( 'You do not have permission to send plugin support messages.', 'media-reference-inspector' ) );
\t\t}
\t\tcheck_admin_referer( 'mediarefinspector_send_support_email' );
\t\t$type    = isset( $_POST['support_type'] ) ? sanitize_key( wp_unslash( $_POST['support_type'] ) ) : 'question';
\t\t$email   = isset( $_POST['support_email'] ) ? sanitize_email( wp_unslash( $_POST['support_email'] ) ) : '';
\t\t$subject = isset( $_POST['support_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['support_subject'] ) ) : '';
\t\t$message = isset( $_POST['support_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['support_message'] ) ) : '';
\t\tif ( ! in_array( $type, array( 'bug', 'feature', 'question' ), true ) ) { $type = 'question'; }
\t\tif ( ! is_email( $email ) || '' === $subject || '' === $message ) { $this->redirect_support_result( 'invalid' ); }
\t\t$user_id  = get_current_user_id();
\t\t$rate_key = 'mediarefinspector_support_' . $user_id;
\t\tif ( get_transient( $rate_key ) ) { $this->redirect_support_result( 'rate_limited' ); }
\t\t$labels = array( 'bug' => __( 'Bug report', 'media-reference-inspector' ), 'feature' => __( 'Feature request', 'media-reference-inspector' ), 'question' => __( 'General question', 'media-reference-inspector' ) );
\t\t$support_email = sanitize_email( apply_filters( 'mediarefinspector_support_email', 'rejoyanislam9009@gmail.com' ) );
\t\tif ( ! is_email( $support_email ) ) { $this->redirect_support_result( 'failed' ); }
\t\t$mail_subject = sprintf( '[Media Reference Inspector] %s: %s', $labels[ $type ], $subject );
\t\t$mail_body = sprintf( "Request type: %s\nReply email: %s\nPlugin version: %s\nWordPress version: %s\n\nMessage:\n%s", $labels[ $type ], $email, MEDIAREFINSPECTOR_VERSION, get_bloginfo( 'version' ), $message );
\t\t$sent = wp_mail( $support_email, $mail_subject, $mail_body, array( 'Reply-To: ' . $email ) );
\t\tif ( $sent ) { set_transient( $rate_key, 1, MINUTE_IN_SECONDS ); $this->redirect_support_result( 'sent' ); }
\t\t$this->redirect_support_result( 'failed' );
\t}

\t/**
\t * Redirects back to Help with a support form status.
\t *
\t * @param string $status Support status key.
\t * @return void
\t */
\tprivate function redirect_support_result( $status ) {
\t\t$url = add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'help', 'support_status' => sanitize_key( $status ) ), admin_url( 'upload.php' ) );
\t\twp_safe_redirect( $url );
\t\texit;
\t}
'''
text = text[:help_start] + new_help + text[diag_start:]
diag_start = text.index("\n\n\t/**\n\t * Handles a manual refresh of WordPress core plugin update metadata.")
scan_start = text.index("\n\n\t/**\n\t * Renders single-scan results when a nonce-protected attachment is requested.", diag_start)
text = text[:diag_start] + text[scan_start:]
admin.write_text(text)

css = Path('assets/css/admin.css')
c = css.read_text()
c = c.replace(".mediarefinspector-filter-form,\n.mediarefinspector-bulk-controls {\n\tdisplay: flex;\n\tgap: 14px;\n\talign-items: flex-end;\n\tmargin: 0 0 18px;\n}", ".mediarefinspector-filter-form,\n.mediarefinspector-bulk-controls {\n\tdisplay: flex;\n\tgap: 14px;\n\talign-items: flex-end;\n\tflex-wrap: wrap;\n\tmargin: 0 0 18px;\n}\n\n.mediarefinspector-admin [hidden] { display: none !important; }\n\n.mediarefinspector-bulk-controls .mediarefinspector-field { min-width: 0; flex: 1 1 170px; }\n.mediarefinspector-bulk-controls .mediarefinspector-field:first-child { flex: 2 1 240px; }\n.mediarefinspector-bulk-controls .mediarefinspector-field-action { display: flex; gap: 8px; align-items: flex-end; justify-content: flex-start; flex: 1 1 100%; flex-direction: row; }")
support_css = r'''

.mediarefinspector-support-panel { grid-column: 1 / -1; }
.mediarefinspector-support-heading { display: flex; gap: 16px; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; }
.mediarefinspector-support-heading p { margin: 4px 0 0; color: var(--mri-muted); }
.mediarefinspector-support-heading .dashicons { width: 30px; height: 30px; font-size: 30px; color: var(--mri-accent); }
.mediarefinspector-support-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.mediarefinspector-support-field { display: flex; min-width: 0; flex-direction: column; gap: 6px; }
.mediarefinspector-support-field label { font-weight: 600; }
.mediarefinspector-support-field input,
.mediarefinspector-support-field select,
.mediarefinspector-support-field textarea { width: 100%; max-width: none; box-sizing: border-box; }
.mediarefinspector-support-field-wide { grid-column: 1 / -1; }
.mediarefinspector-support-actions { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-direction: row; }
.mediarefinspector-support-actions p { margin: 0; }

@media screen and (max-width: 1200px) {
\t.mediarefinspector-bulk-controls { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: end; }
\t.mediarefinspector-bulk-controls .mediarefinspector-field,
\t.mediarefinspector-bulk-controls .mediarefinspector-field:first-child,
\t.mediarefinspector-bulk-controls .mediarefinspector-field-action { width: auto; min-width: 0; flex: none; }
\t.mediarefinspector-bulk-controls .mediarefinspector-field-action { grid-column: 1 / -1; }
\t.mediarefinspector-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media screen and (max-width: 782px) {
\t.mediarefinspector-bulk-controls,
\t.mediarefinspector-support-form { grid-template-columns: 1fr; }
\t.mediarefinspector-bulk-controls .mediarefinspector-field-action,
\t.mediarefinspector-support-field-wide { grid-column: auto; }
\t.mediarefinspector-bulk-controls .mediarefinspector-field-action,
\t.mediarefinspector-support-actions { align-items: stretch; flex-direction: column; }
\t.mediarefinspector-bulk-controls .mediarefinspector-field-action .button,
\t.mediarefinspector-support-actions .button { width: 100%; min-height: 40px; }
}
'''
marker = "\n@media (prefers-reduced-motion: reduce) {"
c = c.replace(marker, support_css + marker)
c = re.sub(r"\n\.mediarefinspector-diagnostics-grid \{.*?\n\.mediarefinspector-diagnostic-status\.is-success \{\n\tcolor: var\(--mri-success\);\n\}\n", "\n", c, flags=re.S)
c = c.replace("\t.mediarefinspector-coverage-strip,\n\t.mediarefinspector-diagnostics-grid {", "\t.mediarefinspector-coverage-strip {")
c = c.replace("\t.mediarefinspector-coverage-strip,\n\t.mediarefinspector-diagnostics-grid,\n\t.mediarefinspector-diagnostics-details dl {", "\t.mediarefinspector-coverage-strip {")
css.write_text(c)

readme = Path('readme.txt')
r = readme.read_text()
r = re.sub(r"\n= Diagnostics =\n\n.*?\n= Privacy =", "\n= Support =\n\nThe Help tab includes an explicit support form for bug reports, feature requests, and questions. Nothing is sent until an administrator submits the form. The message and reply email are sent through the site's configured WordPress mail system to plugin support.\n\n= Privacy =", r, flags=re.S)
r = r.replace('4. Use Scanner for one media item, Bulk Scan for an audit batch, or Diagnostics for update/status information.', '4. Use Scanner for one media item, Bulk Scan for an audit batch, or Help for documentation and support.')
r = r.replace('No. It is intentionally read-only for media and content. The Diagnostics refresh action only refreshes WordPress core plugin-update cache data.', 'No. It is intentionally read-only for media and content.')
r = re.sub(r"\n= What does the Diagnostics tab check\? =\n\n.*?\n= Who can use the scanner\? =", "\n= How does the support form work? =\n\nAn administrator can explicitly submit a bug report, feature request, or question from the Help tab. The form sends only the entered reply email and message plus the plugin and WordPress version through the site's configured WordPress mail system.\n\n= Who can use the scanner? =", r, flags=re.S)
r = r.replace('The scanner requires the `manage_options` capability because results may reveal references to non-public content. Refreshing update metadata also requires the `update_plugins` capability.', 'The scanner and support form require the `manage_options` capability because scan results may reveal references to non-public content.')
r = r.replace('Media Reference Inspector does not send media or site data to external analytics or telemetry services and stores no scan history.', 'Media Reference Inspector does not send media or site data to analytics or telemetry services and stores no scan history. The Help support form sends a message only after an administrator explicitly submits it.')
changelog = "\n= 2.2.0-beta.1 =\n* Removed the temporary Diagnostics tab now that normal WordPress.org updates are verified.\n* Rebuilt Bulk Scan controls for responsive desktop, tablet, and mobile layouts, including desktop-sized mobile viewports.\n* Added an explicit Help-tab support email form for bug reports, feature requests, and general questions.\n* Added nonce, capability, sanitization, rate-limit, and mail-delivery feedback protections to the support form.\n* Preserved all 2.1.0 scanner, integration, CSV, and read-only behavior.\n\n"
r = r.replace('== Changelog ==\n\n', '== Changelog ==\n' + changelog)
readme.write_text(r)
