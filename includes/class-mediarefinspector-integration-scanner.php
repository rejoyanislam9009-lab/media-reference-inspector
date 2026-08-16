<?php
/**
 * Optional integration-aware media reference scanner.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds WooCommerce and Elementor reference checks on top of the core scanner.
 */
class MediaRefInspector_Integration_Scanner extends MediaRefInspector_Enhanced_Scanner {

	/**
	 * Finds all supported references for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_usages( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$usages        = parent::find_usages( $attachment_id );

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return $usages;
		}

		$usages = array_merge( $usages, $this->find_woocommerce_gallery_usages( $attachment_id ) );
		$usages = array_merge( $usages, $this->find_elementor_usages( $attachment_id ) );
		$usages = array_merge( $usages, $this->find_core_widget_usages( $attachment_id ) );
		$usages = array_merge( $usages, $this->find_woocommerce_term_usages( $attachment_id ) );

		return $this->remove_duplicate_usages( $usages );
	}

	/**
	 * Finds WooCommerce product gallery references.
	 *
	 * WooCommerce stores gallery attachment IDs as a comma-separated list in
	 * _product_image_gallery. The query remains read-only.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_woocommerce_gallery_usages( $attachment_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reference inspection must read current metadata.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_title, p.post_type, p.post_status
				FROM %i p
				INNER JOIN %i pm ON p.ID = pm.post_id
				WHERE pm.meta_key = '_product_image_gallery'
				AND FIND_IN_SET( %d, pm.meta_value ) > 0
				AND p.post_status NOT IN ( 'auto-draft', 'trash' )
				ORDER BY p.post_title ASC
				LIMIT 200",
				$wpdb->posts,
				$wpdb->postmeta,
				$attachment_id
			)
		);

		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$usage = $this->row_to_usage( $row, 'woocommerce-gallery', __( 'WooCommerce product gallery', 'media-reference-inspector' ) );
			if ( $usage ) {
				$usages[] = $usage;
			}
		}

		return $usages;
	}

	/**
	 * Finds Elementor media-control references in saved Elementor JSON data.
	 *
	 * Candidate rows are narrowed with LIKE, then JSON is decoded and inspected
	 * recursively so a matching number elsewhere in the document is not enough.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_elementor_usages( $attachment_id ) {
		global $wpdb;

		$id_like = '%' . $wpdb->esc_like( '"id":' . $attachment_id ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reference inspection must read current builder metadata.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_type, p.post_status, pm.meta_value
				FROM %i p
				INNER JOIN %i pm ON p.ID = pm.post_id
				WHERE pm.meta_key = '_elementor_data'
				AND pm.meta_value LIKE %s
				AND p.post_status NOT IN ( 'auto-draft', 'trash' )
				ORDER BY p.post_type ASC, p.post_title ASC
				LIMIT 200",
				$wpdb->posts,
				$wpdb->postmeta,
				$id_like
			)
		);

		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$data = json_decode( (string) $row->meta_value, true );
			if ( ! is_array( $data ) || ! $this->elementor_tree_references_attachment( $data, $attachment_id ) ) {
				continue;
			}

			$usage = $this->row_to_usage( $row, 'elementor-media', __( 'Elementor media', 'media-reference-inspector' ) );
			if ( $usage ) {
				$usages[] = $usage;
			}
		}

		return $usages;
	}

	/**
	 * Recursively checks Elementor data for a media-control shaped object.
	 *
	 * @param mixed $value         Current JSON value.
	 * @param int   $attachment_id Attachment ID.
	 * @return bool
	 */
	private function elementor_tree_references_attachment( $value, $attachment_id ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		if (
			isset( $value['id'] ) &&
			$attachment_id === absint( $value['id'] ) &&
			( isset( $value['url'] ) || isset( $value['source'] ) )
		) {
			return true;
		}

		foreach ( $value as $child ) {
			if ( is_array( $child ) && $this->elementor_tree_references_attachment( $child, $attachment_id ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Finds references stored by WordPress core media widgets and block widgets.
	 *
	 * Core media widgets store attachment IDs in option arrays. The block widget
	 * stores serialized block markup in the widget_block option, which is parsed
	 * using WordPress block parsing before a reference is reported.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_core_widget_usages( $attachment_id ) {
		$usages       = array();
		$widget_names = array(
			'widget_media_image'   => __( 'Image widget', 'media-reference-inspector' ),
			'widget_media_gallery' => __( 'Gallery widget', 'media-reference-inspector' ),
			'widget_media_audio'   => __( 'Audio widget', 'media-reference-inspector' ),
			'widget_media_video'   => __( 'Video widget', 'media-reference-inspector' ),
		);

		foreach ( $widget_names as $option_name => $label ) {
			$instances = get_option( $option_name, array() );
			foreach ( is_array( $instances ) ? $instances : array() as $instance_number => $instance ) {
				if ( ! is_array( $instance ) || '_multiwidget' === (string) $instance_number ) {
					continue;
				}

				$matches_id = isset( $instance['attachment_id'] ) && $attachment_id === absint( $instance['attachment_id'] );
				$matches_ids = false;
				if ( isset( $instance['ids'] ) ) {
					$ids = is_array( $instance['ids'] ) ? $instance['ids'] : preg_split( '/\s*,\s*/', (string) $instance['ids'] );
					$matches_ids = in_array( $attachment_id, array_map( 'absint', is_array( $ids ) ? $ids : array() ), true );
				}

				if ( ! $matches_id && ! $matches_ids ) {
					continue;
				}

				$usages[] = array(
					'key'      => sanitize_key( $option_name ) . ':' . absint( $instance_number ),
					'type'     => __( 'Core media widget', 'media-reference-inspector' ),
					'label'    => sprintf(
						/* translators: 1: Widget label, 2: Widget instance number. */
						__( '%1$s (instance %2$d)', 'media-reference-inspector' ),
						$label,
						absint( $instance_number )
					),
					'status'   => __( 'Widget configuration', 'media-reference-inspector' ),
					'edit_url' => admin_url( 'widgets.php' ),
					'view_url' => '',
				);
			}
		}

		$block_widgets = get_option( 'widget_block', array() );
		foreach ( is_array( $block_widgets ) ? $block_widgets : array() as $instance_number => $instance ) {
			if ( ! is_array( $instance ) || empty( $instance['content'] ) || '_multiwidget' === (string) $instance_number ) {
				continue;
			}

			if ( $this->block_widget_content_references_attachment( (string) $instance['content'], $attachment_id ) ) {
				$usages[] = array(
					'key'      => 'widget-block:' . absint( $instance_number ),
					'type'     => __( 'Block widget', 'media-reference-inspector' ),
					'label'    => sprintf(
						/* translators: %d: Widget instance number. */
						__( 'Block widget instance %d', 'media-reference-inspector' ),
						absint( $instance_number )
					),
					'status'   => __( 'Widget configuration', 'media-reference-inspector' ),
					'edit_url' => admin_url( 'widgets.php' ),
					'view_url' => '',
				);
			}
		}

		return $usages;
	}

	/**
	 * Checks block-widget markup for supported media block attachment IDs.
	 *
	 * @param string $content       Saved block widget content.
	 * @param int    $attachment_id Attachment ID.
	 * @return bool
	 */
	private function block_widget_content_references_attachment( $content, $attachment_id ) {
		if ( '' === trim( $content ) || ! function_exists( 'parse_blocks' ) ) {
			return false;
		}

		return $this->block_widget_tree_references_attachment( parse_blocks( $content ), $attachment_id );
	}

	/**
	 * Recursively checks parsed block-widget content.
	 *
	 * @param array<int, array<string, mixed>> $blocks        Parsed blocks.
	 * @param int                              $attachment_id Attachment ID.
	 * @return bool
	 */
	private function block_widget_tree_references_attachment( $blocks, $attachment_id ) {
		foreach ( is_array( $blocks ) ? $blocks : array() as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$id    = 0;

			switch ( $name ) {
				case 'core/image':
				case 'core/cover':
				case 'core/file':
				case 'core/audio':
				case 'core/video':
					$id = isset( $attrs['id'] ) ? absint( $attrs['id'] ) : 0;
					break;
				case 'core/media-text':
					$id = isset( $attrs['mediaId'] ) ? absint( $attrs['mediaId'] ) : 0;
					break;
				case 'core/gallery':
					$ids = isset( $attrs['ids'] ) && is_array( $attrs['ids'] ) ? array_map( 'absint', $attrs['ids'] ) : array();
					if ( in_array( $attachment_id, $ids, true ) ) {
						return true;
					}
					break;
			}

			if ( $id && $attachment_id === $id ) {
				return true;
			}

			if ( ! empty( $block['innerBlocks'] ) && $this->block_widget_tree_references_attachment( $block['innerBlocks'], $attachment_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Finds WooCommerce product-category thumbnail references.
	 *
	 * WooCommerce stores product category image IDs in term meta using the
	 * thumbnail_id key. The query is scoped to product_cat taxonomy only.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_woocommerce_term_usages( $attachment_id ) {
		global $wpdb;

		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reference inspection must read current term metadata.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT t.term_id, t.name
				FROM %i t
				INNER JOIN %i tt ON t.term_id = tt.term_id
				INNER JOIN %i tm ON t.term_id = tm.term_id
				WHERE tt.taxonomy = 'product_cat'
				AND tm.meta_key = 'thumbnail_id'
				AND tm.meta_value = %s
				ORDER BY t.name ASC
				LIMIT 200",
				$wpdb->terms,
				$wpdb->term_taxonomy,
				$wpdb->termmeta,
				(string) $attachment_id
			)
		);

		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$term_id = isset( $row->term_id ) ? absint( $row->term_id ) : 0;
			if ( ! $term_id ) {
				continue;
			}
			$edit_url = get_edit_term_link( $term_id, 'product_cat', 'product' );
			$usages[] = array(
				'key'      => 'woocommerce-product-category:' . $term_id,
				'type'     => __( 'WooCommerce product category', 'media-reference-inspector' ),
				'label'    => sprintf(
					/* translators: 1: Product category name, 2: Term ID. */
					__( 'Product category: %1$s (ID %2$d)', 'media-reference-inspector' ),
					isset( $row->name ) ? (string) $row->name : '',
					$term_id
				),
				'status'   => __( 'Category thumbnail', 'media-reference-inspector' ),
				'edit_url' => is_wp_error( $edit_url ) ? '' : (string) $edit_url,
				'view_url' => '',
			);
		}

		return $usages;
	}

	/**
	 * Normalizes a post row into the common usage format.
	 *
	 * @param object $row        Query row.
	 * @param string $key_prefix Stable key prefix.
	 * @param string $type       Reference type label.
	 * @return array<string, mixed>|false
	 */
	private function row_to_usage( $row, $key_prefix, $type ) {
		$post_id = isset( $row->ID ) ? absint( $row->ID ) : 0;
		if ( ! $post_id ) {
			return false;
		}

		$post_type        = isset( $row->post_type ) ? (string) $row->post_type : '';
		$post_status      = isset( $row->post_status ) ? (string) $row->post_status : '';
		$post_type_object = get_post_type_object( $post_type );
		$post_type_label  = $post_type_object && isset( $post_type_object->labels->singular_name ) ? $post_type_object->labels->singular_name : $post_type;
		$title            = ! empty( $row->post_title ) ? (string) $row->post_title : sprintf( __( 'Untitled #%d', 'media-reference-inspector' ), $post_id );
		$status_object    = get_post_status_object( $post_status );
		$status_label     = $status_object && isset( $status_object->label ) ? $status_object->label : $post_status;
		$edit_url         = get_edit_post_link( $post_id, 'raw' );
		$view_url         = '';
		$post             = get_post( $post_id );

		if ( $post && is_post_publicly_viewable( $post ) ) {
			$permalink = get_permalink( $post_id );
			$view_url  = $permalink ? $permalink : '';
		}

		return array(
			'key'      => sanitize_key( $key_prefix ) . ':' . $post_id,
			'type'     => $type,
			'label'    => sprintf(
				/* translators: 1: Post type label, 2: Post title, 3: Post ID. */
				__( '%1$s: %2$s (ID %3$d)', 'media-reference-inspector' ),
				$post_type_label,
				$title,
				$post_id
			),
			'status'   => $status_label,
			'edit_url' => $edit_url ? $edit_url : '',
			'view_url' => $view_url,
		);
	}

	/**
	 * Removes duplicate result keys while preserving discovery order.
	 *
	 * @param array<int, array<string, mixed>> $usages Usage records.
	 * @return array<int, array<string, mixed>>
	 */
	private function remove_duplicate_usages( $usages ) {
		$seen   = array();
		$unique = array();

		foreach ( $usages as $usage ) {
			if ( empty( $usage['key'] ) || isset( $seen[ $usage['key'] ] ) ) {
				continue;
			}
			$seen[ $usage['key'] ] = true;
			$unique[]              = $usage;
		}

		return $unique;
	}
}
