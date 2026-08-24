<?php
/**
 * Read-only audit helpers for Media Reference Inspector.
 *
 * @package MediaReferenceInspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides bounded, read-only media health, post audit, and duplicate checks.
 */
class MediaRefInspector_Audit_Service {

	/**
	 * Returns file and metadata health for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public function get_file_health( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$file          = $attachment_id ? get_attached_file( $attachment_id ) : '';
		$exists        = $file && file_exists( $file );
		$metadata      = $attachment_id ? wp_get_attachment_metadata( $attachment_id ) : false;
		$mime          = $attachment_id ? (string) get_post_mime_type( $attachment_id ) : '';
		$file_size     = 0;
		$width         = 0;
		$height        = 0;

		if ( $exists ) {
			$size = filesize( $file );
			$file_size = false === $size ? 0 : (int) $size;
		}
		if ( is_array( $metadata ) ) {
			$width  = isset( $metadata['width'] ) ? absint( $metadata['width'] ) : 0;
			$height = isset( $metadata['height'] ) ? absint( $metadata['height'] ) : 0;
		}

		$original_exists = true;
		if ( wp_attachment_is_image( $attachment_id ) && function_exists( 'wp_get_original_image_path' ) ) {
			$original = wp_get_original_image_path( $attachment_id );
			if ( $original ) {
				$original_exists = file_exists( $original );
			}
		}

		$metadata_ok = ! wp_attachment_is_image( $attachment_id ) || ( is_array( $metadata ) && $width > 0 && $height > 0 );
		$status      = $exists && $original_exists && $metadata_ok ? 'healthy' : 'review';

		return array(
			'status'          => $status,
			'file_exists'     => (bool) $exists,
			'original_exists' => (bool) $original_exists,
			'metadata_ok'     => (bool) $metadata_ok,
			'file_size'       => $file_size,
			'width'           => $width,
			'height'          => $height,
			'mime_type'       => $mime,
			'filename'        => $file ? wp_basename( $file ) : '',
		);
	}

	/**
	 * Audits media IDs referenced by one post/page and reports broken IDs.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function audit_post( $post_id ) {
		$post_id = absint( $post_id );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || 'attachment' === $post->post_type || 'revision' === $post->post_type ) {
			return array( 'media' => array(), 'broken' => array() );
		}

		$references = array();
		$this->collect_block_ids( parse_blocks( (string) $post->post_content ), $references );

		if ( preg_match_all( '/wp-image-(\d+)/', (string) $post->post_content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$this->add_reference( $references, absint( $id ), __( 'Content image class', 'media-reference-inspector' ) );
			}
		}

		$featured = get_post_thumbnail_id( $post_id );
		if ( $featured ) {
			$this->add_reference( $references, $featured, __( 'Featured image', 'media-reference-inspector' ) );
		}

		$media  = array();
		$broken = array();
		foreach ( $references as $attachment_id => $sources ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
				$broken[] = array(
					'id'      => absint( $attachment_id ),
					'sources' => array_values( array_unique( $sources ) ),
				);
				continue;
			}
			$media[] = array(
				'id'      => absint( $attachment_id ),
				'title'   => get_the_title( $attachment_id ),
				'url'     => (string) wp_get_attachment_url( $attachment_id ),
				'sources' => array_values( array_unique( $sources ) ),
				'health'  => $this->get_file_health( $attachment_id ),
			);
		}

		return array( 'media' => $media, 'broken' => $broken );
	}

	/**
	 * Finds exact local-file duplicates using a bounded hash scan.
	 *
	 * @param int $limit Maximum attachments to inspect.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_exact_duplicates( $limit = 150 ) {
		$limit = min( 250, max( 10, absint( $limit ) ) );
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

		$groups = array();
		foreach ( $query->posts as $attachment_id ) {
			$file = get_attached_file( $attachment_id );
			if ( ! $file || ! is_readable( $file ) || ! file_exists( $file ) ) {
				continue;
			}
			$size = filesize( $file );
			if ( false === $size || $size <= 0 || $size > 25 * MB_IN_BYTES ) {
				continue;
			}
			$hash = md5_file( $file );
			if ( ! $hash ) {
				continue;
			}
			$key = $size . ':' . $hash;
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array();
			}
			$groups[ $key ][] = array(
				'id'       => absint( $attachment_id ),
				'title'    => get_the_title( $attachment_id ),
				'filename' => wp_basename( $file ),
				'size'     => (int) $size,
				'edit_url' => (string) get_edit_post_link( $attachment_id, 'raw' ),
			);
		}

		$duplicates = array();
		foreach ( $groups as $items ) {
			if ( count( $items ) > 1 ) {
				$duplicates[] = array( 'items' => $items, 'size' => $items[0]['size'] );
			}
		}
		return $duplicates;
	}

	/**
	 * Adds one attachment reference source.
	 *
	 * @param array<int, array<int, string>> $references Reference map.
	 * @param int                            $id         Attachment ID.
	 * @param string                         $source     Source label.
	 * @return void
	 */
	private function add_reference( &$references, $id, $source ) {
		$id = absint( $id );
		if ( ! $id ) {
			return;
		}
		if ( ! isset( $references[ $id ] ) ) {
			$references[ $id ] = array();
		}
		$references[ $id ][] = $source;
	}

	/**
	 * Collects supported attachment IDs from parsed block trees.
	 *
	 * @param array<int, array<string, mixed>> $blocks     Parsed blocks.
	 * @param array<int, array<int, string>>   $references Reference map.
	 * @return void
	 */
	private function collect_block_ids( $blocks, &$references ) {
		foreach ( is_array( $blocks ) ? $blocks : array() as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			if ( $name ) {
				/* translators: %s: Block name. */
				$label = sprintf( __( 'Block: %s', 'media-reference-inspector' ), $name );
			} else {
				$label = __( 'Block content', 'media-reference-inspector' );
			}

			if ( isset( $attrs['id'] ) ) {
				$this->add_reference( $references, $attrs['id'], $label );
			}
			if ( isset( $attrs['mediaId'] ) ) {
				$this->add_reference( $references, $attrs['mediaId'], $label );
			}
			if ( ! empty( $attrs['ids'] ) && is_array( $attrs['ids'] ) ) {
				foreach ( $attrs['ids'] as $id ) {
					$this->add_reference( $references, $id, $label );
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->collect_block_ids( $block['innerBlocks'], $references );
			}
		}
	}
}
