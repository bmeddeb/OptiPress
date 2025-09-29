<?php
/**
 * Image Converter Class
 *
 * Handles automatic image conversion on upload.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * Image_Converter class
 *
 * Converts uploaded images to WebP or AVIF format automatically.
 * Hooks into WordPress media upload process.
 */
class Image_Converter {

	/**
	 * Singleton instance
	 *
	 * @var Image_Converter
	 */
	private static $instance = null;

	/**
	 * Plugin options
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Conversion errors log
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Get singleton instance
	 *
	 * @return Image_Converter
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->options = get_option( 'optipress_options', array() );

		// Hook into attachment metadata generation
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'convert_on_upload' ), 10, 2 );

		// Hook to display admin notices for conversion errors
		add_action( 'admin_notices', array( $this, 'display_conversion_errors' ) );
	}

	/**
	 * Convert images when attachment metadata is generated
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Modified metadata.
	 */
	public function convert_on_upload( $metadata, $attachment_id ) {
		// Check if auto-convert is enabled
		if ( ! $this->is_auto_convert_enabled() ) {
			return $metadata;
		}

		// Check if this is an image attachment
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return $metadata;
		}

		// Get the file path
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}

		// Check if file type should be converted (only JPG, PNG)
		if ( ! $this->should_convert_file( $file_path ) ) {
			return $metadata;
		}

		// Get target format from settings
		$format = $this->get_target_format();

		// Get appropriate engine
		$registry = \OptiPress\Engines\Engine_Registry::get_instance();
		$engine   = $registry->get_engine_from_settings( $format );

		if ( null === $engine ) {
			$this->log_error(
				$attachment_id,
				sprintf(
					/* translators: %s: Format name */
					__( 'No available engine supports %s format.', 'optipress' ),
					strtoupper( $format )
				)
			);
			return $metadata;
		}

		// Convert the full-size image
		$converted_full = $this->convert_image( $file_path, $format, $engine, $attachment_id );

		// Convert all image sizes
		$converted_sizes = array();
		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$upload_dir = dirname( $file_path );

			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( ! isset( $size_data['file'] ) ) {
					continue;
				}

				$size_path = trailingslashit( $upload_dir ) . $size_data['file'];

				if ( file_exists( $size_path ) ) {
					$converted = $this->convert_image( $size_path, $format, $engine, $attachment_id );
					if ( $converted ) {
						$converted_sizes[] = $size_name;
					}
				}
			}
		}

		// Store conversion metadata
		if ( $converted_full || ! empty( $converted_sizes ) ) {
			update_post_meta( $attachment_id, '_optipress_converted', true );
			update_post_meta( $attachment_id, '_optipress_format', $format );
			update_post_meta( $attachment_id, '_optipress_engine', $engine->get_name() );
			update_post_meta( $attachment_id, '_optipress_converted_sizes', $converted_sizes );
			update_post_meta( $attachment_id, '_optipress_conversion_date', current_time( 'mysql' ) );

			// Handle "Keep Originals" setting
			if ( ! $this->should_keep_originals() ) {
				$this->delete_original_files( $file_path, $metadata );
			}
		} else {
			// Conversion failed for all images
			update_post_meta( $attachment_id, '_optipress_converted', false );
		}

		return $metadata;
	}

	/**
	 * Convert a single image file
	 *
	 * @param string                              $source_path Path to source image.
	 * @param string                              $format      Target format.
	 * @param \OptiPress\Engines\ImageEngineInterface $engine      Conversion engine.
	 * @param int                                 $attachment_id Attachment ID for logging.
	 * @return bool Whether conversion was successful.
	 */
	private function convert_image( $source_path, $format, $engine, $attachment_id ) {
		// Generate destination path
		$path_info = pathinfo( $source_path );
		$dest_path = trailingslashit( $path_info['dirname'] ) . $path_info['filename'] . '.' . $format;

		// Get quality from settings
		$quality = $this->get_quality();

		// Perform conversion
		$result = $engine->convert( $source_path, $dest_path, $format, $quality );

		// Log error if conversion failed
		if ( ! $result ) {
			$this->log_error(
				$attachment_id,
				sprintf(
					/* translators: %s: File name */
					__( 'Failed to convert %s', 'optipress' ),
					basename( $source_path )
				)
			);
		}

		return $result;
	}

	/**
	 * Delete original files after successful conversion
	 *
	 * @param string $file_path Full path to original file.
	 * @param array  $metadata  Attachment metadata.
	 */
	private function delete_original_files( $file_path, $metadata ) {
		// Delete full-size original
		if ( file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}

		// Delete original image sizes
		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$upload_dir = dirname( $file_path );

			foreach ( $metadata['sizes'] as $size_data ) {
				if ( ! isset( $size_data['file'] ) ) {
					continue;
				}

				$size_path = trailingslashit( $upload_dir ) . $size_data['file'];

				if ( file_exists( $size_path ) ) {
					wp_delete_file( $size_path );
				}
			}
		}
	}

	/**
	 * Check if file should be converted
	 *
	 * Only convert JPEG and PNG files.
	 *
	 * @param string $file_path Path to file.
	 * @return bool Whether file should be converted.
	 */
	private function should_convert_file( $file_path ) {
		$image_info = @getimagesize( $file_path );

		if ( false === $image_info ) {
			return false;
		}

		$allowed_types = array( 'image/jpeg', 'image/png' );

		return in_array( $image_info['mime'], $allowed_types, true );
	}

	/**
	 * Check if auto-convert is enabled
	 *
	 * @return bool Whether auto-convert is enabled.
	 */
	private function is_auto_convert_enabled() {
		return isset( $this->options['auto_convert'] ) && true === $this->options['auto_convert'];
	}

	/**
	 * Check if original files should be kept
	 *
	 * @return bool Whether to keep originals.
	 */
	private function should_keep_originals() {
		return isset( $this->options['keep_originals'] ) && true === $this->options['keep_originals'];
	}

	/**
	 * Get target format from settings
	 *
	 * @return string Target format ('webp' or 'avif').
	 */
	private function get_target_format() {
		return isset( $this->options['format'] ) ? $this->options['format'] : 'webp';
	}

	/**
	 * Get quality setting
	 *
	 * @return int Quality level (1-100).
	 */
	private function get_quality() {
		$quality = isset( $this->options['quality'] ) ? intval( $this->options['quality'] ) : 85;
		return max( 1, min( 100, $quality ) );
	}

	/**
	 * Log conversion error
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $message       Error message.
	 */
	private function log_error( $attachment_id, $message ) {
		$this->errors[] = array(
			'attachment_id' => $attachment_id,
			'message'       => $message,
			'time'          => current_time( 'mysql' ),
		);

		// Store in post meta for persistence
		$existing_errors = get_post_meta( $attachment_id, '_optipress_errors', true );
		if ( ! is_array( $existing_errors ) ) {
			$existing_errors = array();
		}

		$existing_errors[] = array(
			'message' => $message,
			'time'    => current_time( 'mysql' ),
		);

		update_post_meta( $attachment_id, '_optipress_errors', $existing_errors );

		// Log to WordPress debug log if enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( 'OptiPress: [Attachment ID: %d] %s', $attachment_id, $message ) );
		}
	}

	/**
	 * Display conversion error notices in admin
	 */
	public function display_conversion_errors() {
		if ( empty( $this->errors ) ) {
			return;
		}

		// Only show on media library or upload pages
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'upload', 'media', 'attachment' ), true ) ) {
			return;
		}

		foreach ( $this->errors as $error ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p><strong>%s:</strong> %s</p></div>',
				esc_html__( 'OptiPress Conversion Warning', 'optipress' ),
				esc_html( $error['message'] )
			);
		}
	}

	/**
	 * Manually convert an attachment
	 *
	 * Can be used for batch processing.
	 *
	 * @param int $attachment_id Attachment ID to convert.
	 * @return bool Whether conversion was successful.
	 */
	public function convert_attachment( $attachment_id ) {
		// Check if this is an image attachment
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		// Get the file path
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return false;
		}

		// Check if already converted
		$already_converted = get_post_meta( $attachment_id, '_optipress_converted', true );
		if ( $already_converted ) {
			return true; // Already converted
		}

		// Get metadata
		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Trigger conversion
		$this->convert_on_upload( $metadata, $attachment_id );

		// Check if conversion was successful
		return (bool) get_post_meta( $attachment_id, '_optipress_converted', true );
	}

	/**
	 * Check if an attachment has been converted
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool Whether attachment has been converted.
	 */
	public function is_converted( $attachment_id ) {
		return (bool) get_post_meta( $attachment_id, '_optipress_converted', true );
	}

	/**
	 * Get conversion info for an attachment
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null Conversion info or null if not converted.
	 */
	public function get_conversion_info( $attachment_id ) {
		if ( ! $this->is_converted( $attachment_id ) ) {
			return null;
		}

		return array(
			'format'         => get_post_meta( $attachment_id, '_optipress_format', true ),
			'engine'         => get_post_meta( $attachment_id, '_optipress_engine', true ),
			'converted_sizes' => get_post_meta( $attachment_id, '_optipress_converted_sizes', true ),
			'conversion_date' => get_post_meta( $attachment_id, '_optipress_conversion_date', true ),
		);
	}
}