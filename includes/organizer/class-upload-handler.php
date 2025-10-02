<?php
/**
 * Upload Handler for OptiPress Library Organizer
 *
 * Integrates with WordPress upload process to automatically create
 * library items when advanced format images are uploaded.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer_Upload_Handler
 *
 * Handles integration with WordPress upload flow and Advanced_Formats.
 */
class OptiPress_Organizer_Upload_Handler {

	/**
	 * Advanced format extensions.
	 *
	 * @var array
	 */
	private $advanced_exts = array();

	/**
	 * Item manager instance.
	 *
	 * @var OptiPress_Organizer_Item_Manager
	 */
	private $item_manager;

	/**
	 * File manager instance.
	 *
	 * @var OptiPress_Organizer_File_Manager
	 */
	private $file_manager;

	/**
	 * Metadata extractor instance.
	 *
	 * @var OptiPress_Organizer_Metadata_Extractor
	 */
	private $metadata_extractor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Set advanced format extensions (should match Advanced_Formats)
		$this->advanced_exts = array(
			// TIFF/PSD
			'tif', 'tiff', 'psd',
			// RAW formats
			'dng', 'arw', 'cr2', 'cr3', 'nef', 'orf', 'rw2', 'raf', 'erf', 'mrw', 'pef', 'sr2', 'x3f', '3fr', 'fff', 'iiq', 'nrw', 'srw', 'rwl',
			// JPEG 2000
			'jp2', 'j2k', 'jpf', 'jpx', 'jpm',
			// HEIF/HEIC
			'heic', 'heif',
		);

