<?php
/**
 * Admin interface for Media Reference Inspector.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the Media Reference Inspector admin experience.
 */
class MediaRefInspector_Plugin {

	/**
	 * Scanner service.
	 *
	 * @var MediaRefInspector_Scanner
	 */
	private $scanner;

	/**
	 * Admin page hook suffix.
	 *
	 * @var string|false
	 */
	private $page_hook = false;

	/**
	 * Constructor.
	 *
	 * @param MediaRefInspector_Scanner $scanner Scanner service.
	 */
	public function __construct( MediaRefInspector_Scanner $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_mediarefinspector_get_bulk_ids', array( $this, 'ajax_get_bulk_ids' ) );
		add_action( 'wp_ajax_mediarefinspector_bulk_scan_item', array( $this, 'ajax_bulk_scan_item' ) );
		add_action( 'admin_post_mediarefinspector_send_support_email', array( $this, 'handle_support_email' ) );
		add_filter( 'media_row_actions', array( $this, 'add_media_row_action' ), 10, 3 );
	}

	/**
	 * Adds the plugin page under Media.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		$this->page_hook = add_media_page(
			__( 'Media Reference Inspector', 'media-reference-inspector' ),
			__( 'Media References', 'media-reference-inspector' ),
			'manage_options',
			'media-reference-inspector',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Loads assets only on the plugin screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( ! $this->page_hook || $hook_suffix !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'mediarefinspector-admin',
			plugins_url( 'assets/css/admin.css', MEDIAREFINSPECTOR_FILE ),
			array(),
			MEDIAREFINSPECTOR_VERSION
		);

		wp_enqueue_script(
			'mediarefinspector-admin',
			plugins_url( 'assets/js/admin.js', MEDIAREFINSPECTOR_FILE ),
			array(),
			MEDIAREFINSPECTOR_VERSION,
			true
		);

		wp_localize_script(
			'mediarefinspector-admin',
			'MediaRefInspectorAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mediarefinspector_bulk_scan' ),
				'strings' => array(
					'starting'         => __( 'Preparing media scan…', 'media-reference-inspector' ),
					'scanning'         => __( 'Scanning media…', 'media-reference-inspector' ),
					'complete'         => __( 'Bulk scan complete.', 'media-reference-inspector' ),
					'failed'           => __( 'The bulk scan could not be completed. Please try again.', 'media-reference-inspector' ),
					'noItems'          => __( 'No media items matched these filters.', 'media-reference-inspector' ),
					'cancelled'        => __( 'Bulk scan stopped.', 'media-reference-inspector' ),
					'referenced'       => __( 'Referenced', 'media-reference-inspector' ),
					'noReferences'     => __( 'No supported references found', 'media-reference-inspector' ),
					'error'            => __( 'Needs review', 'media-reference-inspector' ),
					'csvFilename'      => 'media-reference-inspector-report.csv',
					'inspect'          => __( 'Inspect', 'media-reference-inspector' ),
					'editMedia'        => __( 'Edit media', 'media-reference-inspector' ),
					'confirmStart'     => __( 'Start a read-only scan of the filtered media items?', 'media-reference-inspector' ),
					'noSupportedProof' => __( 'No supported references found does not prove a file is unused.', 'media-reference-inspector' ),
				),
			)
		);
	}

	/**
	 * Adds an Inspector shortcut to Media Library row actions.
	 *
	 * @param string[] $actions  Existing row actions.
	 * @param WP_Post  $post     Attachment post.
	 * @param bool     $detached Whether the media list is detached-only.
	 * @return string[]
	 */
	public function add_media_row_action( $actions, $post, $detached ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required filter signature.
		if ( ! current_user_can( 'manage_options' ) || ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return $actions;
		}

		$url = $this->get_scan_url( $post->ID, '', 1 );

		$actions['mediarefinspector'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Check references', 'media-reference-inspector' )
		);
		$cached = get_transient( 'mediarefinspector_scan_status_' . absint( $post->ID ) );
		if ( is_array( $cached ) && ! empty( $cached['status'] ) ) {
			$label = 'referenced' === $cached['status'] ? __( 'Cached: Referenced', 'media-reference-inspector' ) : __( 'Cached: Needs review', 'media-reference-inspector' );
			$actions['mediarefinspector_status'] = '<span class="mediarefinspector-row-status">' . esc_html( $label ) . '</span>';
		}

		return $actions;
	}

	/**
	 * Renders the plugin page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'media-reference-inspector' ) );
		}

		$tab = $this->get_current_tab();
		?>
		<div class="wrap mediarefinspector-admin">
			<?php $this->render_header( $tab ); ?>

			<div class="mediarefinspector-content" role="region" aria-label="<?php echo esc_attr__( 'Media Reference Inspector content', 'media-reference-inspector' ); ?>">
				<?php
				if ( 'bulk' === $tab ) {
					$this->render_bulk_tab();
				} elseif ( 'audit' === $tab ) {
					$this->render_audit_tab();
				} elseif ( 'duplicates' === $tab ) {
					$this->render_duplicates_tab();
				} elseif ( 'broken' === $tab ) {
					$this->render_broken_tab();
				} elseif ( 'help' === $tab ) {
					$this->render_help_tab();
				} else {
					$this->render_scanner_tab();
				}
				?>
			</div>
		</div>
		<?php
		$this->mark_feature_seen( $tab );
	}

	/**
	 * Renders the branded WordPress-native page header and navigation.
	 *
	 * @param string $tab Active tab.
	 * @return void
	 */
	private function render_header( $tab ) {
		$tabs = array(
			'scanner'    => __( 'Scanner', 'media-reference-inspector' ),
			'bulk'       => __( 'Bulk Scan', 'media-reference-inspector' ),
			'audit'      => __( 'Page Audit', 'media-reference-inspector' ),
			'duplicates' => __( 'Duplicates', 'media-reference-inspector' ),
			'broken'     => __( 'Broken URLs', 'media-reference-inspector' ),
			'help'       => __( 'Help', 'media-reference-inspector' ),
		);
		?>
		<header class="mediarefinspector-header">
			<div class="mediarefinspector-brand">
				<div class="mediarefinspector-brand-mark" aria-hidden="true"><span class="dashicons dashicons-search"></span></div>
				<div class="mediarefinspector-brand-copy">
					<h1><?php esc_html_e( 'Media Reference Inspector', 'media-reference-inspector' ); ?></h1>
					<p><?php esc_html_e( 'Find where WordPress media is referenced before you replace, reorganize, or remove it.', 'media-reference-inspector' ); ?></p>
					<div class="mediarefinspector-badges" aria-label="<?php echo esc_attr__( 'Plugin status', 'media-reference-inspector' ); ?>">
						<span class="mediarefinspector-badge mediarefinspector-badge-safe"><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span><?php esc_html_e( 'Read-only', 'media-reference-inspector' ); ?></span>
						<span class="mediarefinspector-badge"><?php echo esc_html( MEDIAREFINSPECTOR_VERSION ); ?></span>
						<span class="mediarefinspector-badge"><?php esc_html_e( 'No tracking', 'media-reference-inspector' ); ?></span>
					</div>
				</div>
			</div>
			<nav class="nav-tab-wrapper mediarefinspector-tabs" aria-label="<?php echo esc_attr__( 'Inspector sections', 'media-reference-inspector' ); ?>">
				<?php foreach ( $tabs as $tab_key => $label ) : ?>
					<?php
					$url = add_query_arg(
						array(
							'page' => 'media-reference-inspector',
							'tab'  => $tab_key,
						),
						admin_url( 'upload.php' )
					);
					?>
					<a class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>" <?php echo $tab === $tab_key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?><?php if ( $this->is_new_feature( $tab_key ) ) : ?><span class="mediarefinspector-new-badge"><?php esc_html_e( 'NEW', 'media-reference-inspector' ); ?></span><?php endif; ?></a>
				<?php endforeach; ?>
			</nav>
		</header>
		<?php
	}

