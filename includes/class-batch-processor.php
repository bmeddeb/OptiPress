<?php
/**
 * Batch Processor Class
 *
 * Handles batch processing of images and SVG files with AJAX-driven chunked processing.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * Batch Processor
 */
class Batch_Processor {

	/**
	 * Singleton instance
	 *
	 * @var Batch_Processor
	 */
	private static $instance = null;

	/**
	 * Images per batch for AJAX processing
	 *
	 * @var int
	 */
	const BATCH_SIZE = 15;

	/**
	 * Get singleton instance
	 *
	 * @return Batch_Processor
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
		// AJAX handlers
		add_action( 'wp_ajax_optipress_get_batch_stats', array( $this, 'ajax_get_batch_stats' ) );
		add_action( 'wp_ajax_optipress_process_batch', array( $this, 'ajax_process_batch' ) );
		add_action( 'wp_ajax_optipress_revert_images', array( $this, 'ajax_revert_images' ) );
		add_action( 'wp_ajax_optipress_sanitize_svg_batch', array( $this, 'ajax_sanitize_svg_batch' ) );
	}

	/**
	 * Get batch statistics
	 *
	 * Returns counts of processable images and their conversion status.
	 */
	public function ajax_get_batch_stats() {
		// Verify nonce
		check_ajax_referer( 'optipress_admin', 'nonce' );

		// Verify permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'optipress' ) ) );
		}

		$stats = $this->get_image_stats();

