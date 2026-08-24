<?php
/**
 * Extended metadata and builder-aware scanning for Media Reference Inspector.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds bounded metadata, SEO/social, and builder reference checks.
 */
class MediaRefInspector_Extended_Scanner extends MediaRefInspector_Insights_Scanner {

	/**
	 * Finds supported references and appends bounded metadata integrations.
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

		$url = (string) wp_get_attachment_url( $attachment_id );
		$usages = array_merge(
			$usages,
			$this->find_seo_social_usages( $attachment_id, $url ),
			$this->find_builder_usages( $attachment_id, $url ),
			$this->find_generic_postmeta_usages( $attachment_id, $url ),
			$this->find_generic_termmeta_usages( $attachment_id, $url ),
			$this->find_safe_option_usages( $attachment_id, $url )
		);

		return $this->dedupe_usages( $usages );
	}

	/**
	 * Adds coverage information for 2.4 scanners.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_integration_coverage() {
		$coverage = parent::get_integration_coverage();
		$theme    = wp_get_theme();
		$coverage[] = array( 'name' => 'Metadata', 'active' => true, 'detail' => __( 'Bounded media-like post, term and option metadata', 'media-reference-inspector' ) );
		$coverage[] = array( 'name' => 'Yoast SEO', 'active' => defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ), 'detail' => __( 'Known Open Graph and Twitter image metadata', 'media-reference-inspector' ) );
		$coverage[] = array( 'name' => 'Rank Math', 'active' => defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ), 'detail' => __( 'Known Facebook and Twitter image metadata', 'media-reference-inspector' ) );
		$coverage[] = array( 'name' => 'Bricks', 'active' => defined( 'BRICKS_VERSION' ) || 'bricks' === $theme->get_stylesheet() || 'bricks' === $theme->get_template(), 'detail' => __( 'Validated saved Bricks media-like values', 'media-reference-inspector' ) );
		$coverage[] = array( 'name' => 'Divi', 'active' => defined( 'ET_CORE_VERSION' ) || false !== stripos( $theme->get_template(), 'Divi' ), 'detail' => __( 'Validated Divi shortcode media attributes', 'media-reference-inspector' ) );
		$coverage[] = array( 'name' => 'Beaver Builder', 'active' => class_exists( 'FLBuilder' ) || defined( 'FL_BUILDER_VERSION' ), 'detail' => __( 'Validated Beaver Builder saved data', 'media-reference-inspector' ) );
		return $coverage;
	}

	/**
	 * Finds known SEO/social image metadata.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $url           Attachment URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_seo_social_usages( $attachment_id, $url ) {
		global $wpdb;
		$keys = array(
			'_yoast_wpseo_opengraph-image-id', '_yoast_wpseo_twitter-image-id',
			'_yoast_wpseo_opengraph-image', '_yoast_wpseo_twitter-image',
			'rank_math_facebook_image_id', 'rank_math_twitter_image_id',
			'rank_math_facebook_image', 'rank_math_twitter_image',
			'_aioseo_og_image_url', '_aioseo_twitter_image_url',
		);
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$args = $keys;
		$args[] = (string) $attachment_id;
		$args[] = '%' . $wpdb->esc_like( $url ) . '%';
		$sql = "SELECT p.ID, p.post_title, p.post_type, p.post_status, pm.meta_key, pm.meta_value
			FROM %i p INNER JOIN %i pm ON p.ID = pm.post_id
			WHERE pm.meta_key IN ( $placeholders )
			AND ( pm.meta_value = %s OR pm.meta_value LIKE %s )
			AND p.post_status NOT IN ( 'auto-draft', 'trash' )
			ORDER BY p.ID DESC LIMIT 200";
		$params = array_merge( array( $wpdb->posts, $wpdb->postmeta ), $args );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only reference audit.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! $this->value_references_media( $row->meta_value, $attachment_id, $url, (string) $row->meta_key ) ) {
				continue;
			}
			$usages[] = $this->post_usage( $row, 'seo-social:' . sanitize_key( $row->meta_key ), __( 'SEO / social image', 'media-reference-inspector' ), __( 'Confirmed SEO/social metadata', 'media-reference-inspector' ), 'integration' );
		}
		return $usages;
	}

	/**
	 * Finds references in known builder storage only when the saved value validates.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $url           Attachment URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_builder_usages( $attachment_id, $url ) {
		global $wpdb;
		$builder_keys = array(
			'_bricks_page_content_2' => 'Bricks', '_bricks_page_header_2' => 'Bricks', '_bricks_page_footer_2' => 'Bricks',
			'_fl_builder_data' => 'Beaver Builder', '_fl_builder_draft' => 'Beaver Builder',
		);
		$keys = array_keys( $builder_keys );
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$params = array_merge( array( $wpdb->posts, $wpdb->postmeta ), $keys, array( '%' . $wpdb->esc_like( (string) $attachment_id ) . '%', '%' . $wpdb->esc_like( $url ) . '%' ) );
		$sql = "SELECT p.ID, p.post_title, p.post_type, p.post_status, pm.meta_key, pm.meta_value
			FROM %i p INNER JOIN %i pm ON p.ID = pm.post_id
			WHERE pm.meta_key IN ( $placeholders )
			AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s )
			AND p.post_status NOT IN ( 'auto-draft', 'trash' )
			ORDER BY p.ID DESC LIMIT 200";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only builder audit.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! $this->value_references_media( $row->meta_value, $attachment_id, $url, (string) $row->meta_key ) ) {
				continue;
			}
			$builder = isset( $builder_keys[ $row->meta_key ] ) ? $builder_keys[ $row->meta_key ] : __( 'Page builder', 'media-reference-inspector' );
			$usages[] = $this->post_usage( $row, 'builder:' . sanitize_key( $row->meta_key ), $builder . ' ' . __( 'media', 'media-reference-inspector' ), __( 'Validated builder metadata', 'media-reference-inspector' ), 'integration' );
		}

		// Divi stores most module configuration in post_content shortcodes.
		$id_like = '%' . $wpdb->esc_like( (string) $attachment_id ) . '%';
		$url_like = '%' . $wpdb->esc_like( $url ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only builder audit.
		$divi_rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type, post_status, post_content FROM %i WHERE post_content LIKE '%%[et_pb_%%' AND ( post_content LIKE %s OR post_content LIKE %s ) AND post_status NOT IN ( 'auto-draft', 'trash' ) ORDER BY ID DESC LIMIT 200", $wpdb->posts, $id_like, $url_like ) );
		foreach ( is_array( $divi_rows ) ? $divi_rows : array() as $row ) {
			$content = (string) $row->post_content;
			$id_match = preg_match( '/(?:image_id|gallery_ids|attachment_id)=["\x27][^"\x27]*\\b' . preg_quote( (string) $attachment_id, '/' ) . '\\b[^"\x27]*["\x27]/i', $content );
			$url_match = $url && false !== strpos( html_entity_decode( $content, ENT_QUOTES, 'UTF-8' ), $url );
			if ( ! $id_match && ! $url_match ) {
				continue;
			}
			$usages[] = $this->post_usage( $row, 'builder:divi', __( 'Divi media', 'media-reference-inspector' ), __( 'Validated Divi shortcode attribute', 'media-reference-inspector' ), 'integration' );
		}
		return $usages;
	}

	/**
	 * Finds media-looking post meta references with strict key/value validation.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $url           Attachment URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_generic_postmeta_usages( $attachment_id, $url ) {
		global $wpdb;
		$id_like = '%' . $wpdb->esc_like( (string) $attachment_id ) . '%';
		$url_like = '%' . $wpdb->esc_like( $url ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only metadata audit.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_title, p.post_type, p.post_status, pm.meta_key, pm.meta_value FROM %i p INNER JOIN %i pm ON p.ID = pm.post_id WHERE pm.meta_key REGEXP '(image|media|attachment|thumbnail|logo|icon|gallery|file|photo|avatar|banner|cover)' AND CHAR_LENGTH(pm.meta_value) <= 200000 AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s ) AND p.post_status NOT IN ('auto-draft','trash') ORDER BY p.ID DESC LIMIT 250", $wpdb->posts, $wpdb->postmeta, $id_like, $url_like ) );
		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( in_array( (string) $row->meta_key, array( '_thumbnail_id', '_elementor_data', '_product_image_gallery' ), true ) ) {
				continue;
			}
			if ( ! $this->value_references_media( $row->meta_value, $attachment_id, $url, (string) $row->meta_key ) ) {
				continue;
			}
			$usages[] = $this->post_usage( $row, 'metadata:' . sanitize_key( $row->meta_key ), __( 'Media metadata', 'media-reference-inspector' ), __( 'Validated media-like post metadata', 'media-reference-inspector' ), 'metadata' );
		}
		return $usages;
	}

	/**
	 * Finds media-looking term metadata.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $url           Attachment URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_generic_termmeta_usages( $attachment_id, $url ) {
		global $wpdb;
		$id_like = '%' . $wpdb->esc_like( (string) $attachment_id ) . '%';
		$url_like = '%' . $wpdb->esc_like( $url ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only metadata audit.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, tt.taxonomy, tm.meta_key, tm.meta_value FROM %i t INNER JOIN %i tt ON t.term_id=tt.term_id INNER JOIN %i tm ON t.term_id=tm.term_id WHERE tm.meta_key REGEXP '(image|media|attachment|thumbnail|logo|icon|gallery|file|photo|avatar|banner|cover)' AND CHAR_LENGTH(tm.meta_value) <= 200000 AND ( tm.meta_value LIKE %s OR tm.meta_value LIKE %s ) ORDER BY t.term_id DESC LIMIT 200", $wpdb->terms, $wpdb->term_taxonomy, $wpdb->termmeta, $id_like, $url_like ) );
		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( 'product_cat' === $row->taxonomy && 'thumbnail_id' === $row->meta_key ) {
				continue;
			}
			if ( ! $this->value_references_media( $row->meta_value, $attachment_id, $url, (string) $row->meta_key ) ) {
				continue;
			}
			$edit = get_edit_term_link( absint( $row->term_id ), (string) $row->taxonomy );
			$usages[] = array(
				'key' => 'termmeta:' . absint( $row->term_id ) . ':' . sanitize_key( $row->meta_key ),
				'type' => __( 'Term media metadata', 'media-reference-inspector' ),
				'label' => sprintf( __( '%1$s: %2$s', 'media-reference-inspector' ), (string) $row->taxonomy, (string) $row->name ),
				'status' => __( 'Term metadata', 'media-reference-inspector' ),
				'edit_url' => $edit ? $edit : '', 'view_url' => '', 'confidence' => __( 'High', 'media-reference-inspector' ),
				'source' => __( 'Validated media-like term metadata', 'media-reference-inspector' ), 'source_category' => 'metadata', 'context' => (string) $row->meta_key,
			);
		}
		return $usages;
	}

	/**
	 * Finds selected media-looking option values without scanning arbitrary options.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $url           Attachment URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_safe_option_usages( $attachment_id, $url ) {
		global $wpdb;
		$id_like = '%' . $wpdb->esc_like( (string) $attachment_id ) . '%';
		$url_like = '%' . $wpdb->esc_like( $url ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only metadata audit.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM %i WHERE option_name REGEXP '(image|media|attachment|thumbnail|logo|icon|gallery|photo|avatar|banner|cover)' AND CHAR_LENGTH(option_value) <= 200000 AND ( option_value LIKE %s OR option_value LIKE %s ) ORDER BY option_id DESC LIMIT 150", $wpdb->options, $id_like, $url_like ) );
		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! $this->value_references_media( $row->option_value, $attachment_id, $url, (string) $row->option_name ) ) {
				continue;
			}
			$usages[] = array(
				'key' => 'option:' . sanitize_key( $row->option_name ), 'type' => __( 'Site media setting', 'media-reference-inspector' ),
				'label' => sprintf( __( 'Option: %s', 'media-reference-inspector' ), (string) $row->option_name ), 'status' => __( 'Site option', 'media-reference-inspector' ),
				'edit_url' => admin_url( 'options-general.php' ), 'view_url' => '', 'confidence' => __( 'High', 'media-reference-inspector' ),
				'source' => __( 'Validated media-like option value', 'media-reference-inspector' ), 'source_category' => 'metadata', 'context' => (string) $row->option_name,
			);
		}
		return $usages;
	}

	/**
	 * Validates scalar, serialized or JSON values using media-like key context.
	 *
	 * @param mixed  $value         Value to inspect.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $url           Attachment URL.
	 * @param string $key_hint      Current key context.
	 * @param int    $depth         Recursion depth.
	 * @return bool
	 */
	private function value_references_media( $value, $attachment_id, $url, $key_hint = '', $depth = 0 ) {
		if ( $depth > 12 ) {
			return false;
		}
		if ( is_string( $value ) ) {
			if ( $url && false !== strpos( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ), $url ) ) {
				return true;
			}
			$unserialized = maybe_unserialize( $value );
			if ( $unserialized !== $value ) {
				return $this->value_references_media( $unserialized, $attachment_id, $url, $key_hint, $depth + 1 );
			}
			$json = json_decode( $value, true );
			if ( is_array( $json ) ) {
				return $this->value_references_media( $json, $attachment_id, $url, $key_hint, $depth + 1 );
			}
			if ( $this->is_media_key( $key_hint ) ) {
				if ( ctype_digit( trim( $value ) ) && $attachment_id === absint( $value ) ) {
					return true;
				}
				$ids = preg_split( '/[^0-9]+/', $value );
				if ( in_array( $attachment_id, array_map( 'absint', is_array( $ids ) ? $ids : array() ), true ) ) {
					return true;
				}
			}
			return false;
		}
		if ( is_numeric( $value ) ) {
			return $this->is_media_key( $key_hint ) && $attachment_id === absint( $value );
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $child_key => $child ) {
			$hint = is_string( $child_key ) ? $child_key : $key_hint;
			if ( $this->value_references_media( $child, $attachment_id, $url, $hint, $depth + 1 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a key name strongly implies media data.
	 *
	 * @param string $key Key name.
	 * @return bool
	 */
	private function is_media_key( $key ) {
		return (bool) preg_match( '/(?:image|media|attachment|thumbnail|logo|icon|gallery|file|photo|avatar|banner|cover|background|video|audio)/i', (string) $key );
	}

	/**
	 * Normalizes a post-backed usage record.
	 *
	 * @param object $row      Database row.
	 * @param string $key      Usage key prefix.
	 * @param string $type     Usage type.
	 * @param string $source   Evidence source.
	 * @param string $category Evidence category.
	 * @return array<string, mixed>
	 */
	private function post_usage( $row, $key, $type, $source, $category ) {
		$post_id = absint( $row->ID );
		$post = get_post( $post_id );
		$edit = get_edit_post_link( $post_id, 'raw' );
		$view = $post && is_post_publicly_viewable( $post ) ? get_permalink( $post_id ) : '';
		return array(
			'key' => $key . ':' . $post_id, 'type' => $type,
			'label' => sprintf( __( '%1$s (ID %2$d)', 'media-reference-inspector' ), get_the_title( $post_id ) ? get_the_title( $post_id ) : __( 'Untitled', 'media-reference-inspector' ), $post_id ),
			'status' => isset( $row->post_status ) ? (string) $row->post_status : __( 'Saved data', 'media-reference-inspector' ),
			'edit_url' => $edit ? $edit : '', 'view_url' => $view ? $view : '',
			'confidence' => __( 'High', 'media-reference-inspector' ), 'source' => $source, 'source_category' => $category,
			'context' => isset( $row->meta_key ) ? (string) $row->meta_key : $type,
		);
	}

	/**
	 * Removes duplicate normalized results.
	 *
	 * @param array<int, array<string, mixed>> $usages Usages.
	 * @return array<int, array<string, mixed>>
	 */
	private function dedupe_usages( $usages ) {
		$seen = array();
		$out  = array();
		foreach ( $usages as $usage ) {
			$key = ( isset( $usage['key'] ) ? (string) $usage['key'] : '' ) . '|' . ( isset( $usage['label'] ) ? (string) $usage['label'] : '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[] = $usage;
		}
		return $out;
	}
}