	/**
	 * Renders the single-item scanner.
	 *
	 * @return void
	 */
	private function render_scanner_tab() {
		$search = $this->get_search_value();
		$type   = $this->get_type_filter();
		$paged  = $this->get_page_number();
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-scanner-heading">
			<div class="mediarefinspector-section-heading">
				<div>
					<h2 id="mediarefinspector-scanner-heading"><?php esc_html_e( 'Inspect one media item', 'media-reference-inspector' ); ?></h2>
					<p><?php esc_html_e( 'Search your Media Library, choose an item, and inspect supported WordPress references without changing the site.', 'media-reference-inspector' ); ?></p>
				</div>
			</div>

			<div class="mediarefinspector-coverage-strip" aria-label="<?php echo esc_attr__( 'Reference coverage', 'media-reference-inspector' ); ?>">
				<span><strong><?php esc_html_e( 'Core', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Content, blocks, featured media, menus and theme settings', 'media-reference-inspector' ); ?></span>
				<span><strong><?php esc_html_e( 'Widgets', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Core media widgets and block widgets', 'media-reference-inspector' ); ?></span>
				<span><strong><?php esc_html_e( 'WooCommerce', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Product galleries and category thumbnails', 'media-reference-inspector' ); ?></span>
				<span><strong><?php esc_html_e( 'Elementor', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Validated saved media controls', 'media-reference-inspector' ); ?></span>
				<span><strong><?php esc_html_e( 'ACF', 'media-reference-inspector' ); ?></strong><?php esc_html_e( 'Confirmed Image, File and Gallery field references', 'media-reference-inspector' ); ?></span>
			</div>

			<?php $this->render_integration_coverage(); ?>
			<?php $this->render_whats_new_panel(); ?>
			<?php $this->maybe_render_scan_results(); ?>
			<?php $this->render_filter_form( 'scanner', $search, $type ); ?>
			<?php $this->render_media_table( $search, $type, $paged ); ?>
		</section>
		<?php
	}

	/**
	 * Renders the bulk-scan workspace.
	 *
	 * @return void
	 */
	private function render_bulk_tab() {
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-bulk-heading">
			<div class="mediarefinspector-section-heading mediarefinspector-section-heading-split">
				<div>
					<h2 id="mediarefinspector-bulk-heading"><?php esc_html_e( 'Bulk reference scan', 'media-reference-inspector' ); ?></h2>
					<p><?php esc_html_e( 'Scan a filtered group of media items one at a time to avoid long-running requests. Nothing is deleted or modified.', 'media-reference-inspector' ); ?></p>
				</div>
				<div class="mediarefinspector-section-actions">
					<button type="button" class="button" id="mediarefinspector-export-csv" disabled><?php esc_html_e( 'Export CSV', 'media-reference-inspector' ); ?></button>
					<button type="button" class="button" id="mediarefinspector-export-html" disabled><?php esc_html_e( 'Printable HTML report', 'media-reference-inspector' ); ?></button>
				</div>
			</div>

			<div class="mediarefinspector-panel mediarefinspector-bulk-controls">
				<div class="mediarefinspector-field">
					<label for="mediarefinspector-bulk-search"><?php esc_html_e( 'Search media', 'media-reference-inspector' ); ?></label>
					<input type="search" id="mediarefinspector-bulk-search" placeholder="<?php echo esc_attr__( 'Title or description', 'media-reference-inspector' ); ?>" />
				</div>
				<div class="mediarefinspector-field">
					<label for="mediarefinspector-bulk-type"><?php esc_html_e( 'Media type', 'media-reference-inspector' ); ?></label>
					<select id="mediarefinspector-bulk-type">
						<?php $this->render_type_options( '' ); ?>
					</select>
				</div>
				<div class="mediarefinspector-field">
					<label for="mediarefinspector-bulk-age"><?php esc_html_e( 'Uploaded', 'media-reference-inspector' ); ?></label>
					<select id="mediarefinspector-bulk-age">
						<option value="0"><?php esc_html_e( 'Any time', 'media-reference-inspector' ); ?></option>
						<option value="30"><?php esc_html_e( 'Last 30 days', 'media-reference-inspector' ); ?></option>
						<option value="90"><?php esc_html_e( 'Last 90 days', 'media-reference-inspector' ); ?></option>
						<option value="365"><?php esc_html_e( 'Last year', 'media-reference-inspector' ); ?></option>
					</select>
				</div>
				<div class="mediarefinspector-field">
					<label for="mediarefinspector-bulk-limit"><?php esc_html_e( 'Maximum items', 'media-reference-inspector' ); ?></label>
					<select id="mediarefinspector-bulk-limit">
						<option value="25">25</option>
						<option value="50">50</option>
						<option value="100" selected>100</option>
						<option value="250">250</option>
					</select>
				</div>
				<div class="mediarefinspector-field mediarefinspector-field-grow">
					<label for="mediarefinspector-selected-ids"><?php esc_html_e( 'Specific media IDs', 'media-reference-inspector' ); ?></label>
					<input type="text" id="mediarefinspector-selected-ids" inputmode="numeric" placeholder="<?php echo esc_attr__( 'Optional: 12, 34, 56', 'media-reference-inspector' ); ?>" />
					<span class="description"><?php esc_html_e( 'Enter attachment IDs to scan only those items. Other search filters are ignored when IDs are supplied.', 'media-reference-inspector' ); ?></span>
				</div>
				<div class="mediarefinspector-field mediarefinspector-field-action">
					<button type="button" class="button button-primary" id="mediarefinspector-start-bulk"><?php esc_html_e( 'Start bulk scan', 'media-reference-inspector' ); ?></button>
					<button type="button" class="button" id="mediarefinspector-stop-bulk" hidden><?php esc_html_e( 'Stop', 'media-reference-inspector' ); ?></button>
				</div>
			</div>

			<div class="mediarefinspector-progress" id="mediarefinspector-bulk-progress" hidden>
				<div class="mediarefinspector-progress-header">
					<strong id="mediarefinspector-progress-status"><?php esc_html_e( 'Preparing media scan…', 'media-reference-inspector' ); ?></strong>
					<span id="mediarefinspector-progress-count">0 / 0</span>
				</div>
				<progress id="mediarefinspector-progress-bar" value="0" max="100">0%</progress>
			</div>

			<div class="mediarefinspector-summary-grid" id="mediarefinspector-bulk-summary" hidden aria-live="polite">
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Scanned', 'media-reference-inspector' ); ?></span><strong data-summary="scanned">0</strong></div>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></span><strong data-summary="referenced">0</strong></div>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'No supported references', 'media-reference-inspector' ); ?></span><strong data-summary="unreferenced">0</strong></div>
				<div class="mediarefinspector-summary-card"><span><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></span><strong data-summary="errors">0</strong></div>
			</div>

			<div class="mediarefinspector-bulk-filter-row" id="mediarefinspector-bulk-filter-row" hidden>
				<div><label for="mediarefinspector-result-filter"><?php esc_html_e( 'Show results', 'media-reference-inspector' ); ?></label>
				<select id="mediarefinspector-result-filter"><option value="all"><?php esc_html_e( 'All results', 'media-reference-inspector' ); ?></option><option value="referenced"><?php esc_html_e( 'Referenced', 'media-reference-inspector' ); ?></option><option value="unreferenced"><?php esc_html_e( 'Potential unused review', 'media-reference-inspector' ); ?></option><option value="error"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option></select></div>
				<div><label for="mediarefinspector-result-sort"><?php esc_html_e( 'Sort results', 'media-reference-inspector' ); ?></label>
				<select id="mediarefinspector-result-sort"><option value="scan"><?php esc_html_e( 'Scan order', 'media-reference-inspector' ); ?></option><option value="references-desc"><?php esc_html_e( 'Most references', 'media-reference-inspector' ); ?></option><option value="references-asc"><?php esc_html_e( 'Fewest references', 'media-reference-inspector' ); ?></option><option value="title"><?php esc_html_e( 'Title A–Z', 'media-reference-inspector' ); ?></option></select></div>
		
				<div><label for="mediarefinspector-source-filter"><?php esc_html_e( 'Evidence source', 'media-reference-inspector' ); ?></label><select id="mediarefinspector-source-filter"><option value="all"><?php esc_html_e( 'All sources', 'media-reference-inspector' ); ?></option><option value="core-id"><?php esc_html_e( 'Exact ID / block', 'media-reference-inspector' ); ?></option><option value="core-url"><?php esc_html_e( 'URL / content marker', 'media-reference-inspector' ); ?></option><option value="integration"><?php esc_html_e( 'Integration metadata', 'media-reference-inspector' ); ?></option><option value="widget"><?php esc_html_e( 'Widget data', 'media-reference-inspector' ); ?></option><option value="setting"><?php esc_html_e( 'Site/theme setting', 'media-reference-inspector' ); ?></option></select></div>
				<div><label for="mediarefinspector-health-filter"><?php esc_html_e( 'File health', 'media-reference-inspector' ); ?></label><select id="mediarefinspector-health-filter"><option value="all"><?php esc_html_e( 'Any health', 'media-reference-inspector' ); ?></option><option value="healthy"><?php esc_html_e( 'Healthy', 'media-reference-inspector' ); ?></option><option value="review"><?php esc_html_e( 'Needs review', 'media-reference-inspector' ); ?></option></select></div>
			</div>

			<div class="mediarefinspector-empty-state" id="mediarefinspector-bulk-empty">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'Ready for a read-only audit', 'media-reference-inspector' ); ?></h3>
				<p><?php esc_html_e( 'Choose optional filters and start a scan. Results will appear here as each media item is checked.', 'media-reference-inspector' ); ?></p>
			</div>

			<div class="mediarefinspector-table-wrap" id="mediarefinspector-bulk-table-wrap" hidden>
				<table class="widefat striped mediarefinspector-table mediarefinspector-bulk-table">
					<thead><tr><th><?php esc_html_e( 'Media', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'Type', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'Result', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'References', 'media-reference-inspector' ); ?></th><th><?php esc_html_e( 'Actions', 'media-reference-inspector' ); ?></th></tr></thead>
					<tbody id="mediarefinspector-bulk-results"></tbody>
				</table>
			</div>

			<p class="description mediarefinspector-safety-note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'A result of “No supported references found” is advisory and does not prove that a file is unused.', 'media-reference-inspector' ); ?></p>
		</section>
		<?php
	}


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
			<div class="mediarefinspector-section-heading"><div><h2 id="mediarefinspector-audit-heading"><?php esc_html_e( 'Page & post media audit', 'media-reference-inspector' ); ?></h2><p><?php esc_html_e( 'Choose a post or page to see supported media references, broken attachment IDs, and local file-health information. Nothing is modified.', 'media-reference-inspector' ); ?></p></div></div>
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
			$post_types = get_post_types( array( 'public' => true ), 'names' );
			unset( $post_types['attachment'] );
			$args = array( 'post_type' => array_values( $post_types ), 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 30, 'orderby' => 'modified', 'order' => 'DESC' );
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
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-duplicates-heading"><div class="mediarefinspector-section-heading mediarefinspector-section-heading-split"><div><h2 id="mediarefinspector-duplicates-heading"><?php esc_html_e( 'Potential duplicate media', 'media-reference-inspector' ); ?></h2><p><?php esc_html_e( 'Compare a bounded set of local media files by exact file hash. This tool reports matches only and never deletes anything.', 'media-reference-inspector' ); ?></p></div><?php $run_url = wp_nonce_url( add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'duplicates', 'run_duplicates' => '1' ), admin_url( 'upload.php' ) ), 'mediarefinspector_run_duplicates', 'mediarefinspector_duplicates_nonce' ); ?><a class="button button-primary" href="<?php echo esc_url( $run_url ); ?>"><?php esc_html_e( 'Scan recent 150 files', 'media-reference-inspector' ); ?></a></div>
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
		if ( ! in_array( $feature, array( 'bulk', 'broken' ), true ) ) {
			return false;
		}
		$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_230', true );
		$seen = is_array( $seen ) ? $seen : array();
		return empty( $seen[ $feature ] );
	}

	/**
	 * Marks the current 2.3 feature tab as seen after rendering.
	 *
	 * @param string $feature Feature key.
	 * @return void
	 */
	private function mark_feature_seen( $feature ) {
		if ( ! in_array( $feature, array( 'bulk', 'broken' ), true ) ) {
			return;
		}
		$seen = get_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_230', true );
		$seen = is_array( $seen ) ? $seen : array();
		$seen[ $feature ] = 1;
		update_user_meta( get_current_user_id(), 'mediarefinspector_seen_features_230', $seen );
	}


	/**
	 * Renders active/not-installed coverage for supported integrations.
	 *
	 * @return void
	 */
	private function render_integration_coverage() {
		if ( ! method_exists( $this->scanner, 'get_integration_coverage' ) ) {
			return;
		}
		$coverage = $this->scanner->get_integration_coverage();
		?>
		<div class="mediarefinspector-panel mediarefinspector-integration-coverage">
			<div class="mediarefinspector-section-heading"><div><h3><?php esc_html_e( 'Integration coverage', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'See which supported reference sources are available on this site. No external service is contacted.', 'media-reference-inspector' ); ?></p></div></div>
			<div class="mediarefinspector-coverage-grid">
				<?php foreach ( $coverage as $item ) : ?>
					<div class="mediarefinspector-coverage-card"><div><strong><?php echo esc_html( $item['name'] ); ?></strong><span class="mediarefinspector-health-pill <?php echo ! empty( $item['active'] ) ? 'is-healthy' : 'is-review'; ?>"><?php echo esc_html( ! empty( $item['active'] ) ? __( 'Active', 'media-reference-inspector' ) : __( 'Not installed / inactive', 'media-reference-inspector' ) ); ?></span></div><p><?php echo esc_html( $item['detail'] ); ?></p></div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a bounded read-only scan for broken local uploads URLs in content.
	 *
	 * @return void
	 */
	private function render_broken_tab() {
		$run = isset( $_GET['run_broken'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['run_broken'] ) );
		$run_url = wp_nonce_url( add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'broken', 'run_broken' => '1' ), admin_url( 'upload.php' ) ), 'mediarefinspector_run_broken', 'mediarefinspector_broken_nonce' );
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-broken-heading">
			<div class="mediarefinspector-section-heading mediarefinspector-section-heading-split"><div><h2 id="mediarefinspector-broken-heading"><?php esc_html_e( 'Broken local media URLs', 'media-reference-inspector' ); ?></h2><p><?php esc_html_e( 'Check recent supported post content for URLs inside this site’s uploads directory whose local files no longer exist. The scan is read-only and makes no external requests.', 'media-reference-inspector' ); ?></p></div><a class="button button-primary" href="<?php echo esc_url( $run_url ); ?>"><?php esc_html_e( 'Scan for broken URLs', 'media-reference-inspector' ); ?></a></div>
			<?php if ( $run ) : ?>
				<?php $nonce = isset( $_GET['mediarefinspector_broken_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_broken_nonce'] ) ) : ''; ?>
				<?php if ( ! wp_verify_nonce( $nonce, 'mediarefinspector_run_broken' ) ) : ?>
					<?php $this->render_notice( __( 'The broken URL scan request could not be verified.', 'media-reference-inspector' ), 'error' ); ?>
				<?php elseif ( ! method_exists( $this->scanner, 'find_broken_local_upload_urls' ) ) : ?>
					<?php $this->render_notice( __( 'Broken URL scanning is not available in this build.', 'media-reference-inspector' ), 'error' ); ?>
				<?php else : ?>
					<?php $items = $this->scanner->find_broken_local_upload_urls( 100 ); ?>
					<?php if ( empty( $items ) ) : ?>
						<?php $this->render_notice( __( 'No broken local uploads URLs were found in the bounded scan.', 'media-reference-inspector' ), 'success' ); ?>
					<?php else : ?>
						<div class="mediarefinspector-reference-list mediarefinspector-broken-list">
						<?php foreach ( $items as $item ) : ?>
							<article class="mediarefinspector-reference-item"><div><strong><?php echo esc_html( $item['title'] ); ?></strong><span class="mediarefinspector-status-pill"><?php esc_html_e( 'Local file missing', 'media-reference-inspector' ); ?></span><p class="mediarefinspector-evidence"><code><?php echo esc_html( $item['url'] ); ?></code></p></div><div class="mediarefinspector-reference-actions"><?php if ( ! empty( $item['edit_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'media-reference-inspector' ); ?></a><?php endif; ?><?php if ( ! empty( $item['view_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $item['view_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'media-reference-inspector' ); ?></a><?php endif; ?></div></article>
						<?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			<?php else : ?>
				<div class="mediarefinspector-empty-state"><span class="dashicons dashicons-warning" aria-hidden="true"></span><h3><?php esc_html_e( 'Ready for a local broken-link audit', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Only URLs under this site’s WordPress uploads directory are checked. Remote URLs are ignored.', 'media-reference-inspector' ); ?></p></div>
			<?php endif; ?>
		</section>
		<?php
	}


	/**
	 * Renders contextual help and plugin support.
	 *
	 * @return void
	 */
	private function render_help_tab() {
		$current_user  = wp_get_current_user();
		$support_state = isset( $_GET['support_status'] ) ? sanitize_key( wp_unslash( $_GET['support_status'] ) ) : '';
		?>
		<section class="mediarefinspector-section" aria-labelledby="mediarefinspector-help-heading">
			<div class="mediarefinspector-section-heading">
				<div>
					<h2 id="mediarefinspector-help-heading"><?php esc_html_e( 'How the inspector works', 'media-reference-inspector' ); ?></h2>
					<p><?php esc_html_e( 'The scanner checks supported WordPress and integration data locally. It does not send media or site data to an external service.', 'media-reference-inspector' ); ?></p>
				</div>
			</div>

			<?php if ( 'sent' === $support_state ) : ?>
				<div class="notice notice-success inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'Your support message was sent successfully.', 'media-reference-inspector' ); ?></p></div>
			<?php elseif ( 'invalid' === $support_state ) : ?>
				<div class="notice notice-error inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'Please enter a valid email address, subject, and message.', 'media-reference-inspector' ); ?></p></div>
			<?php elseif ( 'rate_limited' === $support_state ) : ?>
				<div class="notice notice-warning inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'Please wait a minute before sending another support message.', 'media-reference-inspector' ); ?></p></div>
			<?php elseif ( 'failed' === $support_state ) : ?>
				<div class="notice notice-error inline mediarefinspector-inline-notice"><p><?php esc_html_e( 'WordPress could not send the email. Your hosting mail configuration may need attention.', 'media-reference-inspector' ); ?></p></div>
			<?php endif; ?>

			<div class="mediarefinspector-help-grid">
				<div class="mediarefinspector-panel">
					<h3><?php esc_html_e( 'Standard WordPress checks', 'media-reference-inspector' ); ?></h3>
					<ul class="mediarefinspector-check-list">
						<li><?php esc_html_e( 'Post, page, and custom post type content and excerpts', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Generated image-size URLs and WordPress media blocks', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Featured images and navigation menu URLs', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Core media widgets and block widgets', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Site Icon, Site Logo, Custom Logo, Header Image, and Background Image', 'media-reference-inspector' ); ?></li>
					</ul>
				</div>
				<div class="mediarefinspector-panel">
					<h3><?php esc_html_e( 'Integration-aware checks', 'media-reference-inspector' ); ?></h3>
					<ul class="mediarefinspector-check-list">
						<li><?php esc_html_e( 'WooCommerce product gallery and product-category thumbnail attachment IDs', 'media-reference-inspector' ); ?></li>
						<li><?php esc_html_e( 'Elementor media-control data saved in Elementor JSON', 'media-reference-inspector' ); ?></li>
					</ul>
					<p class="description"><?php esc_html_e( 'These checks are passive: if matching plugin data is not present, no extra work is performed beyond the focused lookups.', 'media-reference-inspector' ); ?></p>
				</div>
				<div class="mediarefinspector-panel mediarefinspector-panel-warning">
					<h3><?php esc_html_e( 'Important limitation', 'media-reference-inspector' ); ?></h3>
					<p><?php esc_html_e( 'No scanner can prove that media is unused across every custom table, external service, theme, builder, shortcode, cache, or custom code path. Treat results as evidence for review, not as automatic deletion instructions.', 'media-reference-inspector' ); ?></p>
				</div>
				<div class="mediarefinspector-panel mediarefinspector-support-panel">
					<div class="mediarefinspector-support-heading">
						<div><h3><?php esc_html_e( 'Contact plugin support', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Report a bug or request a feature directly from WordPress admin.', 'media-reference-inspector' ); ?></p></div>
						<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
					</div>
					<form class="mediarefinspector-support-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="mediarefinspector_send_support_email" />
						<?php wp_nonce_field( 'mediarefinspector_send_support_email' ); ?>
						<div class="mediarefinspector-support-field"><label for="mediarefinspector-support-type"><?php esc_html_e( 'Request type', 'media-reference-inspector' ); ?></label><select id="mediarefinspector-support-type" name="support_type"><option value="bug"><?php esc_html_e( 'Bug report', 'media-reference-inspector' ); ?></option><option value="feature"><?php esc_html_e( 'Feature request', 'media-reference-inspector' ); ?></option><option value="question"><?php esc_html_e( 'General question', 'media-reference-inspector' ); ?></option></select></div>
						<div class="mediarefinspector-support-field"><label for="mediarefinspector-support-email"><?php esc_html_e( 'Your email', 'media-reference-inspector' ); ?></label><input id="mediarefinspector-support-email" name="support_email" type="email" required value="<?php echo esc_attr( $current_user->user_email ); ?>" autocomplete="email" /></div>
						<div class="mediarefinspector-support-field mediarefinspector-support-field-wide"><label for="mediarefinspector-support-subject"><?php esc_html_e( 'Subject', 'media-reference-inspector' ); ?></label><input id="mediarefinspector-support-subject" name="support_subject" type="text" maxlength="120" required placeholder="<?php echo esc_attr__( 'Briefly describe the issue or feature', 'media-reference-inspector' ); ?>" /></div>
						<div class="mediarefinspector-support-field mediarefinspector-support-field-wide"><label for="mediarefinspector-support-message"><?php esc_html_e( 'Message', 'media-reference-inspector' ); ?></label><textarea id="mediarefinspector-support-message" name="support_message" rows="7" maxlength="4000" required placeholder="<?php echo esc_attr__( 'Tell us what happened, what you expected, or what feature you would like.', 'media-reference-inspector' ); ?>"></textarea></div>
						<div class="mediarefinspector-support-actions mediarefinspector-support-field-wide"><button type="submit" class="button button-primary"><?php esc_html_e( 'Send support email', 'media-reference-inspector' ); ?></button><p class="description"><?php esc_html_e( 'Nothing is sent until you press this button. Delivery uses this WordPress site’s configured mail system.', 'media-reference-inspector' ); ?></p></div>
					</form>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Sends an explicitly submitted support email.
	 *
	 * @return void
	 */
	public function handle_support_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to send plugin support messages.', 'media-reference-inspector' ) );
		}
		check_admin_referer( 'mediarefinspector_send_support_email' );
		$type    = isset( $_POST['support_type'] ) ? sanitize_key( wp_unslash( $_POST['support_type'] ) ) : 'question';
		$email   = isset( $_POST['support_email'] ) ? sanitize_email( wp_unslash( $_POST['support_email'] ) ) : '';
		$subject = isset( $_POST['support_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['support_subject'] ) ) : '';
		$message = isset( $_POST['support_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['support_message'] ) ) : '';
		if ( ! in_array( $type, array( 'bug', 'feature', 'question' ), true ) ) { $type = 'question'; }
		if ( ! is_email( $email ) || '' === $subject || '' === $message ) { $this->redirect_support_result( 'invalid' ); }
		$user_id  = get_current_user_id();
		$rate_key = 'mediarefinspector_support_' . $user_id;
		if ( get_transient( $rate_key ) ) { $this->redirect_support_result( 'rate_limited' ); }
		$labels = array( 'bug' => __( 'Bug report', 'media-reference-inspector' ), 'feature' => __( 'Feature request', 'media-reference-inspector' ), 'question' => __( 'General question', 'media-reference-inspector' ) );
		$support_email = sanitize_email( apply_filters( 'mediarefinspector_support_email', 'rejoyanislam9009@gmail.com' ) );
		if ( ! is_email( $support_email ) ) { $this->redirect_support_result( 'failed' ); }
		$mail_subject = sprintf( '[Media Reference Inspector] %s: %s', $labels[ $type ], $subject );
		$mail_body = sprintf( "Request type: %s
Reply email: %s
Plugin version: %s
WordPress version: %s

Message:
%s", $labels[ $type ], $email, MEDIAREFINSPECTOR_VERSION, get_bloginfo( 'version' ), $message );
		$sent = wp_mail( $support_email, $mail_subject, $mail_body, array( 'Reply-To: ' . $email ) );
		if ( $sent ) { set_transient( $rate_key, 1, MINUTE_IN_SECONDS ); $this->redirect_support_result( 'sent' ); }
		$this->redirect_support_result( 'failed' );
	}

	/**
	 * Redirects back to Help with a support form status.
	 *
	 * @param string $status Support status key.
	 * @return void
	 */
	private function redirect_support_result( $status ) {
		$url = add_query_arg( array( 'page' => 'media-reference-inspector', 'tab' => 'help', 'support_status' => sanitize_key( $status ) ), admin_url( 'upload.php' ) );
		wp_safe_redirect( $url );
		exit;
	}


	/**
	 * Renders single-scan results when a nonce-protected attachment is requested.
	 *
	 * @return void
	 */
	private function maybe_render_scan_results() {
		if ( ! isset( $_GET['attachment_id'] ) ) {
			return;
		}

		$attachment_id = absint( wp_unslash( $_GET['attachment_id'] ) );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			$this->render_notice( esc_html__( 'The requested media item is invalid.', 'media-reference-inspector' ), 'error' );
			return;
		}

		$nonce = isset( $_GET['mediarefinspector_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mediarefinspector_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mediarefinspector_scan_attachment_' . $attachment_id ) ) {
			$this->render_notice( esc_html__( 'The scan request could not be verified. Please try again from this page.', 'media-reference-inspector' ), 'error' );
			return;
		}

		$usages = $this->scanner->find_usages( $attachment_id );
		$media  = $this->get_attachment_summary( $attachment_id );
		?>
		<div class="mediarefinspector-result-panel">
			<div class="mediarefinspector-result-media">
				<div class="mediarefinspector-result-preview"><?php echo wp_kses_post( $media['preview'] ); ?></div>
				<div>
					<span class="mediarefinspector-eyebrow"><?php esc_html_e( 'Scan result', 'media-reference-inspector' ); ?></span>
					<h3><?php echo esc_html( $media['title'] ); ?></h3>
					<p><code><?php echo esc_html( $media['filename'] ); ?></code></p>
					<p class="description"><?php echo esc_html( $media['meta'] ); ?></p>
				</div>
			</div>
			<div class="mediarefinspector-result-count <?php echo empty( $usages ) ? 'is-warning' : 'is-success'; ?>">
				<strong><?php echo esc_html( (string) count( $usages ) ); ?></strong>
				<span><?php echo esc_html( _n( 'reference found', 'references found', count( $usages ), 'media-reference-inspector' ) ); ?></span>
			</div>
		</div>

		<?php if ( empty( $usages ) ) : ?>
			<div class="notice notice-warning inline mediarefinspector-inline-notice"><p><strong><?php esc_html_e( 'No supported references found.', 'media-reference-inspector' ); ?></strong> <?php esc_html_e( 'This does not prove that the file is unused. Themes, page builders, custom tables, external systems, or custom code may reference media elsewhere.', 'media-reference-inspector' ); ?></p></div>
		<?php else : ?>
			<?php $this->render_grouped_usages( $usages ); ?>
		<?php endif; ?>

		<?php $this->render_file_health( $attachment_id ); ?>
		<?php $this->render_attachment_parent_note( $attachment_id ); ?>
		<?php
	}

	/**
	 * Renders grouped reference cards.
	 *
	 * @param array<int, array<string, mixed>> $usages Usage records.
	 * @return void
	 */
	private function render_grouped_usages( $usages ) {
		$groups = array();
		foreach ( $usages as $usage ) {
			$type = ! empty( $usage['type'] ) ? (string) $usage['type'] : __( 'Other reference', 'media-reference-inspector' );
			if ( ! isset( $groups[ $type ] ) ) {
				$groups[ $type ] = array();
			}
			$groups[ $type ][] = $usage;
		}
		?>
		<div class="mediarefinspector-reference-groups">
			<?php foreach ( $groups as $type => $items ) : ?>
				<section class="mediarefinspector-reference-group">
					<div class="mediarefinspector-reference-group-heading"><h3><?php echo esc_html( $type ); ?></h3><span><?php echo esc_html( (string) count( $items ) ); ?></span></div>
					<div class="mediarefinspector-reference-list">
						<?php foreach ( $items as $usage ) : ?>
							<article class="mediarefinspector-reference-item">
								<div><strong><?php echo esc_html( $usage['label'] ); ?></strong><span class="mediarefinspector-status-pill"><?php echo esc_html( $usage['status'] ); ?></span></div>
								<?php if ( ! empty( $usage['source'] ) || ! empty( $usage['confidence'] ) ) : ?>
									<p class="mediarefinspector-evidence"><strong><?php echo esc_html( isset( $usage['confidence'] ) ? $usage['confidence'] : __( 'High', 'media-reference-inspector' ) ); ?></strong><?php if ( ! empty( $usage['source'] ) ) : ?> · <?php echo esc_html( $usage['source'] ); ?><?php endif; ?><?php if ( ! empty( $usage['context'] ) && $usage['context'] !== $usage['label'] ) : ?><span><?php echo esc_html( $usage['context'] ); ?></span><?php endif; ?></p>
								<?php endif; ?>
								<div class="mediarefinspector-reference-actions">
									<?php if ( ! empty( $usage['edit_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $usage['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'media-reference-inspector' ); ?></a><?php endif; ?>
									<?php if ( ! empty( $usage['view_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $usage['view_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'media-reference-inspector' ); ?></a><?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Renders the searchable media list.
	 *
	 * @param string $search Search query.
	 * @param string $type   Media type filter.
	 * @param int    $paged  Current page number.
	 * @return void
	 */
	private function render_media_table( $search, $type, $paged ) {
		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 20,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}
		if ( '' !== $type ) {
			$query_args['post_mime_type'] = $type;
		}

		$media_query = new WP_Query( $query_args );
		?>
		<div class="mediarefinspector-media-grid">
			<?php if ( $media_query->have_posts() ) : ?>
				<?php while ( $media_query->have_posts() ) : ?>
					<?php
					$media_query->the_post();
					$attachment_id = get_the_ID();
					$media         = $this->get_attachment_summary( $attachment_id );
					$scan_url      = $this->get_scan_url( $attachment_id, $search, $paged, $type );
					?>
					<article class="mediarefinspector-media-card">
						<div class="mediarefinspector-media-preview"><?php echo wp_kses_post( $media['preview'] ); ?></div>
						<div class="mediarefinspector-media-copy">
							<h3><?php echo esc_html( $media['title'] ); ?></h3>
							<code><?php echo esc_html( $media['filename'] ); ?></code>
							<p><?php echo esc_html( $media['meta'] ); ?></p>
						</div>
						<div class="mediarefinspector-media-action"><a class="button button-primary" href="<?php echo esc_url( $scan_url ); ?>"><?php esc_html_e( 'Scan references', 'media-reference-inspector' ); ?></a></div>
					</article>
				<?php endwhile; ?>
			<?php else : ?>
				<div class="mediarefinspector-empty-state mediarefinspector-empty-state-inline"><span class="dashicons dashicons-format-image" aria-hidden="true"></span><h3><?php esc_html_e( 'No media items found', 'media-reference-inspector' ); ?></h3><p><?php esc_html_e( 'Try a different search or media type filter.', 'media-reference-inspector' ); ?></p></div>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
		$this->render_pagination( $media_query, $search, $type, $paged );
	}

	/**
	 * Renders search and type controls.
	 *
	 * @param string $tab    Current tab.
	 * @param string $search Search value.
	 * @param string $type   Type value.
	 * @return void
	 */
	private function render_filter_form( $tab, $search, $type ) {
		?>
		<form class="mediarefinspector-panel mediarefinspector-filter-form" method="get" action="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">
			<input type="hidden" name="page" value="media-reference-inspector" />
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
			<div class="mediarefinspector-field mediarefinspector-field-grow">
				<label for="mediarefinspector-search-input"><?php esc_html_e( 'Search media', 'media-reference-inspector' ); ?></label>
				<input type="search" id="mediarefinspector-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search title or description', 'media-reference-inspector' ); ?>" />
			</div>
			<div class="mediarefinspector-field">
				<label for="mediarefinspector-type-filter"><?php esc_html_e( 'Media type', 'media-reference-inspector' ); ?></label>
				<select id="mediarefinspector-type-filter" name="media_type"><?php $this->render_type_options( $type ); ?></select>
			</div>
			<div class="mediarefinspector-field mediarefinspector-field-action"><button class="button button-secondary" type="submit"><?php esc_html_e( 'Apply filters', 'media-reference-inspector' ); ?></button></div>
		</form>
		<?php
	}

	/**
	 * Renders shared media type options.
	 *
	 * @param string $selected Selected value.
	 * @return void
	 */
	private function render_type_options( $selected ) {
		$options = array(
			''            => __( 'All media', 'media-reference-inspector' ),
			'image'       => __( 'Images', 'media-reference-inspector' ),
			'video'       => __( 'Video', 'media-reference-inspector' ),
			'audio'       => __( 'Audio', 'media-reference-inspector' ),
			'application' => __( 'Documents / files', 'media-reference-inspector' ),
		);

		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
	}

	/**
	 * AJAX: returns attachment IDs matching bulk filters.
	 *
	 * @return void
	 */
	public function ajax_get_bulk_ids() {
		$this->verify_bulk_ajax_request();

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$type   = isset( $_POST['media_type'] ) ? sanitize_key( wp_unslash( $_POST['media_type'] ) ) : '';
		$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 100;
		$limit  = min( 250, max( 1, $limit ) );
		$age    = isset( $_POST['age'] ) ? absint( $_POST['age'] ) : 0;
		if ( ! in_array( $age, array( 0, 30, 90, 365 ), true ) ) {
			$age = 0;
		}

		if ( ! in_array( $type, array( '', 'image', 'video', 'audio', 'application' ), true ) ) {
			$type = '';
		}
		$selected_raw = isset( $_POST['selected_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_ids'] ) ) : '';
		if ( '' !== $selected_raw ) {
			$selected = array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[^0-9]+/', $selected_raw ) ) ) ) );
			$selected = array_slice( $selected, 0, $limit );
			$valid = array();
			foreach ( $selected as $candidate_id ) {
				if ( 'attachment' === get_post_type( $candidate_id ) ) { $valid[] = $candidate_id; }
			}
			wp_send_json_success( array( 'ids' => $valid ) );
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( '' !== $type ) {
			$args['post_mime_type'] = $type;
		}
		if ( $age ) {
			$args['date_query'] = array(
				array(
					'after'     => $age . ' days ago',
					'inclusive' => true,
				),
			);
		}

		$query = new WP_Query( $args );
		wp_send_json_success( array( 'ids' => array_map( 'absint', $query->posts ) ) );
	}

	/**
	 * AJAX: scans one attachment and returns a normalized bulk result.
	 *
	 * @return void
	 */
	public function ajax_bulk_scan_item() {
		$this->verify_bulk_ajax_request();

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid media item.', 'media-reference-inspector' ) ), 400 );
		}

		$usages = $this->scanner->find_usages( $attachment_id );
		$media  = $this->get_attachment_summary( $attachment_id );
		$types      = array();
		$sources    = array();
		$confidence = array();
		foreach ( $usages as $usage ) {
			if ( ! empty( $usage['type'] ) ) { $types[] = (string) $usage['type']; }
			if ( ! empty( $usage['source_category'] ) ) { $sources[] = sanitize_key( $usage['source_category'] ); }
			if ( ! empty( $usage['confidence'] ) ) { $confidence[] = (string) $usage['confidence']; }
		}
		$health = ( new MediaRefInspector_Audit_Service() )->get_file_health( $attachment_id );

		$edit_attachment = get_edit_post_link( $attachment_id, 'raw' );

		wp_send_json_success(
			array(
				'id'             => $attachment_id,
				'title'          => $media['title'],
				'filename'       => $media['filename'],
				'mimeType'       => $media['mime_type'],
				'url'            => $media['url'],
				'fileSize'       => $media['file_size'],
				'uploadedDate'   => $media['date'],
				'referenceCount' => count( $usages ),
				'referenceTypes'   => array_values( array_unique( $types ) ),
				'sourceCategories' => array_values( array_unique( $sources ) ),
				'confidence'       => array_values( array_unique( $confidence ) ),
				'healthStatus'     => isset( $health['status'] ) ? sanitize_key( $health['status'] ) : 'review',
				'status'           => empty( $usages ) ? 'unreferenced' : 'referenced',
				'inspectUrl'     => $this->get_scan_url( $attachment_id, '', 1 ),
				'editAttachment' => $edit_attachment ? $edit_attachment : '',
			)
		);
	}

	/**
	 * Verifies the shared bulk AJAX nonce and capability.
	 *
	 * @return void
	 */
	private function verify_bulk_ajax_request() {
		check_ajax_referer( 'mediarefinspector_bulk_scan', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to run this scan.', 'media-reference-inspector' ) ), 403 );
		}
	}

	/**
	 * Builds summary data for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, string>
	 */
	private function get_attachment_summary( $attachment_id ) {
		$title     = get_the_title( $attachment_id );
		$file      = get_attached_file( $attachment_id );
		$mime_type = (string) get_post_mime_type( $attachment_id );
		$filename  = $file ? wp_basename( $file ) : sprintf( __( 'Media item #%d', 'media-reference-inspector' ), $attachment_id );

		if ( '' === $title ) {
			$title = $filename;
		}

		$preview = 'image/svg+xml' === $mime_type ? '' : wp_get_attachment_image( $attachment_id, array( 96, 96 ), true, array( 'loading' => 'lazy' ) );
		if ( ! $preview ) {
			$extension = $file ? strtoupper( (string) pathinfo( $file, PATHINFO_EXTENSION ) ) : __( 'FILE', 'media-reference-inspector' );
			$preview   = '<span class="mediarefinspector-file-preview" aria-hidden="true"><span class="dashicons dashicons-media-default"></span><span class="mediarefinspector-file-extension">' . esc_html( $extension ) . '</span></span>';
		}

		$meta_parts = array();
		if ( $mime_type ) {
			$meta_parts[] = $mime_type;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
			$meta_parts[] = absint( $metadata['width'] ) . '×' . absint( $metadata['height'] );
		}

		$file_size = 0;
		if ( $file && file_exists( $file ) ) {
			$raw_size = filesize( $file );
			if ( false !== $raw_size ) {
				$file_size    = (int) $raw_size;
				$meta_parts[] = size_format( $file_size, 1 );
			}
		}

		$date = get_the_date( '', $attachment_id );
		if ( $date ) {
			$meta_parts[] = $date;
		}

		return array(
			'title'     => $title,
			'filename'  => $filename,
			'mime_type' => $mime_type,
			'preview'   => $preview,
			'meta'      => implode( ' · ', $meta_parts ),
			'url'       => (string) wp_get_attachment_url( $attachment_id ),
			'file_size' => $file_size,
			'date'      => (string) $date,
		);
	}

	/**
	 * Renders pagination while retaining filters.
	 *
	 * @param WP_Query $query  Media query.
	 * @param string   $search Search value.
	 * @param string   $type   Type value.
	 * @param int      $paged  Current page.
	 * @return void
	 */
	private function render_pagination( $query, $search, $type, $paged ) {
		if ( $query->max_num_pages <= 1 ) {
			return;
		}

		$base = add_query_arg(
			array(
				'page'  => 'media-reference-inspector',
				'tab'   => 'scanner',
				'paged' => 999999999,
			),
			admin_url( 'upload.php' )
		);
		if ( '' !== $search ) {
			$base = add_query_arg( 's', $search, $base );
		}
		if ( '' !== $type ) {
			$base = add_query_arg( 'media_type', $type, $base );
		}
		$base = str_replace( '999999999', '%#%', $base );

		$links = paginate_links(
			array(
				'base'      => $base,
				'current'   => $paged,
				'total'     => (int) $query->max_num_pages,
				'end_size'  => 1,
				'mid_size'  => 1,
				'prev_text' => __( 'Previous', 'media-reference-inspector' ),
				'next_text' => __( 'Next', 'media-reference-inspector' ),
			)
		);

		if ( $links ) {
			?><nav class="mediarefinspector-pagination" aria-label="<?php echo esc_attr__( 'Media pagination', 'media-reference-inspector' ); ?>"><?php echo wp_kses_post( $links ); ?></nav><?php
		}
	}

	/**
	 * Renders an attachment-parent note.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function render_attachment_parent_note( $attachment_id ) {
		$parent_id = wp_get_post_parent_id( $attachment_id );
		if ( ! $parent_id ) {
			return;
		}
		$title = get_the_title( $parent_id );
		if ( '' === $title ) {
			$title = sprintf( __( 'Post #%d', 'media-reference-inspector' ), $parent_id );
		}
		?><p class="description mediarefinspector-safety-note"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><?php echo esc_html( sprintf( __( 'Attachment relationship: uploaded to %s. This relationship alone does not prove the media item is displayed there.', 'media-reference-inspector' ), $title ) ); ?></p><?php
	}

	/**
	 * Renders a standard inline notice.
	 *
	 * @param string $message Notice text.
	 * @param string $type    Notice type.
	 * @return void
	 */
	private function render_notice( $message, $type = 'info' ) {
		if ( ! in_array( $type, array( 'info', 'warning', 'error', 'success' ), true ) ) {
			$type = 'info';
		}
		?><div class="notice notice-<?php echo esc_attr( $type ); ?> inline"><p><?php echo esc_html( $message ); ?></p></div><?php
	}

	/**
	 * Builds a nonce-protected single scan URL.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $search        Search filter.
	 * @param int    $paged         Page number.
	 * @param string $type          Type filter.
	 * @return string
	 */
	private function get_scan_url( $attachment_id, $search, $paged, $type = '' ) {
		$args = array(
			'page'          => 'media-reference-inspector',
			'tab'           => 'scanner',
			'attachment_id' => absint( $attachment_id ),
			'paged'         => max( 1, absint( $paged ) ),
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( '' !== $type ) {
			$args['media_type'] = $type;
		}
		$url = add_query_arg( $args, admin_url( 'upload.php' ) );
		return wp_nonce_url( $url, 'mediarefinspector_scan_attachment_' . absint( $attachment_id ), 'mediarefinspector_nonce' );
	}

	/**
	 * Gets the active tab.
	 *
	 * @return string
	 */
	private function get_current_tab() {
		$tab = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
		$tab = is_string( $tab ) ? sanitize_key( $tab ) : 'scanner';
		return in_array( $tab, array( 'scanner', 'bulk', 'audit', 'duplicates', 'broken', 'help' ), true ) ? $tab : 'scanner';
	}

	/**
	 * Gets the current search filter.
	 *
	 * @return string
	 */
	private function get_search_value() {
		$value = filter_input( INPUT_GET, 's', FILTER_UNSAFE_RAW );
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Gets the current media type filter.
	 *
	 * @return string
	 */
	private function get_type_filter() {
		$value = filter_input( INPUT_GET, 'media_type', FILTER_UNSAFE_RAW );
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		return in_array( $value, array( '', 'image', 'video', 'audio', 'application' ), true ) ? $value : '';
	}

	/**
	 * Gets the current page number.
	 *
	 * @return int
	 */
	private function get_page_number() {
		$value = filter_input( INPUT_GET, 'paged', FILTER_VALIDATE_INT );
		return false !== $value && null !== $value ? max( 1, absint( $value ) ) : 1;
	}
}
