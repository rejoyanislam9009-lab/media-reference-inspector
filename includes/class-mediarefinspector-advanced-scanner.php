<?php
/**
 * Advanced optional integration scanner.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds confirmed ACF image/file/gallery references on top of existing integrations.
 */
class MediaRefInspector_Advanced_Scanner extends MediaRefInspector_Integration_Scanner {

	/**
	 * Finds all supported references.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_usages( $attachment_id ) {
		$usages = parent::find_usages( $attachment_id );
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! function_exists( 'get_field_object' ) ) {
			return $usages;
		}

		$usages = array_merge( $usages, $this->find_acf_usages( $attachment_id ) );
		return $this->remove_duplicate_usages( $usages );
	}

	/**
	 * Finds ACF image/file/gallery fields that explicitly contain the attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_acf_usages( $attachment_id ) {
		global $wpdb;

		$serialized_id = '%' . $wpdb->esc_like( 'i:' . $attachment_id . ';' ) . '%';
		$string_id     = '%' . $wpdb->esc_like( '"' . $attachment_id . '"' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reference inspection must read current ACF metadata.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_type, p.post_status, pm.meta_key, pm.meta_value
				FROM %i p
				INNER JOIN %i pm ON p.ID = pm.post_id
				WHERE pm.meta_key NOT LIKE '\\_%'
				AND p.post_status NOT IN ( 'auto-draft', 'trash' )
				AND ( pm.meta_value = %s OR pm.meta_value LIKE %s OR pm.meta_value LIKE %s )
				ORDER BY p.post_type ASC, p.post_title ASC
				LIMIT 200",
				$wpdb->posts,
				$wpdb->postmeta,
				(string) $attachment_id,
				$serialized_id,
				$string_id
			)
		);

		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$field_key = get_post_meta( $row->ID, '_' . $row->meta_key, true );
			if ( ! is_string( $field_key ) || 0 !== strpos( $field_key, 'field_' ) ) {
				continue;
			}
			$field = get_field_object( $field_key, $row->ID, false, false );
			if ( ! is_array( $field ) || empty( $field['type'] ) || ! in_array( $field['type'], array( 'image', 'file', 'gallery' ), true ) ) {
				continue;
			}

			$value = maybe_unserialize( $row->meta_value );
			if ( ! $this->acf_value_references_attachment( $value, $attachment_id ) ) {
				continue;
			}

			$usage = $this->row_to_usage(
				$row,
				'acf-' . sanitize_key( $row->meta_key ),
				sprintf(
					/* translators: %s: ACF field label/name. */
					__( 'ACF field: %s', 'media-reference-inspector' ),
					! empty( $field['label'] ) ? $field['label'] : $row->meta_key
				)
			);
			if ( $usage ) {
				$usages[] = $usage;
			}
		}
		return $usages;
	}

	/**
	 * Recursively confirms an ACF field value contains an attachment ID.
	 *
	 * @param mixed $value         Field value.
	 * @param int   $attachment_id Attachment ID.
	 * @return bool
	 */
	private function acf_value_references_attachment( $value, $attachment_id ) {
		if ( is_numeric( $value ) ) {
			return $attachment_id === absint( $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $child ) {
				if ( $this->acf_value_references_attachment( $child, $attachment_id ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
