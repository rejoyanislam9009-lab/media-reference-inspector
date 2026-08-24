<?php
/**
 * Enhanced read-only media reference scanner.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds generated-image URL and core block awareness without changing the base scanner.
 */
class MediaRefInspector_Enhanced_Scanner extends MediaRefInspector_Scanner {

	/**
	 * Finds base references plus version 1.1.0 enhanced references.
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

		$urls         = $this->get_attachment_urls( $attachment_id );
		$primary      = wp_get_attachment_url( $attachment_id );
		$url_variants = array_values( array_filter( $urls, function ( $url ) use ( $primary ) {
			return $url && $url !== $primary;
		} ) );

		$usages = array_merge( $usages, $this->find_block_usages( $attachment_id ) );
		$usages = array_merge( $usages, $this->find_variant_content_usages( $url_variants ) );
		$usages = array_merge( $usages, $this->find_variant_menu_usages( $url_variants ) );
		$usages = array_merge( $usages, $this->find_variant_theme_usages( $url_variants ) );

		return $this->remove_duplicate_usages( $usages );
	}

	/**
	 * Builds attachment URLs including generated image sizes and scheme variants.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, string>
	 */
	private function get_attachment_urls( $attachment_id ) {
		$urls = array();
		$url  = wp_get_attachment_url( $attachment_id );

		if ( $url ) {
			$urls[] = $url;
		}

		if ( wp_attachment_is_image( $attachment_id ) ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
					$size_url = wp_get_attachment_image_url( $attachment_id, $size_name );
					if ( $size_url ) {
						$urls[] = $size_url;
					}
				}
			}

			$full_url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( $full_url ) {
				$urls[] = $full_url;
			}