		// Hook into upload process AFTER Advanced_Formats (which runs at priority 10)
		// Priority 20 ensures Advanced_Formats has already processed the file
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'handle_upload' ), 20, 2 );
	}

	/**
	 * Get manager instances (lazy loading to avoid circular dependency).
	 *
	 * @return void
	 */
	private function init_managers() {
		if ( ! $this->item_manager ) {
			$organizer = optipress_organizer();
			$this->item_manager       = $organizer->get_item_manager();
			$this->file_manager       = $organizer->get_file_manager();
			$this->metadata_extractor = $organizer->metadata;
		}
	}

	/**
	 * Check if organizer is enabled.
	 *
	 * @return bool True if enabled.
	 */
	private function is_organizer_enabled() {
		$options = get_option( 'optipress_options', array() );
		return isset( $options['organizer_enabled'] ) ? (bool) $options['organizer_enabled'] : false;
	}

	/**
	 * Detect if attachment is an advanced format.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if advanced format.
	 */
	public function is_advanced_format( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return false;
		}

		// Check if this was processed by Advanced_Formats
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['original_file'] ) ) {
			// Advanced_Formats has processed this file - use the original file extension
			$original_ext = strtolower( pathinfo( $metadata['original_file'], PATHINFO_EXTENSION ) );
			return in_array( $original_ext, $this->advanced_exts, true );
		}

		// Fallback: check current file extension
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		return in_array( $ext, $this->advanced_exts, true );
	}

	/**
	 * Get the original file path for an advanced format attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false Original file path or false if not found.
	 */
	public function get_original_file( $attachment_id ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! empty( $metadata['original_file'] ) ) {
			// Advanced_Formats stores relative path
			$upload_dir = wp_upload_dir();
			return $upload_dir['basedir'] . '/' . $metadata['original_file'];
		}

		// Fallback to current attached file
		return get_attached_file( $attachment_id );
	}

	/**
	 * Get the preview file path for an advanced format attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false Preview file path or false if not found.
	 */
	public function get_preview_file( $attachment_id ) {
		// If Advanced_Formats processed this, the current attached file IS the preview
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['original_file'] ) ) {
			return get_attached_file( $attachment_id );
		}

		return false;
	}

	/**
	 * Handle upload and create library item if applicable.
	 *
	 * Hooked to wp_generate_attachment_metadata at priority 20 (after Advanced_Formats).
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Unmodified metadata.
	 */
	public function handle_upload( $metadata, $attachment_id ) {
		// Check if organizer is enabled
		if ( ! $this->is_organizer_enabled() ) {
			return $metadata;
		}

		// Check if this is an advanced format
		if ( ! $this->is_advanced_format( $attachment_id ) ) {
			return $metadata;
		}

		// Detection successful - fire action hook for logging/debugging
		do_action( 'optipress_organizer_advanced_format_detected', $attachment_id, $metadata );

		// Store detection flag in attachment meta for later use
		update_post_meta( $attachment_id, '_optipress_is_advanced_format', 1 );

		// Check if already processed
		if ( $this->is_already_organized( $attachment_id ) ) {
			return $metadata;
		}

		/**
		 * Filter whether to automatically create library item on upload.
		 *
		 * @param bool  $create        Whether to create item. Default true.
		 * @param int   $attachment_id Attachment ID.
		 * @param array $metadata      Attachment metadata.
		 */
		$should_create_item = apply_filters( 'optipress_organizer_auto_create_item', true, $attachment_id, $metadata );

		if ( $should_create_item ) {
			// Create library item from attachment
			$item_id = $this->create_item_from_attachment( $attachment_id, $metadata );

			if ( ! is_wp_error( $item_id ) && $item_id ) {
				// Link attachment to created item
				update_post_meta( $attachment_id, '_optipress_organizer_item_id', $item_id );

				do_action( 'optipress_organizer_item_created_from_upload', $item_id, $attachment_id, $metadata );
			}
		}

		// Return metadata unmodified - we don't change WordPress behavior
		return $metadata;
	}

	/**
	 * Create library item from attachment.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $metadata      Attachment metadata.
	 * @return int|WP_Error Item ID on success, WP_Error on failure.
	 */
	public function create_item_from_attachment( $attachment_id, $metadata ) {
		// Initialize managers (lazy loading)
		$this->init_managers();

		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) {
			return new WP_Error( 'invalid_attachment', __( 'Invalid attachment ID.', 'optipress' ) );
		}

		// Get file paths
		$original_file = $this->get_original_file( $attachment_id );
		$preview_file  = $this->get_preview_file( $attachment_id );

		if ( ! $original_file || ! file_exists( $original_file ) ) {
			return new WP_Error( 'missing_file', __( 'Original file not found.', 'optipress' ) );
		}

		// Prepare item data from attachment
		$item_data = array(
			'title'       => $attachment->post_title ? $attachment->post_title : basename( $original_file ),
			'description' => $attachment->post_content,
			'status'      => 'publish',
		);

		// Get default collection from settings
		$options = get_option( 'optipress_options', array() );
		if ( ! empty( $options['organizer_default_collection'] ) ) {
			$item_data['collection_id'] = absint( $options['organizer_default_collection'] );
		}

		// Create the item
		$item_id = $this->item_manager->create_item( $item_data );

		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		// Extract metadata from original file
		$file_metadata = $this->metadata_extractor->extract_all_metadata( $original_file );

		// Create file post for original
		$original_metadata = array(
			'width'  => isset( $file_metadata['dimensions']['width'] ) ? $file_metadata['dimensions']['width'] : null,
			'height' => isset( $file_metadata['dimensions']['height'] ) ? $file_metadata['dimensions']['height'] : null,
		);

		// Add EXIF and IPTC data if available
		if ( ! empty( $file_metadata['exif'] ) ) {
			$original_metadata['exif_data'] = $file_metadata['exif'];
		}
		if ( ! empty( $file_metadata['iptc'] ) ) {
			$original_metadata['iptc_data'] = $file_metadata['iptc'];
		}

		$original_id = $this->file_manager->add_file(
			$item_id,
			$original_file,
			'original',
			$original_metadata
		);

		if ( is_wp_error( $original_id ) ) {
			// Cleanup: delete the item if file creation failed
			$this->item_manager->delete_item( $item_id, true );
			return $original_id;
		}

		// Create file post for preview if it exists
		if ( $preview_file && file_exists( $preview_file ) ) {
			$preview_metadata = array(
				'width'  => isset( $metadata['width'] ) ? $metadata['width'] : null,
				'height' => isset( $metadata['height'] ) ? $metadata['height'] : null,
			);

			$preview_id = $this->file_manager->add_file(
				$item_id,
				$preview_file,
				'preview',
				$preview_metadata
			);

			if ( ! is_wp_error( $preview_id ) ) {
				// Set preview as display file
				update_post_meta( $item_id, '_optipress_display_file', $preview_id );
			}
		}

		// Store complete metadata on the item
		if ( ! empty( $file_metadata ) ) {
			$this->metadata_extractor->store_metadata( $item_id, $file_metadata );
		}

		// Create file posts for all generated sizes
		if ( ! empty( $metadata['sizes'] ) ) {
			$this->create_size_files( $item_id, $attachment_id, $metadata );
		}

		return $item_id;
	}

	/**
	 * Create file posts for all generated image sizes.
	 *
	 * @param int   $item_id       Item ID.
	 * @param int   $attachment_id Attachment ID.
	 * @param array $metadata      Attachment metadata with sizes.
	 * @return array Array of created file IDs.
	 */
	public function create_size_files( $item_id, $attachment_id, $metadata ) {
		// Initialize managers (lazy loading)
		$this->init_managers();

		$created_files = array();

		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return $created_files;
		}

		// Get upload directory
		$upload_dir = wp_upload_dir();
		$base_file  = get_attached_file( $attachment_id );
		$base_dir   = dirname( $base_file );

		foreach ( $metadata['sizes'] as $size_name => $size_data ) {
			if ( empty( $size_data['file'] ) ) {
				continue;
			}

			// Build full path to size file
			$size_file = trailingslashit( $base_dir ) . $size_data['file'];

			if ( ! file_exists( $size_file ) ) {
				continue;
			}

			// Prepare size metadata
			$size_metadata = array(
				'width'  => isset( $size_data['width'] ) ? $size_data['width'] : null,
				'height' => isset( $size_data['height'] ) ? $size_data['height'] : null,
			);

			// Store the size name for reference
			$size_metadata['size_name'] = $size_name;

			// Create file post for this size
			$file_id = $this->file_manager->add_file(
				$item_id,
				$size_file,
				'size',
				$size_metadata
			);

			if ( ! is_wp_error( $file_id ) ) {
				$created_files[ $size_name ] = $file_id;

				// Store size-specific meta
				update_post_meta( $file_id, '_optipress_size_name', $size_name );
			}
		}

		// Store array of size file IDs on the item
		if ( ! empty( $created_files ) ) {
			update_post_meta( $item_id, '_optipress_size_files', $created_files );

			do_action( 'optipress_organizer_size_files_created', $item_id, $created_files, $metadata );
		}

		return $created_files;
	}

	/**
	 * Detect if attachment was already processed by organizer.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if already processed.
	 */
	public function is_already_organized( $attachment_id ) {
		return (bool) get_post_meta( $attachment_id, '_optipress_organizer_item_id', true );
	}

	/**
	 * Get advanced format extensions.
	 *
	 * @return array Array of extensions.
	 */
	public function get_advanced_extensions() {
		return $this->advanced_exts;
	}

	/**
	 * Check if a file path is an advanced format.
	 *
	 * @param string $file_path File path.
	 * @return bool True if advanced format.
	 */
	public function is_advanced_format_file( $file_path ) {
		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		return in_array( $ext, $this->advanced_exts, true );
	}
}
