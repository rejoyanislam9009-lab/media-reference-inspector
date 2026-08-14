<?php
/**
 * Read-only media reference scanner.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds references to a single attachment in standard WordPress locations.
 */
class MediaRefInspector_Scanner {

	/**
	 * Finds supported references for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_usages( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return array();
		}

		$usages = array();
		$url    = wp_get_attachment_url( $attachment_id );

		$usages = array_merge( $usages, $this->find_content_usages( $attachment_id, $url ) );
		$usages = array_merge( $usages, $this->find_featured_image_usages( $attachment_id ) );

		if ( $url ) {
			$usages = array_merge( $usages, $this->find_menu_url_usages( $url ) );
		}

		$usages = array_merge( $usages, $this->find_site_setting_usages( $attachment_id, $url ) );

		return $this->remove_duplicate_usages( $usages );
	}

	/**
	 * Finds references in post content and excerpts by attachment URL or wp-image class.
	 *
	 * @param int          $attachment_id Attachment ID.
	 * @param string|false $url           Attachment URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_content_usages( $attachment_id, $url ) {
		global $wpdb;

		$class_like = '%' . $wpdb->esc_like( 'wp-image-' . $attachment_id ) . '%';

		if ( $url ) {
			$url_like = '%' . $wpdb->esc_like( $url ) . '%';

			// Read-only lookup across core post content is the purpose of this scanner.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A fresh reference scan must query current content.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title, post_type, post_status
					FROM %i
					WHERE post_type NOT IN ( 'attachment', 'revision', 'nav_menu_item' )
					AND post_status NOT IN ( 'auto-draft', 'trash' )
					AND (
						post_content LIKE %s
						OR post_excerpt LIKE %s
						OR post_content LIKE %s
						OR post_excerpt LIKE %s
					)
					ORDER BY post_type ASC, post_title ASC
					LIMIT 200",
					$wpdb->posts,
					$url_like,
					$url_like,
					$class_like,
					$class_like
				)
			);
		} else {
			// Read-only lookup across core post content is the purpose of this scanner.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A fresh reference scan must query current content.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title, post_type, post_status
					FROM %i
					WHERE post_type NOT IN ( 'attachment', 'revision', 'nav_menu_item' )
					AND post_status NOT IN ( 'auto-draft', 'trash' )
					AND ( post_content LIKE %s OR post_excerpt LIKE %s )
					ORDER BY post_type ASC, post_title ASC
					LIMIT 200",
					$wpdb->posts,
					$class_like,
					$class_like
				)
			);
		}

		return $this->rows_to_usages( $rows, 'content', __( 'Post content', 'media-reference-inspector' ) );
	}

	/**
	 * Finds attachments used as featured images.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_featured_image_usages( $attachment_id ) {
		global $wpdb;

		// Read-only lookup for WordPress core featured-image metadata.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A fresh reference scan must query current metadata.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_title, p.post_type, p.post_status
				FROM %i p
				INNER JOIN %i pm ON p.ID = pm.post_id
				WHERE pm.meta_key = '_thumbnail_id'
				AND pm.meta_value = %s
				AND p.post_status NOT IN ( 'auto-draft', 'trash' )
				ORDER BY p.post_type ASC, p.post_title ASC
				LIMIT 200",
				$wpdb->posts,
				$wpdb->postmeta,
				(string) $attachment_id
			)
		);

		return $this->rows_to_usages( $rows, 'featured-image', __( 'Featured image', 'media-reference-inspector' ) );
	}

	/**
	 * Finds navigation menu custom links that exactly match the attachment URL.
	 *
	 * @param string $url Attachment URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_menu_url_usages( $url ) {
		global $wpdb;

		// Read-only lookup for WordPress core navigation menu URLs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A fresh reference scan must query current menu metadata.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_title, p.post_type, p.post_status
				FROM %i p
				INNER JOIN %i pm ON p.ID = pm.post_id
				WHERE p.post_type = 'nav_menu_item'
				AND pm.meta_key = '_menu_item_url'
				AND pm.meta_value = %s
				AND p.post_status NOT IN ( 'auto-draft', 'trash' )
				ORDER BY p.post_title ASC
				LIMIT 100",
				$wpdb->posts,
				$wpdb->postmeta,
				$url
			)
		);

		$usages = array();
		foreach ( $rows as $row ) {
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
	 * Finds WordPress core site and theme settings that reference an attachment.
	 *
	 * @param int          $attachment_id Attachment ID.
	 * @param string|false $url           Attachment URL.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_site_setting_usages( $attachment_id, $url ) {
		$usages = array();

		if ( $attachment_id === absint( get_option( 'site_icon' ) ) ) {
			$usages[] = array(
				'key'      => 'site-setting:site-icon',
				'type'     => __( 'Site setting', 'media-reference-inspector' ),
				'label'    => __( 'Site Icon', 'media-reference-inspector' ),
				'status'   => __( 'Active setting', 'media-reference-inspector' ),
				'edit_url' => admin_url( 'options-general.php' ),
				'view_url' => '',
			);
		}

		if ( $attachment_id === absint( get_option( 'site_logo' ) ) ) {
			$usages[] = array(
				'key'      => 'site-setting:site-logo',
				'type'     => __( 'Site setting', 'media-reference-inspector' ),
				'label'    => __( 'Site Logo', 'media-reference-inspector' ),
				'status'   => __( 'Active setting', 'media-reference-inspector' ),
				'edit_url' => admin_url( 'options-general.php' ),
				'view_url' => '',
			);
		}

		if ( $attachment_id === absint( get_theme_mod( 'custom_logo' ) ) ) {
			$usages[] = array(
				'key'      => 'site-setting:custom-logo',
				'type'     => __( 'Theme setting', 'media-reference-inspector' ),
				'label'    => __( 'Custom Logo', 'media-reference-inspector' ),
				'status'   => __( 'Active theme setting', 'media-reference-inspector' ),
				'edit_url' => admin_url( 'customize.php' ),
				'view_url' => '',
			);
		}

		if ( $url && $url === get_theme_mod( 'header_image' ) ) {
			$usages[] = array(
				'key'      => 'theme-setting:header-image',
				'type'     => __( 'Theme setting', 'media-reference-inspector' ),
				'label'    => __( 'Header Image', 'media-reference-inspector' ),
				'status'   => __( 'Active theme setting', 'media-reference-inspector' ),
				'edit_url' => admin_url( 'customize.php' ),
				'view_url' => '',
			);
		}

		if ( $url && $url === get_theme_mod( 'background_image' ) ) {
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
	 * Converts post query rows to normalized usage records.
	 *
	 * @param array|object|null $rows       Query result rows.
	 * @param string            $key_prefix Stable internal key prefix.
	 * @param string            $type       Usage type label.
	 * @return array<int, array<string, mixed>>
	 */
	private function rows_to_usages( $rows, $key_prefix, $type ) {
		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return array();
		}