		wp_send_json_success( $stats );
	}

	/**
	 * Process a batch of images
	 */
	public function ajax_process_batch() {
		// Verify nonce
		check_ajax_referer( 'optipress_admin', 'nonce' );

		// Verify permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'optipress' ) ) );
		}

		// Get offset
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		// Process batch
		$result = $this->process_image_batch( $offset, self::BATCH_SIZE );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Revert converted images
	 */
	public function ajax_revert_images() {
		// Verify nonce
		check_ajax_referer( 'optipress_admin', 'nonce' );

		// Verify permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'optipress' ) ) );
		}

		// Get offset
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		// Process revert batch
		$result = $this->revert_image_batch( $offset, self::BATCH_SIZE );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Batch sanitize existing SVG files
	 */
	public function ajax_sanitize_svg_batch() {
		// Verify nonce
		check_ajax_referer( 'optipress_admin', 'nonce' );

		// Verify permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'optipress' ) ) );
		}

		// Get offset
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		// Process SVG batch
		$result = $this->sanitize_svg_batch( $offset, self::BATCH_SIZE );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Get image statistics
	 *
	 * @return array Statistics about processable images.
	 */
	private function get_image_stats() {
		global $wpdb;

		// Get supported MIME types from available engines
		$mime_types_in = $this->get_mime_types_sql_in();

		// Get all supported image attachments
		$query = "
			SELECT COUNT(ID) as total
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_mime_type IN ($mime_types_in)
		";

		$total = absint( $wpdb->get_var( $query ) );

		// Count already converted images
		$converted_query = "
			SELECT COUNT(DISTINCT p.ID) as converted
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type IN ($mime_types_in)
			AND pm.meta_key = '_optipress_converted'
			AND pm.meta_value = '1'
		";

		$converted = absint( $wpdb->get_var( $converted_query ) );

		// Count SVG files
		$svg_query = "
			SELECT COUNT(ID) as total
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_mime_type = 'image/svg+xml'
		";

		$svg_total = absint( $wpdb->get_var( $svg_query ) );

		return array(
			'total'           => $total,
			'converted'       => $converted,
			'remaining'       => max( 0, $total - $converted ),
			'svg_total'       => $svg_total,
		);
	}

	/**
	 * Get SQL IN clause for supported MIME types
	 *
	 * @return string SQL-safe comma-separated quoted MIME types.
	 */
	private function get_mime_types_sql_in() {
		global $wpdb;

		$registry = \OptiPress\Engines\Engine_Registry::get_instance();
		$mime_types = $registry->get_all_supported_input_formats();

		if ( empty( $mime_types ) ) {
			// Fallback to JPEG and PNG if no engines available
			$mime_types = array( 'image/jpeg', 'image/png' );
		}

		// Prepare each MIME type for SQL
		$prepared = array_map( function( $mime ) use ( $wpdb ) {
			return $wpdb->prepare( '%s', $mime );
		}, $mime_types );

		return implode( ',', $prepared );
	}

	/**
	 * Process a batch of images
	 *
	 * @param int $offset Starting offset.
	 * @param int $limit  Number of images to process.
	 * @return array|WP_Error Processing results.
	 */
	private function process_image_batch( $offset, $limit ) {
		global $wpdb;

		// Get supported MIME types
		$mime_types_in = $this->get_mime_types_sql_in();

		// Get unconverted images
		$query = $wpdb->prepare(
			"
			SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_optipress_converted'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type IN ($mime_types_in)
			AND (pm.meta_value IS NULL OR pm.meta_value != '1')
			ORDER BY p.ID ASC
			LIMIT %d OFFSET %d
			",
			$limit,
			$offset
		);

		$image_ids = $wpdb->get_col( $query );

		if ( empty( $image_ids ) ) {
			return array(
				'processed' => 0,
				'message'   => __( 'No more images to process.', 'optipress' ),
			);
		}

		// Get image converter
		$converter = Image_Converter::get_instance();

		// Process each image
		$processed  = 0;
		$errors     = array();

		foreach ( $image_ids as $image_id ) {
			$file_path = get_attached_file( $image_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$errors[] = sprintf(
					/* translators: %d: Image ID */
					__( 'File not found for image ID %d', 'optipress' ),
					$image_id
				);
				continue;
			}

			// Get attachment metadata
			$metadata = wp_get_attachment_metadata( $image_id );

			if ( ! $metadata ) {
				$errors[] = sprintf(
					/* translators: %d: Image ID */
					__( 'No metadata found for image ID %d', 'optipress' ),
					$image_id
				);
				continue;
			}

			// Process main image (pass attachment ID for proper logging)
			$success = $converter->convert_image( $file_path, null, null, $image_id );

			// Process image sizes
			if ( ! empty( $metadata['sizes'] ) ) {
				$upload_dir = wp_get_upload_dir();
				$base_dir   = trailingslashit( dirname( $file_path ) );

				foreach ( $metadata['sizes'] as $size => $size_data ) {
					$size_file = $base_dir . $size_data['file'];
					if ( file_exists( $size_file ) ) {
						$converter->convert_image( $size_file, null, null, $image_id );
					}
				}
			}

			if ( $success ) {
				// Mark as converted
				update_post_meta( $image_id, '_optipress_converted', '1' );
				$processed++;
			} else {
				$errors[] = sprintf(
					/* translators: %d: Image ID */
					__( 'Failed to convert image ID %d', 'optipress' ),
					$image_id
				);
			}
		}

		$result = array(
			'processed' => $processed,
			'batch_size' => count( $image_ids ),
		);

		if ( ! empty( $errors ) ) {
			$result['errors'] = $errors;
		}

		return $result;
	}

	/**
	 * Revert a batch of converted images
	 *
	 * @param int $offset Starting offset.
	 * @param int $limit  Number of images to process.
	 * @return array|WP_Error Revert results.
	 */
	private function revert_image_batch( $offset, $limit ) {
		global $wpdb;

		// Get supported MIME types
		$mime_types_in = $this->get_mime_types_sql_in();

		// Get converted images
		$query = $wpdb->prepare(
			"
			SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type IN ($mime_types_in)
			AND pm.meta_key = '_optipress_converted'
			AND pm.meta_value = '1'
			ORDER BY p.ID ASC
			LIMIT %d OFFSET %d
			",
			$limit,
			$offset
		);

		$image_ids = $wpdb->get_col( $query );

		if ( empty( $image_ids ) ) {
			return array(
				'reverted' => 0,
				'message'  => __( 'No more images to revert.', 'optipress' ),
			);
		}

		// Get plugin options to check format
		$options = get_option( 'optipress_options', array() );
		$format  = isset( $options['format'] ) ? $options['format'] : 'webp';

		$reverted = 0;
		$errors   = array();

		foreach ( $image_ids as $image_id ) {
			$file_path = get_attached_file( $image_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$errors[] = sprintf(
					/* translators: %d: Image ID */
					__( 'Original file not found for image ID %d', 'optipress' ),
					$image_id
				);
				continue;
			}

			// Delete converted files
			$pathinfo  = pathinfo( $file_path );
			$converted = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.' . $format;

			if ( file_exists( $converted ) ) {
				wp_delete_file( $converted );
			}

			// Delete converted sizes
			$metadata = wp_get_attachment_metadata( $image_id );
			if ( ! empty( $metadata['sizes'] ) ) {
				$base_dir = trailingslashit( dirname( $file_path ) );

				foreach ( $metadata['sizes'] as $size => $size_data ) {
					$size_pathinfo = pathinfo( $size_data['file'] );
					$size_converted = $base_dir . $size_pathinfo['filename'] . '.' . $format;

					if ( file_exists( $size_converted ) ) {
						wp_delete_file( $size_converted );
					}
				}
			}

			// Remove conversion flag
			delete_post_meta( $image_id, '_optipress_converted' );
			$reverted++;
		}

		$result = array(
			'reverted'   => $reverted,
			'batch_size' => count( $image_ids ),
		);

		if ( ! empty( $errors ) ) {
			$result['errors'] = $errors;
		}

		return $result;
	}

	/**
	 * Sanitize a batch of SVG files
	 *
	 * @param int $offset Starting offset.
	 * @param int $limit  Number of SVGs to process.
	 * @return array|WP_Error Processing results.
	 */
	private function sanitize_svg_batch( $offset, $limit ) {
		global $wpdb;

		// Get SVG attachments
		$query = $wpdb->prepare(
			"
			SELECT ID
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_mime_type = 'image/svg+xml'
			ORDER BY ID ASC
			LIMIT %d OFFSET %d
			",
			$limit,
			$offset
		);

		$svg_ids = $wpdb->get_col( $query );

		if ( empty( $svg_ids ) ) {
			return array(
				'sanitized' => 0,
				'message'   => __( 'No more SVG files to process.', 'optipress' ),
			);
		}

		// Get SVG sanitizer
		$sanitizer = SVG_Sanitizer::get_instance();

		$sanitized = 0;
		$errors    = array();

		foreach ( $svg_ids as $svg_id ) {
			$file_path = get_attached_file( $svg_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$errors[] = sprintf(
					/* translators: %d: SVG ID */
					__( 'File not found for SVG ID %d', 'optipress' ),
					$svg_id
				);
				continue;
			}

			// Read SVG content
			$svg_content = file_get_contents( $file_path );

			if ( false === $svg_content ) {
				$errors[] = sprintf(
					/* translators: %d: SVG ID */
					__( 'Failed to read SVG file ID %d', 'optipress' ),
					$svg_id
				);
				continue;
			}

			// Sanitize
			$clean_svg = $sanitizer->sanitize_svg_content( $svg_content );

			if ( is_wp_error( $clean_svg ) ) {
				$errors[] = sprintf(
					/* translators: 1: SVG ID, 2: Error message */
					__( 'Failed to sanitize SVG ID %1$d: %2$s', 'optipress' ),
					$svg_id,
					$clean_svg->get_error_message()
				);
				continue;
			}

			// Write sanitized content back
			$written = file_put_contents( $file_path, $clean_svg );

			if ( false === $written ) {
				$errors[] = sprintf(
					/* translators: %d: SVG ID */
					__( 'Failed to write sanitized SVG ID %d', 'optipress' ),
					$svg_id
				);
				continue;
			}

			$sanitized++;
		}

		$result = array(
			'sanitized'  => $sanitized,
			'batch_size' => count( $svg_ids ),
		);

		if ( ! empty( $errors ) ) {
			$result['errors'] = $errors;
		}

		return $result;
	}
}