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
 * Registers and renders the Media Reference Inspector admin screen.
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
	 * Loads the small admin stylesheet only on this plugin screen.
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

		$search       = '';
		$search_input = filter_input( INPUT_GET, 's', FILTER_UNSAFE_RAW );
		if ( is_string( $search_input ) ) {
			$search = sanitize_text_field( $search_input );
		}

		$paged       = 1;
		$paged_input = filter_input( INPUT_GET, 'paged', FILTER_VALIDATE_INT );
		if ( false !== $paged_input && null !== $paged_input ) {
			$paged = max( 1, absint( $paged_input ) );
		}

		?>
		<div class="wrap mediarefinspector-admin">
			<h1><?php esc_html_e( 'Media Reference Inspector', 'media-reference-inspector' ); ?></h1>
			<p>
				<?php esc_html_e( 'Check a Media Library item for references in standard WordPress content. The plugin is read-only and never deletes or changes media.', 'media-reference-inspector' ); ?>
			</p>

			<?php $this->maybe_render_scan_results(); ?>

			<form class="mediarefinspector-search-form" method="get" action="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">
				<input type="hidden" name="page" value="media-reference-inspector" />
				<p class="search-box mediarefinspector-search-box">
					<label class="screen-reader-text" for="mediarefinspector-search-input">
						<?php esc_html_e( 'Search media', 'media-reference-inspector' ); ?>
					</label>
					<input
						type="search"
						id="mediarefinspector-search-input"
						name="s"
						value="<?php echo esc_attr( $search ); ?>"
						placeholder="<?php echo esc_attr__( 'Search media title or description', 'media-reference-inspector' ); ?>"
					/>
					<?php submit_button( esc_html__( 'Search Media', 'media-reference-inspector' ), 'secondary', '', false ); ?>
				</p>
			</form>

			<?php $this->render_media_table( $search, $paged ); ?>
		</div>
		<?php
	}

	/**
	 * Renders scan results when a valid attachment scan was requested.
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

		$nonce = '';
		if ( isset( $_GET['mediarefinspector_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['mediarefinspector_nonce'] ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'mediarefinspector_scan_attachment_' . $attachment_id ) ) {
			$this->render_notice( esc_html__( 'The scan request could not be verified. Please try again from this page.', 'media-reference-inspector' ), 'error' );
			return;
		}

		$usages = $this->scanner->find_usages( $attachment_id );
		$title  = get_the_title( $attachment_id );
		$file   = get_attached_file( $attachment_id );

		if ( '' === $title ) {
			$title = $file ? wp_basename( $file ) : sprintf(
				/* translators: %d: Attachment ID. */
				__( 'Media item #%d', 'media-reference-inspector' ),
				$attachment_id
			);
		}

		?>
		<div class="notice notice-info inline">
			<p>
				<strong><?php esc_html_e( 'Scan result:', 'media-reference-inspector' ); ?></strong>
				<?php echo esc_html( $title ); ?>
				<?php if ( $file ) : ?>
					&mdash; <code><?php echo esc_html( wp_basename( $file ) ); ?></code>
				<?php endif; ?>
			</p>
		</div>

		<?php if ( empty( $usages ) ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php esc_html_e( 'No references were found in the supported locations. This does not prove the file is unused; themes, page builders, custom tables, external systems, or custom code may reference media in other ways.', 'media-reference-inspector' ); ?>
				</p>
			</div>
		<?php else : ?>
			<h2>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: Number of detected media references. */
						_n( '%d reference found', '%d references found', count( $usages ), 'media-reference-inspector' ),
						count( $usages )
					)
				);
				?>
			</h2>
			<div class="mediarefinspector-table-wrap mediarefinspector-results-table-wrap">
			<table class="widefat striped mediarefinspector-table mediarefinspector-results-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Reference type', 'media-reference-inspector' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Location', 'media-reference-inspector' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'media-reference-inspector' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'media-reference-inspector' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $usages as $usage ) : ?>
						<?php
						$edit_label = sprintf(
							/* translators: %s: Reference location label. */
							__( 'Edit %s', 'media-reference-inspector' ),
							$usage['label']
						);
						$view_label = sprintf(
							/* translators: %s: Reference location label. */
							__( 'View %s', 'media-reference-inspector' ),
							$usage['label']
						);
						?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'Reference type', 'media-reference-inspector' ); ?>"><?php echo esc_html( $usage['type'] ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Location', 'media-reference-inspector' ); ?>"><?php echo esc_html( $usage['label'] ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Status', 'media-reference-inspector' ); ?>"><?php echo esc_html( $usage['status'] ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Actions', 'media-reference-inspector' ); ?>">
								<?php if ( ! empty( $usage['edit_url'] ) ) : ?>
									<a href="<?php echo esc_url( $usage['edit_url'] ); ?>" aria-label="<?php echo esc_attr( $edit_label ); ?>"><?php esc_html_e( 'Edit', 'media-reference-inspector' ); ?></a>
								<?php endif; ?>
								<?php if ( ! empty( $usage['edit_url'] ) && ! empty( $usage['view_url'] ) ) : ?>
									<span aria-hidden="true"> | </span>
								<?php endif; ?>
								<?php if ( ! empty( $usage['view_url'] ) ) : ?>
									<a href="<?php echo esc_url( $usage['view_url'] ); ?>" aria-label="<?php echo esc_attr( $view_label ); ?>"><?php esc_html_e( 'View', 'media-reference-inspector' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>

		<?php $this->render_attachment_parent_note( $attachment_id ); ?>
		<hr />
		<?php
	}

	/**
	 * Renders a note about the attachment parent relationship.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function render_attachment_parent_note( $attachment_id ) {
		$parent_id = wp_get_post_parent_id( $attachment_id );
		if ( ! $parent_id ) {
			return;
		}

		$parent_title = get_the_title( $parent_id );
		if ( '' === $parent_title ) {
			$parent_title = sprintf(
				/* translators: %d: Post ID. */
				__( 'Post #%d', 'media-reference-inspector' ),
				$parent_id
			);
		}

		?>
		<p>
			<strong><?php esc_html_e( 'Attachment relationship:', 'media-reference-inspector' ); ?></strong>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: Title of the post the media item was uploaded to. */
					__( 'Uploaded to %s. This relationship alone does not prove the media item is currently displayed there.', 'media-reference-inspector' ),
					$parent_title
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Renders a standard WordPress admin notice.
	 *
	 * @param string $message Message text.
	 * @param string $type    Notice type: info, warning, error, or success.
	 * @return void
	 */
	private function render_notice( $message, $type = 'info' ) {
		$allowed_types = array( 'info', 'warning', 'error', 'success' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			$type = 'info';
		}
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> inline">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders the searchable Media Library table.
	 *
	 * @param string $search Search query.
	 * @param int    $paged  Current page number.
	 * @return void
	 */
	private function render_media_table( $search, $paged ) {
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

		$media_query = new WP_Query( $query_args );
		?>
		<div class="mediarefinspector-table-wrap mediarefinspector-media-table-wrap">
		<table class="widefat striped mediarefinspector-table mediarefinspector-media-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Preview', 'media-reference-inspector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Media', 'media-reference-inspector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'media-reference-inspector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', 'media-reference-inspector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Usage check', 'media-reference-inspector' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $media_query->have_posts() ) : ?>
					<?php while ( $media_query->have_posts() ) : ?>
						<?php
						$media_query->the_post();
						$attachment_id  = get_the_ID();
						$title          = get_the_title();
						$file           = get_attached_file( $attachment_id );
						$mime_type      = (string) get_post_mime_type( $attachment_id );
						$preview        = 'image/svg+xml' === $mime_type ? '' : wp_get_attachment_image( $attachment_id, array( 60, 60 ), true );
						$scan_url       = $this->get_scan_url( $attachment_id, $search, $paged );
						$file_extension = $file ? strtoupper( (string) pathinfo( $file, PATHINFO_EXTENSION ) ) : '';

						if ( '' === $file_extension ) {
							$file_extension = __( 'FILE', 'media-reference-inspector' );
						}

						if ( '' === $title ) {
							$title = $file ? wp_basename( $file ) : sprintf(
								/* translators: %d: Attachment ID. */
								__( 'Media item #%d', 'media-reference-inspector' ),
								$attachment_id
							);
						}

						$scan_label = sprintf(
							/* translators: %s: Media title. */
							__( 'Find references for %s', 'media-reference-inspector' ),
							$title
						);
						$no_preview_label = sprintf(
							/* translators: %s: Media title. */
							__( 'No generated preview for %s.', 'media-reference-inspector' ),
							$title
						);
						?>
						<tr>
							<td class="mediarefinspector-preview-cell" data-label="<?php echo esc_attr__( 'Preview', 'media-reference-inspector' ); ?>">
								<?php
								if ( $preview ) {
									echo wp_kses_post( $preview );
								} else {
									?>
									<span class="mediarefinspector-file-preview" aria-hidden="true">
										<span class="dashicons dashicons-media-default"></span>
										<span class="mediarefinspector-file-extension"><?php echo esc_html( $file_extension ); ?></span>
									</span>
									<span class="screen-reader-text"><?php echo esc_html( $no_preview_label ); ?></span>
									<?php
								}
								?>
							</td>
							<th scope="row" data-label="<?php echo esc_attr__( 'Media', 'media-reference-inspector' ); ?>">
								<strong><?php echo esc_html( $title ); ?></strong>
								<?php if ( $file ) : ?>
									<br /><code><?php echo esc_html( wp_basename( $file ) ); ?></code>
								<?php endif; ?>
							</th>
							<td data-label="<?php echo esc_attr__( 'Type', 'media-reference-inspector' ); ?>"><?php echo esc_html( $mime_type ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Date', 'media-reference-inspector' ); ?>"><?php echo esc_html( get_the_date( '', $attachment_id ) ); ?></td>
							<td data-label="<?php echo esc_attr__( 'Usage check', 'media-reference-inspector' ); ?>"><a class="button button-secondary" href="<?php echo esc_url( $scan_url ); ?>" aria-label="<?php echo esc_attr( $scan_label ); ?>"><?php esc_html_e( 'Find References', 'media-reference-inspector' ); ?></a></td>
						</tr>
					<?php endwhile; ?>
				<?php else : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No media items found.', 'media-reference-inspector' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		</div>
		<?php
		wp_reset_postdata();

		if ( $media_query->max_num_pages > 1 ) {
			$pagination_base = add_query_arg(
				array(
					'page'  => 'media-reference-inspector',
					'paged' => 999999999,
				),
				admin_url( 'upload.php' )
			);

			if ( '' !== $search ) {
				$pagination_base = add_query_arg( 's', $search, $pagination_base );
			}

			$pagination_base = str_replace( '999999999', '%#%', $pagination_base );

			$pagination = paginate_links(
				array(
					'base'      => $pagination_base,
					'format'    => '',
					'current'   => $paged,
					'total'     => (int) $media_query->max_num_pages,
					'type'      => 'plain',
					'end_size'  => 1,
					'mid_size'  => 1,
					'prev_text' => esc_html__( 'Previous', 'media-reference-inspector' ),
					'next_text' => esc_html__( 'Next', 'media-reference-inspector' ),
				)
			);

			if ( $pagination ) {
				?>
				<nav class="tablenav mediarefinspector-pagination" aria-label="<?php echo esc_attr__( 'Media pagination', 'media-reference-inspector' ); ?>"><div class="tablenav-pages"><?php echo wp_kses_post( $pagination ); ?></div></nav>
				<?php
			}
		}
	}

	/**
	 * Builds a nonce-protected read-only scan URL.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $search        Current search query.
	 * @param int    $paged         Current page number.
	 * @return string
	 */
	private function get_scan_url( $attachment_id, $search, $paged ) {
		$args = array(
			'page'          => 'media-reference-inspector',
			'attachment_id' => $attachment_id,
			'paged'         => $paged,
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$url = add_query_arg( $args, admin_url( 'upload.php' ) );

		return wp_nonce_url(
			$url,
			'mediarefinspector_scan_attachment_' . $attachment_id,
			'mediarefinspector_nonce'
		);
	}
}
