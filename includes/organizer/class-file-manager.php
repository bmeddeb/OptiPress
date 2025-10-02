<?php
/**
 * File Manager for OptiPress Library Organizer
 *
 * Handles CRUD operations for file variants.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer_File_Manager
 *
 * Manages file variants (optipress_file posts) for library items.
 */
class OptiPress_Organizer_File_Manager {

	/**
	 * File system manager instance.
	 *
	 * @var OptiPress_Organizer_File_System
	 */
	private $file_system;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->file_system = new OptiPress_Organizer_File_System();
	}

	/**
	 * Add a file to an item.
	 *
	 * @param int    $item_id Item post ID.
	 * @param string $file_path File path on disk.
	 * @param string $variant_type Variant type (original, preview, thumbnail, etc.).
	 * @param array  $metadata File metadata.
	 * @return int|WP_Error File post ID on success, WP_Error on failure.
	 */
	public function add_file( $item_id, $file_path, $variant_type, $metadata = array() ) {
		// Validate inputs
		if ( ! $item_id ) {
			return new WP_Error( 'missing_item_id', __( 'Item ID is required.', 'optipress' ) );
		}

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return new WP_Error( 'invalid_file_path', __( 'File path is invalid or file does not exist.', 'optipress' ) );
		}

		if ( empty( $variant_type ) ) {
			return new WP_Error( 'missing_variant_type', __( 'Variant type is required.', 'optipress' ) );
		}

		// Verify parent item exists
		$parent_item = get_post( $item_id );
		if ( ! $parent_item || $parent_item->post_type !== 'optipress_item' ) {
			return new WP_Error( 'invalid_parent', __( 'Parent item not found or invalid.', 'optipress' ) );
		}

		// Organize file in file system
		$organized_path = $this->file_system->organize_file( $file_path, $item_id, $variant_type );
		if ( ! $organized_path ) {
			return new WP_Error( 'file_organization_failed', __( 'Failed to organize file.', 'optipress' ) );
		}

		// Get file info
		$filename = basename( $organized_path );
		$file_size = filesize( $organized_path );
		$mime_type = wp_check_filetype( $organized_path );

		// Prepare post data
		$post_data = array(
			'post_type'    => 'optipress_file',
			'post_title'   => $filename,
			'post_content' => isset( $metadata['description'] ) ? wp_kses_post( $metadata['description'] ) : '',
			'post_status'  => 'inherit',
			'post_parent'  => $item_id,
			'post_author'  => $parent_item->post_author,
			'post_mime_type' => $mime_type['type'] ? $mime_type['type'] : 'application/octet-stream',
		);

		// Create the file post
		$file_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $file_id ) ) {
			// Clean up organized file on failure
			unlink( $organized_path );
			return $file_id;
		}

		// Store file metadata
		update_post_meta( $file_id, '_optipress_file_path', str_replace( ABSPATH, '', $organized_path ) );
		update_post_meta( $file_id, '_optipress_file_size', $file_size );
		update_post_meta( $file_id, '_optipress_file_format', $mime_type['ext'] ? $mime_type['ext'] : 'unknown' );
		update_post_meta( $file_id, '_optipress_variant_type', sanitize_key( $variant_type ) );

		// Store dimensions if provided
		if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
			update_post_meta( $file_id, '_optipress_dimensions', absint( $metadata['width'] ) . 'x' . absint( $metadata['height'] ) );
		}

		// Store conversion settings if provided
		if ( ! empty( $metadata['conversion_settings'] ) ) {
			update_post_meta( $file_id, '_optipress_conversion_settings', $metadata['conversion_settings'] );
		}

		// Store EXIF data if provided
		if ( ! empty( $metadata['exif_data'] ) ) {
			update_post_meta( $file_id, '_optipress_exif_data', $metadata['exif_data'] );
		}

		// Initialize download count
		update_post_meta( $file_id, '_optipress_download_count', 0 );

		// Allow plugins to hook into file addition
		do_action( 'optipress_organizer_file_added', $file_id, $item_id, $variant_type, $metadata );

		return $file_id;
	}

	/**
	 * Get a file by ID.
	 *
	 * @param int $file_id File post ID.
	 * @return WP_Post|null File post object or null.
	 */
	public function get_file( $file_id ) {
		if ( ! $file_id ) {
			return null;
		}

		$post = get_post( $file_id );

		// Verify it's an optipress_file post type
		if ( ! $post || $post->post_type !== 'optipress_file' ) {
			return null;
		}

		return $post;
	}

	/**
	 * Get file with full details (metadata, file path, dimensions, etc.).
	 *
	 * @param int $file_id File post ID.
	 * @return array|null File data array or null on failure.
	 */
	public function get_file_details( $file_id ) {
		$file = $this->get_file( $file_id );

		if ( ! $file ) {
			return null;
		}

		// Get file metadata
		$file_path = get_post_meta( $file_id, '_optipress_file_path', true );
		$file_size = get_post_meta( $file_id, '_optipress_file_size', true );
		$file_format = get_post_meta( $file_id, '_optipress_file_format', true );
		$variant_type = get_post_meta( $file_id, '_optipress_variant_type', true );
		$dimensions = get_post_meta( $file_id, '_optipress_dimensions', true );
		$conversion_settings = get_post_meta( $file_id, '_optipress_conversion_settings', true );
		$exif_data = get_post_meta( $file_id, '_optipress_exif_data', true );
		$download_count = get_post_meta( $file_id, '_optipress_download_count', true );

		// Parse dimensions
		$width = 0;
		$height = 0;
		if ( $dimensions && strpos( $dimensions, 'x' ) !== false ) {
			list( $width, $height ) = explode( 'x', $dimensions );
		}

		// Get full file path
		$full_path = $file_path ? ABSPATH . $file_path : '';

		// Check if file exists
		$file_exists = $full_path && file_exists( $full_path );

		return array(
			'id'                  => $file->ID,
			'item_id'             => $file->post_parent,
			'title'               => $file->post_title,
			'description'         => $file->post_content,
			'mime_type'           => $file->post_mime_type,
			'file_path'           => $file_path,
			'full_path'           => $full_path,
			'file_exists'         => $file_exists,
			'file_size'           => $file_size ? intval( $file_size ) : 0,
			'file_format'         => $file_format ? $file_format : 'unknown',
			'variant_type'        => $variant_type ? $variant_type : '',
			'width'               => intval( $width ),
			'height'              => intval( $height ),
			'dimensions'          => $dimensions ? $dimensions : '',
			'conversion_settings' => $conversion_settings ? $conversion_settings : array(),
			'exif_data'           => $exif_data ? $exif_data : array(),
			'download_count'      => $download_count ? intval( $download_count ) : 0,
			'date_created'        => $file->post_date,
			'date_modified'       => $file->post_modified,
		);
	}

	/**
	 * Get all files for an item.
	 *
	 * @param int $item_id Item post ID.
	 * @return WP_Post[] Array of file post objects.
	 */
	public function get_files_by_item( $item_id ) {
		if ( ! $item_id ) {
			return array();
		}

		$args = array(
			'post_type'      => 'optipress_file',
			'post_parent'    => $item_id,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'post_status'    => 'inherit',
		);

		$query = new WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Get all files for an item with full details.
	 *
	 * @param int $item_id Item post ID.
	 * @return array Array of file data arrays.
	 */
	public function get_files_details_by_item( $item_id ) {
		$files = $this->get_files_by_item( $item_id );
		$files_data = array();

		foreach ( $files as $file ) {
			$files_data[] = $this->get_file_details( $file->ID );
		}

		return $files_data;
	}

	/**
	 * Get a specific file variant for an item.
	 *
	 * @param int    $item_id Item post ID.
	 * @param string $variant_type Variant type.
	 * @return WP_Post|null File post object or null.
	 */
	public function get_file_by_type( $item_id, $variant_type ) {
		if ( ! $item_id || empty( $variant_type ) ) {
			return null;
		}

		$args = array(
			'post_type'      => 'optipress_file',
			'post_parent'    => $item_id,
			'posts_per_page' => 1,
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'   => '_optipress_variant_type',
					'value' => sanitize_key( $variant_type ),
				),
			),
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return null;
	}

	/**
	 * Get files by variant type across all items.
	 *
	 * @param string $variant_type Variant type.
	 * @param array  $args Additional query arguments.
	 * @return WP_Post[] Array of file post objects.
	 */
	public function get_files_by_variant_type( $variant_type, $args = array() ) {
		$defaults = array(
			'post_type'      => 'optipress_file',
			'posts_per_page' => 20,
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'   => '_optipress_variant_type',
					'value' => sanitize_key( $variant_type ),
				),
			),
		);

		$query_args = wp_parse_args( $args, $defaults );
		$query = new WP_Query( $query_args );

		return $query->posts;
	}

	/**
	 * Update file metadata.
	 *
	 * @param int   $file_id File post ID.
	 * @param array $metadata Updated metadata.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_file( $file_id, $metadata ) {
		// Verify file exists
		$file = $this->get_file( $file_id );
		if ( ! $file ) {
			return new WP_Error( 'file_not_found', __( 'File not found.', 'optipress' ) );
		}

		// Prepare post data for update
		$post_data = array(
			'ID' => $file_id,
		);

		// Update title if provided
		if ( isset( $metadata['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $metadata['title'] );
		}

		// Update description if provided
		if ( isset( $metadata['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $metadata['description'] );
		}

		// Update the post if there are changes
		if ( count( $post_data ) > 1 ) {
			$result = wp_update_post( $post_data, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update dimensions if provided
		if ( isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
			update_post_meta( $file_id, '_optipress_dimensions', absint( $metadata['width'] ) . 'x' . absint( $metadata['height'] ) );
		}

		// Update variant type if provided
		if ( isset( $metadata['variant_type'] ) ) {
			update_post_meta( $file_id, '_optipress_variant_type', sanitize_key( $metadata['variant_type'] ) );
		}

		// Update conversion settings if provided
		if ( isset( $metadata['conversion_settings'] ) ) {
			update_post_meta( $file_id, '_optipress_conversion_settings', $metadata['conversion_settings'] );
		}

		// Update EXIF data if provided
		if ( isset( $metadata['exif_data'] ) ) {
			update_post_meta( $file_id, '_optipress_exif_data', $metadata['exif_data'] );
		}

		// Allow plugins to hook into file update
		do_action( 'optipress_organizer_file_updated', $file_id, $metadata );

		return true;
	}

	/**
	 * Delete a file.
	 *
	 * @param int  $file_id File post ID.
	 * @param bool $delete_physical Whether to delete the physical file.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_file( $file_id, $delete_physical = true ) {
		// Verify file exists
		$file = $this->get_file( $file_id );
		if ( ! $file ) {
			return new WP_Error( 'file_not_found', __( 'File not found.', 'optipress' ) );
		}

		// Allow plugins to hook before deletion
		do_action( 'optipress_organizer_before_delete_file', $file_id, $delete_physical );

		// Get file path before deleting post
		$file_path = get_post_meta( $file_id, '_optipress_file_path', true );
		$full_path = $file_path ? ABSPATH . $file_path : '';

		// Delete physical file if requested
		if ( $delete_physical && $full_path && file_exists( $full_path ) ) {
			if ( ! unlink( $full_path ) ) {
				return new WP_Error( 'file_delete_failed', __( 'Failed to delete physical file.', 'optipress' ) );
			}
		}

		// Delete the file post
		$result = wp_delete_post( $file_id, true ); // Force delete (skip trash)

		if ( ! $result ) {
			return new WP_Error( 'post_delete_failed', __( 'Failed to delete file post.', 'optipress' ) );
		}

		// Allow plugins to hook after deletion
		do_action( 'optipress_organizer_file_deleted', $file_id );

		return true;
	}

	/**
	 * Increment download count for a file.
	 *
	 * @param int $file_id File post ID.
	 * @return bool Success status.
	 */
	public function increment_download_count( $file_id ) {
		$current_count = get_post_meta( $file_id, '_optipress_download_count', true );
		$new_count = $current_count ? intval( $current_count ) + 1 : 1;

		return update_post_meta( $file_id, '_optipress_download_count', $new_count );
	}

	/**
	 * Get file absolute path on disk.
	 *
	 * @param int $file_id File post ID.
	 * @return string|false Full file path or false on failure.
	 */
	public function get_file_absolute_path( $file_id ) {
		$file_path = get_post_meta( $file_id, '_optipress_file_path', true );

		if ( ! $file_path ) {
			return false;
		}

		$full_path = ABSPATH . $file_path;

		if ( ! file_exists( $full_path ) ) {
			return false;
		}

		return $full_path;
	}

	/**
	 * Check if a file exists on disk.
	 *
	 * @param int $file_id File post ID.
	 * @return bool True if file exists.
	 */
	public function file_exists_on_disk( $file_id ) {
		return (bool) $this->get_file_absolute_path( $file_id );
	}

	/**
	 * Get file size in human-readable format.
	 *
	 * @param int $file_id File post ID.
	 * @return string Human-readable file size.
	 */
	public function get_file_size_human( $file_id ) {
		$file_size = get_post_meta( $file_id, '_optipress_file_size', true );

		if ( ! $file_size ) {
			return '0 B';
		}

		$size = intval( $file_size );
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		for ( $i = 0; $size >= 1024 && $i < count( $units ) - 1; $i++ ) {
			$size /= 1024;
		}

		return round( $size, 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * Generate secure download URL for a file.
	 *
	 * @param int $file_id File post ID.
	 * @param int $expiry Expiry time in seconds (default: 1 hour).
	 * @return string|WP_Error Download URL or WP_Error on failure.
	 */
	public function generate_download_url( $file_id, $expiry = 3600 ) {
		// TODO: Implement in Phase 4
		return new WP_Error( 'not_implemented', 'Method not yet implemented' );
	}
}