			if ( function_exists( 'wp_get_original_image_url' ) ) {
				$original_url = wp_get_original_image_url( $attachment_id );
				if ( $original_url ) {
					$urls[] = $original_url;
				}
			}
		}

		$urls        = array_values( array_unique( array_filter( $urls ) ) );
		$scheme_urls = array();
		foreach ( $urls as $candidate_url ) {
			$scheme_urls[] = $candidate_url;
			$scheme_urls[] = set_url_scheme( $candidate_url, 'http' );
			$scheme_urls[] = set_url_scheme( $candidate_url, 'https' );
		}

		return array_values( array_unique( array_filter( $scheme_urls ) ) );
	}

	/**
	 * Finds supported core blocks whose attributes contain the attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_block_usages( $attachment_id ) {
		global $wpdb;

		$id_like       = '%' . $wpdb->esc_like( '"id":' . $attachment_id ) . '%';
		$media_id_like = '%' . $wpdb->esc_like( '"mediaId":' . $attachment_id ) . '%';
		$gallery_like  = '%' . $wpdb->esc_like( '"ids":[' ) . '%' . $wpdb->esc_like( (string) $attachment_id ) . '%' . $wpdb->esc_like( ']' ) . '%';

		// Read-only candidate lookup; parse_blocks() confirms supported block attributes below.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A fresh reference scan must query current content.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type, post_status, post_content
				FROM %i
				WHERE post_type NOT IN ( 'attachment', 'revision', 'nav_menu_item' )
				AND post_status NOT IN ( 'auto-draft', 'trash' )
				AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s )
				ORDER BY post_type ASC, post_title ASC
				LIMIT 200",
				$wpdb->posts,
				$id_like,
				$media_id_like,
				$gallery_like
			)
		);

		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$block_types = $this->find_block_reference_types( $row->post_content, $attachment_id );
			foreach ( $block_types as $block_name => $block_label ) {
				$usage = $this->row_to_usage(
					$row,
					'block-' . sanitize_key( str_replace( '/', '-', $block_name ) ),
					sprintf(
						/* translators: %s: WordPress block type label. */
						__( 'Block: %s', 'media-reference-inspector' ),
						$block_label
					)
				);
				if ( $usage ) {
					$usages[] = $usage;
				}
			}
		}

		return $usages;
	}

	/**
	 * Finds generated or alternate attachment URLs in post content and excerpts.
	 *
	 * @param array<int, string> $urls URL variants excluding the current primary URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_variant_content_usages( $urls ) {
		global $wpdb;

		if ( empty( $urls ) ) {
			return array();
		}

		$urls       = array_slice( array_values( array_unique( $urls ) ), 0, 60 );
		$conditions = array();
		$query_args = array( $wpdb->posts );
		foreach ( $urls as $url ) {
			$conditions[] = '( post_content LIKE %s OR post_excerpt LIKE %s )';
			$like         = '%' . $wpdb->esc_like( $url ) . '%';
			$query_args[] = $like;
			$query_args[] = $like;
		}

		$conditions_sql = implode( ' OR ', $conditions );
		$query          = "SELECT ID, post_title, post_type, post_status
			FROM %i
			WHERE post_type NOT IN ( 'attachment', 'revision', 'nav_menu_item' )
			AND post_status NOT IN ( 'auto-draft', 'trash' )
			AND ( {$conditions_sql} )
			ORDER BY post_type ASC, post_title ASC
			LIMIT 200";

		// Dynamic fragments contain only static LIKE clauses with prepared placeholders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic fragments contain static placeholders only; all URL values are passed to prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $query, $query_args ) );

		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$usage = $this->row_to_usage( $row, 'content', __( 'Media URL variant', 'media-reference-inspector' ) );
			if ( $usage ) {
				$usages[] = $usage;
			}
		}

		return $usages;
	}

	/**
	 * Finds navigation menu custom links using a generated or alternate attachment URL.
	 *
	 * @param array<int, string> $urls URL variants excluding the current primary URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_variant_menu_usages( $urls ) {
		global $wpdb;

		if ( empty( $urls ) ) {
			return array();
		}

		$urls         = array_slice( array_values( array_unique( $urls ) ), 0, 60 );
		$placeholders = implode( ', ', array_fill( 0, count( $urls ), '%s' ) );
		$query_args   = array_merge( array( $wpdb->posts, $wpdb->postmeta ), $urls );
		$query        = "SELECT DISTINCT p.ID, p.post_title
			FROM %i p
			INNER JOIN %i pm ON p.ID = pm.post_id
			WHERE p.post_type = 'nav_menu_item'
			AND pm.meta_key = '_menu_item_url'
			AND pm.meta_value IN ( {$placeholders} )
			AND p.post_status NOT IN ( 'auto-draft', 'trash' )
			ORDER BY p.post_title ASC
			LIMIT 100";

		// Dynamic IN fragment contains only prepared placeholders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic IN fragment contains placeholders only; all URL values are passed to prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $query, $query_args ) );

		$usages = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$title = $row->post_title ? $row->post_title : sprintf(
				/* translators: %d: Navigation menu item ID. */
				__( 'Menu item #%d', 'media-reference-inspector' ),
				$row->ID
			);
			$usages[] = array(
				'key'      => 'menu-url:' . (int) $row->ID,
				'type'     => __( 'Navigation menu URL', 'media-reference-inspector' ),
				'label'    => $title,
				'status'   => __( 'Published menu item', 'media-reference-inspector' ),
				'edit_url' => admin_url( 'nav-menus.php' ),
				'view_url' => '',
			);
		}

		return $usages;
	}

	/**
	 * Finds generated or alternate URLs used in theme header/background settings.
	 *
	 * @param array<int, string> $urls URL variants excluding the current primary URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_variant_theme_usages( $urls ) {
		$usages = array();
		if ( empty( $urls ) ) {
			return $usages;
		}

		$header_image = get_theme_mod( 'header_image' );
		if ( $header_image && in_array( $header_image, $urls, true ) ) {
			$usages[] = array(
				'key'      => 'theme-setting:header-image',
				'type'     => __( 'Theme setting', 'media-reference-inspector' ),
				'label'    => __( 'Header Image', 'media-reference-inspector' ),
				'status'   => __( 'Active theme setting', 'media-reference-inspector' ),
				'edit_url' => admin_url( 'customize.php' ),
				'view_url' => '',
			);
		}

		$background_image = get_theme_mod( 'background_image' );
		if ( $background_image && in_array( $background_image, $urls, true ) ) {
			$usages[] = array(
				'key'      => 'theme-setting:background-image',
				'type'     => __( 'Theme setting', 'media-reference-inspector' ),
				'label'    => __( 'Background Image', 'media-reference-inspector' ),
				'status'   => __( 'Active theme setting', 'media-reference-inspector' ),
				'edit_url' => admin_url( 'customize.php' ),
				'view_url' => '',
			);
		}

		return $usages;
	}

	/**
	 * Finds core block types that reference an attachment ID.
	 *
	 * @param string $content       Saved post content.
	 * @param int    $attachment_id Attachment ID.
	 * @return array<string, string>
	 */
	private function find_block_reference_types( $content, $attachment_id ) {
		if ( ! is_string( $content ) || '' === trim( $content ) || ! function_exists( 'parse_blocks' ) ) {
			return array();
		}

		$found = array();
		$this->collect_block_reference_types( parse_blocks( $content ), $attachment_id, $found );
		return $found;
	}

	/**
	 * Recursively collects supported core media blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks        Parsed blocks.
	 * @param int                              $attachment_id Attachment ID.
	 * @param array<string, string>            $found         Found block labels.
	 * @return void
	 */
	private function collect_block_reference_types( $blocks, $attachment_id, &$found ) {
		foreach ( is_array( $blocks ) ? $blocks : array() as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name  = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			if ( $name && $this->block_references_attachment( $name, $attrs, $attachment_id ) ) {
				$found[ $name ] = $this->get_block_type_label( $name );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->collect_block_reference_types( $block['innerBlocks'], $attachment_id, $found );
			}
		}
	}

	/**
	 * Checks supported block attributes for an attachment ID.
	 *
	 * @param string               $block_name    Block name.
	 * @param array<string, mixed> $attrs         Block attributes.
	 * @param int                  $attachment_id Attachment ID.
	 * @return bool
	 */
	private function block_references_attachment( $block_name, $attrs, $attachment_id ) {
		$id_keys = array(
			'core/image'      => 'id',
			'core/cover'      => 'id',
			'core/media-text' => 'mediaId',
			'core/file'       => 'id',
			'core/audio'      => 'id',
			'core/video'      => 'id',
		);

		if ( isset( $id_keys[ $block_name ] ) ) {
			$key = $id_keys[ $block_name ];
			return isset( $attrs[ $key ] ) && $attachment_id === absint( $attrs[ $key ] );
		}

		if ( 'core/gallery' === $block_name && ! empty( $attrs['ids'] ) && is_array( $attrs['ids'] ) ) {
			foreach ( $attrs['ids'] as $gallery_id ) {
				if ( $attachment_id === absint( $gallery_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Gets a readable supported block label.
	 *
	 * @param string $block_name Block name.
	 * @return string
	 */
	private function get_block_type_label( $block_name ) {
		$labels = array(
			'core/image'      => __( 'Image', 'media-reference-inspector' ),
			'core/gallery'    => __( 'Gallery', 'media-reference-inspector' ),
			'core/cover'      => __( 'Cover', 'media-reference-inspector' ),
			'core/media-text' => __( 'Media & Text', 'media-reference-inspector' ),
			'core/file'       => __( 'File', 'media-reference-inspector' ),
			'core/audio'      => __( 'Audio', 'media-reference-inspector' ),
			'core/video'      => __( 'Video', 'media-reference-inspector' ),
		);

		return isset( $labels[ $block_name ] ) ? $labels[ $block_name ] : $block_name;
	}

	/**
	 * Normalizes one post row into the existing result format.
	 *
	 * @param object $row        Query row.
	 * @param string $key_prefix Stable key prefix.
	 * @param string $type       Usage type.
	 * @return array<string, mixed>|false
	 */
	private function row_to_usage( $row, $key_prefix, $type ) {
		$post_id = isset( $row->ID ) ? absint( $row->ID ) : 0;
		if ( ! $post_id ) {
			return false;
		}

		$post_type        = isset( $row->post_type ) ? $row->post_type : '';
		$post_status      = isset( $row->post_status ) ? $row->post_status : '';
		$post_type_object = get_post_type_object( $post_type );
		$post_type_label  = $post_type_object && isset( $post_type_object->labels->singular_name ) ? $post_type_object->labels->singular_name : $post_type;
		if ( ! empty( $row->post_title ) ) {
			$title = $row->post_title;
		} else {
			/* translators: %d: Post ID. */
			$title = sprintf( __( 'Untitled #%d', 'media-reference-inspector' ), $post_id );
		}
		$status_object    = get_post_status_object( $post_status );
		$status_label     = $status_object && isset( $status_object->label ) ? $status_object->label : $post_status;
		$post             = get_post( $post_id );
		$view_url         = '';

		if ( $post && is_post_publicly_viewable( $post ) ) {
			$permalink = get_permalink( $post_id );
			$view_url  = $permalink ? $permalink : '';
		}

		$edit_url = get_edit_post_link( $post_id, 'raw' );

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
	 * Removes duplicate result keys while preserving base-scanner results first.
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
