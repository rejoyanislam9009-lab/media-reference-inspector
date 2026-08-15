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
