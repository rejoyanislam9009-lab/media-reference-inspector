<?php
/**
 * Insight and audit enhancements for Media Reference Inspector.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds reference confidence/context, scan caching, integration coverage, and broken local URL checks.
 */
class MediaRefInspector_Insights_Scanner extends MediaRefInspector_Advanced_Scanner {

	/**
	 * Finds supported references and enriches them with evidence metadata.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_usages( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$usages        = parent::find_usages( $attachment_id );

		foreach ( $usages as $index => $usage ) {
			$usages[ $index ] = $this->decorate_usage( $usage );
		}

		if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) ) {
			set_transient(
				'mediarefinspector_scan_status_' . $attachment_id,
				array(
					'count'   => count( $usages ),
					'status'  => empty( $usages ) ? 'unreferenced' : 'referenced',
					'checked' => time(),
				),
				6 * HOUR_IN_SECONDS
			);
		}

		return $usages;
	}

	/**
	 * Returns supported integration availability without loading external services.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_integration_coverage() {
		return array(
			array( 'name' => 'WordPress Core', 'active' => true, 'detail' => __( 'Content, blocks, featured media, menus, settings and widgets', 'media-reference-inspector' ) ),
			array( 'name' => 'WooCommerce', 'active' => class_exists( 'WooCommerce' ) || taxonomy_exists( 'product_cat' ), 'detail' => __( 'Product galleries and category thumbnails', 'media-reference-inspector' ) ),
			array( 'name' => 'Elementor', 'active' => defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' ), 'detail' => __( 'Validated saved media-control data', 'media-reference-inspector' ) ),
			array( 'name' => 'ACF', 'active' => function_exists( 'get_field_object' ) || class_exists( 'ACF' ), 'detail' => __( 'Confirmed Image, File and Gallery fields', 'media-reference-inspector' ) ),
		);
	}

	/**
	 * Finds local uploads URLs saved in content whose files no longer exist.
	 *
	 * @param int $limit Maximum result rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_broken_local_upload_urls( $limit = 100 ) {
		global $wpdb;

		$limit   = min( 200, max( 1, absint( $limit ) ) );
		$uploads = wp_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? untrailingslashit( (string) $uploads['baseurl'] ) : '';
		$basedir = isset( $uploads['basedir'] ) ? untrailingslashit( (string) $uploads['basedir'] ) : '';
		if ( '' === $baseurl || '' === $basedir ) {
			return array();
		}

		$like = '%' . $wpdb->esc_like( $baseurl ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current content must be inspected for broken local URLs.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type, post_status, post_content, post_excerpt
				FROM %i
				WHERE post_type NOT IN ( 'attachment', 'revision', 'nav_menu_item' )
				AND post_status NOT IN ( 'auto-draft', 'trash' )
				AND ( post_content LIKE %s OR post_excerpt LIKE %s )
				ORDER BY post_modified_gmt DESC
				LIMIT 300",
				$wpdb->posts,
				$like,
				$like
			)
		);

		$results = array();
		$seen    = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$text = html_entity_decode( (string) $row->post_content . "\n" . (string) $row->post_excerpt, ENT_QUOTES, 'UTF-8' );
			if ( ! preg_match_all( '~https?://[^\\s\"\'<>]+~i', $text, $matches ) ) {
				continue;
			}
			foreach ( $matches[0] as $raw_url ) {
				$url = preg_replace( '/[?#].*$/', '', (string) $raw_url );
				if ( 0 !== strpos( $url, $baseurl . '/' ) ) {
					continue;
				}
				$relative = rawurldecode( ltrim( substr( $url, strlen( $baseurl ) ), '/' ) );
				if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
					continue;
				}
				$path = wp_normalize_path( trailingslashit( $basedir ) . $relative );
				if ( 0 !== strpos( $path, wp_normalize_path( trailingslashit( $basedir ) ) ) || file_exists( $path ) ) {
					continue;
				}
				$key = absint( $row->ID ) . '|' . $url;
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$post_id      = absint( $row->ID );
				$post         = get_post( $post_id );
				$edit_url     = get_edit_post_link( $post_id, 'raw' );
				$view_url     = $post && is_post_publicly_viewable( $post ) ? get_permalink( $post_id ) : '';
				$results[]    = array(
					'post_id'   => $post_id,
					'title'     => ! empty( $row->post_title ) ? (string) $row->post_title : sprintf( __( 'Untitled #%d', 'media-reference-inspector' ), $post_id ),
					'post_type' => (string) $row->post_type,
					'url'       => $url,
					'file'      => wp_basename( $path ),
					'edit_url'  => $edit_url ? $edit_url : '',
					'view_url'  => $view_url ? $view_url : '',
				);
				if ( count( $results ) >= $limit ) {
					return $results;
				}
			}
		}
		return $results;
	}

	/**
	 * Adds evidence metadata to a normalized usage record.
	 *
	 * @param array<string, mixed> $usage Usage record.
	 * @return array<string, mixed>
	 */
	private function decorate_usage( $usage ) {
		$key  = isset( $usage['key'] ) ? strtolower( (string) $usage['key'] ) : '';
		$type = isset( $usage['type'] ) ? strtolower( (string) $usage['type'] ) : '';

		$source     = __( 'Supported reference', 'media-reference-inspector' );
		$category   = 'other';
		$confidence = __( 'High', 'media-reference-inspector' );
		$context    = isset( $usage['label'] ) ? (string) $usage['label'] : '';

		if ( false !== strpos( $key, 'elementor' ) || false !== strpos( $key, 'acf-' ) || false !== strpos( $key, 'woocommerce' ) ) {
			$source   = __( 'Confirmed integration metadata', 'media-reference-inspector' );
			$category = 'integration';
		} elseif ( false !== strpos( $key, 'widget' ) || false !== strpos( $type, 'widget' ) ) {
			$source   = __( 'Widget attachment ID / block data', 'media-reference-inspector' );
			$category = 'widget';
		} elseif ( false !== strpos( $key, 'site-setting' ) || false !== strpos( $key, 'theme-setting' ) || false !== strpos( $type, 'setting' ) ) {
			$source   = __( 'Exact WordPress setting value', 'media-reference-inspector' );
			$category = 'setting';
		} elseif ( false !== strpos( $key, 'featured-image' ) || false !== strpos( $key, 'block-' ) || false !== strpos( $type, 'block' ) ) {
			$source   = __( 'Exact attachment ID', 'media-reference-inspector' );
			$category = 'core-id';
		} elseif ( false !== strpos( $key, 'menu-url' ) || false !== strpos( $key, 'generated' ) || false !== strpos( $key, 'content' ) || false !== strpos( $type, 'content' ) ) {
			$source   = __( 'Exact URL / WordPress media marker', 'media-reference-inspector' );
			$category = 'core-url';
		}

		$usage['confidence']      = $confidence;
		$usage['source']          = $source;
		$usage['source_category'] = $category;
		$usage['context']         = $context;
		return $usage;
	}
}
