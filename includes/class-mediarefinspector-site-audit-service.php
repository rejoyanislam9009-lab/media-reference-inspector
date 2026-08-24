<?php
/**
 * Bounded site-audit summary for Media Reference Inspector.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds an explicit, bounded and read-only site audit summary.
 */
class MediaRefInspector_Site_Audit_Service {

	/**
	 * Runs a bounded audit over recent Media Library items.
	 *
	 * @param object $scanner Scanner with find_usages().
	 * @param int    $limit   Maximum attachments.
	 * @return array<string, mixed>
	 */
	public function run( $scanner, $limit = 50 ) {
		$limit = min( 100, max( 10, absint( $limit ) ) );
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$health_service = new MediaRefInspector_Audit_Service();
		$summary = array(
			'scanned'       => 0,
			'referenced'    => 0,
			'unreferenced'  => 0,
			'health_review' => 0,
			'references'    => 0,
			'broken_urls'   => 0,
			'duplicate_groups' => 0,
			'items'         => array(),
		);

		foreach ( is_array( $query->posts ) ? $query->posts : array() as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			$usages = method_exists( $scanner, 'find_usages' ) ? $scanner->find_usages( $attachment_id ) : array();
			$health = $health_service->get_file_health( $attachment_id );
			$count  = count( is_array( $usages ) ? $usages : array() );
			$summary['scanned']++;
			$summary['references'] += $count;
			if ( $count ) {
				$summary['referenced']++;
			} else {
				$summary['unreferenced']++;
			}
			if ( empty( $health['status'] ) || 'healthy' !== $health['status'] ) {
				$summary['health_review']++;
			}
			$summary['items'][] = array(
				'id'         => $attachment_id,
				'title'      => get_the_title( $attachment_id ),
				'filename'   => wp_basename( (string) get_attached_file( $attachment_id ) ),
				'references' => $count,
				'health'     => isset( $health['status'] ) ? sanitize_key( $health['status'] ) : 'review',
				'edit_url'   => (string) get_edit_post_link( $attachment_id, 'raw' ),
			);
		}

		if ( method_exists( $scanner, 'find_broken_local_upload_urls' ) ) {
			$summary['broken_urls'] = count( $scanner->find_broken_local_upload_urls( 100 ) );
		}
		$summary['duplicate_groups'] = count( $health_service->find_exact_duplicates( min( 100, max( 50, $limit ) ) ) );
		return $summary;
	}
}