		$usages = array();

		foreach ( $rows as $row ) {
			$post_id = absint( $row->ID );
			if ( ! $post_id ) {
				continue;
			}

			$post_type_object = get_post_type_object( $row->post_type );
			$post_type_label  = $post_type_object && isset( $post_type_object->labels->singular_name )
				? $post_type_object->labels->singular_name
				: $row->post_type;

			$title = $row->post_title ? $row->post_title : sprintf(
				/* translators: %d: Post ID. */
				__( 'Untitled #%d', 'media-reference-inspector' ),
				$post_id
			);

			$status_object = get_post_status_object( $row->post_status );
			$status_label  = $status_object && isset( $status_object->label ) ? $status_object->label : $row->post_status;

			$view_url = '';
			$post     = get_post( $post_id );
			if ( $post && is_post_publicly_viewable( $post ) ) {
				$permalink = get_permalink( $post_id );
				if ( $permalink ) {
					$view_url = $permalink;
				}
			}

			$edit_url = get_edit_post_link( $post_id, 'raw' );

			$usages[] = array(
				'key'      => sanitize_key( $key_prefix ) . ':' . $post_id,
				'type'     => $type,
				'label'    => sprintf(
					/* translators: 1: Post type label, 2: Post title. */
					__( '%1$s: %2$s', 'media-reference-inspector' ),
					$post_type_label,
					$title
				),
				'status'   => $status_label,
				'edit_url' => $edit_url ? $edit_url : '',
				'view_url' => $view_url,
			);
		}

		return $usages;
	}

	/**
	 * Removes exact duplicate usage records while preserving order.
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
