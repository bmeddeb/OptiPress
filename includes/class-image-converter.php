<?php
/**
 * Image Converter Class
 *
 * Handles automatic image conversion on upload and serves converted images.
 *
 * This class operates at the WordPress API level, filtering image URLs through
 * WordPress core functions (wp_get_attachment_url, wp_calculate_image_srcset, etc.).
 *
 * DELIVERY PATH PRECEDENCE:
 * 1. Image_Converter (this class): Filters WordPress image functions (API level)
 *    - wp_get_attachment_url: Direct attachment URLs
 *    - wp_get_attachment_image_src: Image tag sources
 *    - wp_calculate_image_srcset: Responsive image srcsets
 *
 * 2. Content_Filter (class-content-filter.php): Filters HTML output (content level)
 *    - the_content, post_thumbnail_html, etc.: Catches hardcoded URLs
 *
 * These work complementary, not duplicated:
 * - Image_Converter catches images served through WP functions (theme/plugin code)
 * - Content_Filter catches remaining images in HTML content (editor, shortcodes, etc.)
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

		// Hook to serve converted images
		add_filter( 'wp_get_attachment_url', array( $this, 'filter_attachment_url' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'filter_image_src' ), 10, 4 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'filter_image_srcset' ), 10, 5 );

		// Hook to modify attachment details display
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'modify_attachment_for_js' ), 10, 3 );

		// Add custom columns to media library
		add_filter( 'manage_media_columns', array( $this, 'add_media_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'display_media_column' ), 10, 2 );

		// Hook to clean up converted files when attachment is deleted
		add_action( 'delete_attachment', array( $this, 'delete_converted_files' ), 10, 1 );

		// AJAX handler for checking conversion status
		add_action( 'wp_ajax_optipress_check_conversion_status', array( $this, 'ajax_check_conversion_status' ) );

		// Hook to track conversion queue and show summary notices
		add_action( 'admin_notices', array( $this, 'display_conversion_summary' ) );
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
		$mime_type = get_post_mime_type( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}

		// Check if file type should be converted
		if ( ! $this->should_convert_file( $file_path ) ) {
			return $metadata;
		}

		// Mark as processing immediately (before conversion starts)
		// This allows JavaScript to detect conversion is happening
		update_post_meta( $attachment_id, '_optipress_converting', true );

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

		// Calculate original file sizes before conversion
		$original_size = filesize( $file_path );
		$original_total_size = $original_size;

		// Convert the full-size image
		$converted_full = $this->convert_image( $file_path, $format, $engine, $attachment_id );

		// Convert all image sizes
		$converted_sizes = array();
		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) && ! empty( $metadata['sizes'] ) ) {
			$upload_dir = dirname( $file_path );

			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( ! isset( $size_data['file'] ) ) {
					continue;
				}

				$size_path = trailingslashit( $upload_dir ) . $size_data['file'];

				if ( file_exists( $size_path ) ) {
					// Add to original total size
					$original_total_size += filesize( $size_path );

					$converted = $this->convert_image( $size_path, $format, $engine, $attachment_id );
					if ( $converted ) {
						$converted_sizes[] = $size_name;
					}
				}
			}
		} else {
			// No WordPress-generated thumbnails exist
			// This can happen with formats WordPress doesn't natively support (TIFF, PSD, etc.)
		}

		// Store conversion metadata
		if ( $converted_full || ! empty( $converted_sizes ) ) {
			// Calculate converted total size
			$converted_total_size = 0;
			$path_info = pathinfo( $file_path );
			$converted_path = trailingslashit( $path_info['dirname'] ) . $path_info['filename'] . '.' . $format;

			if ( file_exists( $converted_path ) ) {
				$converted_total_size += filesize( $converted_path );
			}

			// Add size of all converted image sizes
			if ( ! empty( $converted_sizes ) && isset( $metadata['sizes'] ) ) {
				$upload_dir = dirname( $file_path );

				foreach ( $metadata['sizes'] as $size_name => $size_data ) {
					if ( ! isset( $size_data['file'] ) || ! in_array( $size_name, $converted_sizes, true ) ) {
						continue;
					}

					$size_path_info = pathinfo( $size_data['file'] );
					$converted_size_path = trailingslashit( $upload_dir ) . $size_path_info['filename'] . '.' . $format;

					if ( file_exists( $converted_size_path ) ) {
						$converted_total_size += filesize( $converted_size_path );
					}
				}
			}

			// Calculate savings
			$bytes_saved = $original_total_size - $converted_total_size;
			$percent_saved = $original_total_size > 0 ? ( $bytes_saved / $original_total_size ) * 100 : 0;

			// Remove processing flag
			delete_post_meta( $attachment_id, '_optipress_converting' );

			update_post_meta( $attachment_id, '_optipress_converted', true );
			update_post_meta( $attachment_id, '_optipress_format', $format );
			update_post_meta( $attachment_id, '_optipress_engine', $engine->get_name() );
			update_post_meta( $attachment_id, '_optipress_converted_sizes', $converted_sizes );
			update_post_meta( $attachment_id, '_optipress_conversion_date', current_time( 'mysql' ) );
			update_post_meta( $attachment_id, '_optipress_original_size', $original_total_size );
			update_post_meta( $attachment_id, '_optipress_converted_size', $converted_total_size );
			update_post_meta( $attachment_id, '_optipress_bytes_saved', $bytes_saved );
			update_post_meta( $attachment_id, '_optipress_percent_saved', round( $percent_saved, 2 ) );

			// Track for summary notice
			$this->track_conversion_for_summary( $attachment_id, $metadata );

			// Handle "Keep Originals" setting
			if ( ! $this->should_keep_originals() ) {
				$this->delete_original_files( $file_path, $metadata );
			}
		} else {
			// Conversion failed for all images
			delete_post_meta( $attachment_id, '_optipress_converting' );
			update_post_meta( $attachment_id, '_optipress_converted', false );
		}

		return $metadata;
	}

	/**
	 * Convert a single image file
	 *
	 * This method is public so it can be used by the batch processor. If
	 * $format or $engine are not provided the method will resolve them from
	 * plugin settings.
	 *
	 * @param string                              $source_path   Path to source image.
	 * @param string|null                         $format        Target format (e.g. 'webp' or 'avif').
	 * @param \OptiPress\Engines\ImageEngineInterface|null $engine        Conversion engine instance.
	 * @param int                                 $attachment_id Attachment ID for logging (optional).
	 * @return bool Whether conversion was successful.
	 */
	public function convert_image( $source_path, $format = null, $engine = null, $attachment_id = 0 ) {
		// Resolve target format from settings if not provided
		if ( null === $format ) {
			$format = $this->get_target_format();
		}

		// Resolve engine from registry if not provided
		if ( null === $engine ) {
			$registry = \OptiPress\Engines\Engine_Registry::get_instance();
			$engine   = $registry->get_engine_from_settings( $format );

			if ( null === $engine ) {
				// Prefer to attach error to the post when we have an attachment ID
				if ( $attachment_id ) {
					$this->log_error(
						$attachment_id,
						sprintf(
							/* translators: %s: Format name */
							__( 'No available engine supports %s format.', 'optipress' ),
							strtoupper( $format )
						)
					);
				}

				return false;
			}
		}
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
	 * Checks if the file type is supported by any available conversion engine.
	 *
	 * @param string $file_path Path to file.
	 * @return bool Whether file should be converted.
	 */
	private function should_convert_file( $file_path ) {
		$image_info = @getimagesize( $file_path );

		if ( false === $image_info ) {
			return false;
		}

		// Get supported formats from available engines
		$registry = \OptiPress\Engines\Engine_Registry::get_instance();
		return $registry->is_mime_type_supported( $image_info['mime'] );
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
			'format'          => get_post_meta( $attachment_id, '_optipress_format', true ),
			'engine'          => get_post_meta( $attachment_id, '_optipress_engine', true ),
			'converted_sizes' => get_post_meta( $attachment_id, '_optipress_converted_sizes', true ),
			'conversion_date' => get_post_meta( $attachment_id, '_optipress_conversion_date', true ),
		);
	}

	/**
	 * Filter attachment URL to serve converted version
	 *
	 * @param string $url           Attachment URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string Modified URL.
	 */
	public function filter_attachment_url( $url, $attachment_id ) {
		// Check if attachment is converted
		if ( ! $this->is_converted( $attachment_id ) ) {
			return $url;
		}

		// Get conversion format
		$format = get_post_meta( $attachment_id, '_optipress_format', true );

		if ( empty( $format ) ) {
			return $url;
		}

		// Generate converted URL
		$converted_url = $this->get_converted_url( $url, $format );

		// Check if converted file exists
		$converted_path = $this->url_to_path( $converted_url );

		if ( $converted_path && file_exists( $converted_path ) ) {
			return $converted_url;
		}

		return $url;
	}

	/**
	 * Filter image src to serve converted version
	 *
	 * @param array|false  $image         Array of image data or false.
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Image size.
	 * @param bool         $icon          Whether to use icon.
	 * @return array|false Modified image data.
	 */
	public function filter_image_src( $image, $attachment_id, $size, $icon ) {
		if ( false === $image || ! $this->is_converted( $attachment_id ) ) {
			return $image;
		}

		// Get conversion format
		$format = get_post_meta( $attachment_id, '_optipress_format', true );

		if ( empty( $format ) ) {
			return $image;
		}

		// Modify URL (index 0)
		if ( isset( $image[0] ) ) {
			$converted_url  = $this->get_converted_url( $image[0], $format );
			$converted_path = $this->url_to_path( $converted_url );

			if ( $converted_path && file_exists( $converted_path ) ) {
				$image[0] = $converted_url;
			}
		}

		return $image;
	}

	/**
	 * Filter image srcset to serve converted versions
	 *
	 * @param array  $sources       Array of image sources.
	 * @param array  $size_array    Array of width and height values.
	 * @param string $image_src     The image src.
	 * @param array  $image_meta    The image metadata.
	 * @param int    $attachment_id Attachment ID.
	 * @return array Modified sources.
	 */
	public function filter_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( ! $this->is_converted( $attachment_id ) ) {
			return $sources;
		}

		// Get conversion format
		$format = get_post_meta( $attachment_id, '_optipress_format', true );

		if ( empty( $format ) ) {
			return $sources;
		}

		// Convert each source URL
		foreach ( $sources as $width => $source ) {
			if ( isset( $source['url'] ) ) {
				$converted_url  = $this->get_converted_url( $source['url'], $format );
				$converted_path = $this->url_to_path( $converted_url );

				if ( $converted_path && file_exists( $converted_path ) ) {
					$sources[ $width ]['url'] = $converted_url;
				}
			}
		}

		return $sources;
	}

	/**
	 * Convert image URL to converted format URL
	 *
	 * @param string $url    Original URL.
	 * @param string $format Target format (webp or avif).
	 * @return string Converted URL.
	 */
	private function get_converted_url( $url, $format ) {
		// Replace extension
		return preg_replace( '/\.(jpg|jpeg|png)$/i', '.' . $format, $url );
	}

	/**
	 * Convert URL to file system path
	 *
	 * @param string $url URL to convert.
	 * @return string|false File path or false on failure.
	 */
	private function url_to_path( $url ) {
		$upload_dir = wp_upload_dir();

		// Check if URL is in uploads directory
		if ( 0 !== strpos( $url, $upload_dir['baseurl'] ) ) {
			return false;
		}

		// Convert URL to path
		$path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );

		return $path;
	}

	/**
	 * Modify attachment data for JavaScript (attachment details modal)
	 *
	 * @param array      $response   Array of prepared attachment data.
	 * @param WP_Post    $attachment Attachment object.
	 * @param array|bool $meta       Array of attachment meta data, or false.
	 * @return array Modified response.
	 */
	public function modify_attachment_for_js( $response, $attachment, $meta ) {
		if ( ! $this->is_converted( $attachment->ID ) ) {
			return $response;
		}

		$format = get_post_meta( $attachment->ID, '_optipress_format', true );
		$bytes_saved = get_post_meta( $attachment->ID, '_optipress_bytes_saved', true );
		$percent_saved = get_post_meta( $attachment->ID, '_optipress_percent_saved', true );

		// Update filename display to show converted format
		if ( ! empty( $format ) && isset( $response['filename'] ) ) {
			$response['filename'] = preg_replace( '/\.(jpg|jpeg|png)$/i', '.' . $format, $response['filename'] );
		}

		// Add conversion info to filesizeHumanReadable
		if ( $bytes_saved > 0 && isset( $response['filesizeHumanReadable'] ) ) {
			$response['filesizeHumanReadable'] .= sprintf(
				' <span style="color: #46b450; font-weight: 600;" title="%s">(%s saved, %.1f%%)</span>',
				esc_attr__( 'Space saved by OptiPress conversion', 'optipress' ),
				size_format( $bytes_saved, 1 ),
				$percent_saved
			);
		}

		// Add conversion info to description
		if ( $bytes_saved > 0 ) {
			$conversion_info = sprintf(
				/* translators: 1: Format (WebP/AVIF), 2: Bytes saved, 3: Percent saved */
				__( 'Converted to %1$s • Saved %2$s (%3$s%%)', 'optipress' ),
				strtoupper( $format ),
				size_format( $bytes_saved, 1 ),
				number_format( $percent_saved, 1 )
			);

			if ( isset( $response['description'] ) && ! empty( $response['description'] ) ) {
				$response['description'] .= "\n\n" . $conversion_info;
			} else {
				$response['description'] = $conversion_info;
			}
		}

		return $response;
	}

	/**
	 * Add custom columns to media library
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_media_columns( $columns ) {
		// Add "Space Saved" column after the title
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			if ( 'title' === $key ) {
				$new_columns['optipress_savings'] = __( 'Space Saved', 'optipress' );
			}
		}

		return $new_columns;
	}

	/**
	 * Display custom column content in media library
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Attachment ID.
	 */
	public function display_media_column( $column_name, $post_id ) {
		if ( 'optipress_savings' !== $column_name ) {
			return;
		}

		if ( ! $this->is_converted( $post_id ) ) {
			echo '<span style="color: #999;">—</span>';
			return;
		}

		$bytes_saved = get_post_meta( $post_id, '_optipress_bytes_saved', true );
		$percent_saved = get_post_meta( $post_id, '_optipress_percent_saved', true );
		$format = get_post_meta( $post_id, '_optipress_format', true );

		if ( $bytes_saved > 0 ) {
			printf(
				'<span style="color: #46b450; font-weight: 600;" title="%s">%s<br><small>(%s%%)</small></span>',
				esc_attr( sprintf(
					/* translators: %s: Format (WebP/AVIF) */
					__( 'Converted to %s', 'optipress' ),
					strtoupper( $format )
				) ),
				esc_html( size_format( $bytes_saved, 1 ) ),
				esc_html( number_format( $percent_saved, 1 ) )
			);
		} elseif ( $bytes_saved < 0 ) {
			printf(
				'<span style="color: #dc3232;" title="%s">%s<br><small>(%s%%)</small></span>',
				esc_attr__( 'Converted file is larger', 'optipress' ),
				esc_html( size_format( abs( $bytes_saved ), 1 ) ),
				esc_html( number_format( abs( $percent_saved ), 1 ) )
			);
		} else {
			printf(
				'<span style="color: #999;">%s</span>',
				esc_html__( 'Same size', 'optipress' )
			);
		}
	}

	/**
	 * Delete converted files when attachment is deleted
	 *
	 * Cleans up WebP/AVIF files that were generated for this attachment.
	 * Called by WordPress delete_attachment action.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public function delete_converted_files( $attachment_id ) {
		// Get attachment metadata
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! $metadata ) {
			return;
		}

		// Get the file path
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path ) {
			return;
		}

		// Check if this is a supported image type
		$mime_type = get_post_mime_type( $attachment_id );
		$registry = \OptiPress\Engines\Engine_Registry::get_instance();

		if ( ! $registry->is_mime_type_supported( $mime_type ) ) {
			return;
		}

		// Delete both WebP and AVIF versions (user might have changed format over time)
		$formats = array( 'webp', 'avif' );

		// Delete main file converted versions
		foreach ( $formats as $format ) {
			$pathinfo = pathinfo( $file_path );
			$converted_file = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.' . $format;

			if ( file_exists( $converted_file ) ) {
				wp_delete_file( $converted_file );
			}
		}

		// Delete converted versions of all image sizes
		if ( ! empty( $metadata['sizes'] ) ) {
			$upload_dir = wp_get_upload_dir();
			$base_dir = trailingslashit( dirname( $file_path ) );

			foreach ( $metadata['sizes'] as $size => $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}

				// Delete converted versions for each size
				foreach ( $formats as $format ) {
					$size_pathinfo = pathinfo( $size_data['file'] );
					$converted_size_file = $base_dir . $size_pathinfo['filename'] . '.' . $format;

					if ( file_exists( $converted_size_file ) ) {
						wp_delete_file( $converted_size_file );
					}
				}
			}
		}
	}

	/**
	 * Convert a single attachment to specified format
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Target format (webp or avif).
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function convert_single_attachment( $attachment_id, $format ) {
		// Validate attachment
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return new \WP_Error( 'invalid_attachment', __( 'Attachment is not an image.', 'optipress' ) );
		}

		// Get file path
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Image file not found.', 'optipress' ) );
		}

		// Check if file type should be converted
		if ( ! $this->should_convert_file( $file_path ) ) {
			return new \WP_Error( 'unsupported_type', __( 'Image type not supported for conversion.', 'optipress' ) );
		}

		// Validate format
		if ( ! in_array( $format, array( 'webp', 'avif' ), true ) ) {
			return new \WP_Error( 'invalid_format', __( 'Invalid target format.', 'optipress' ) );
		}

		// Get appropriate engine
		$registry = \OptiPress\Engines\Engine_Registry::get_instance();
		$engine   = $registry->get_engine_from_settings( $format );

		if ( null === $engine ) {
			return new \WP_Error(
				'no_engine',
				sprintf(
					/* translators: %s: Format name */
					__( 'No available engine supports %s format.', 'optipress' ),
					strtoupper( $format )
				)
			);
		}

		// Check if format is supported
		if ( ! $engine->supports_format( $format ) ) {
			return new \WP_Error(
				'format_not_supported',
				sprintf(
					/* translators: %s: Format name */
					__( '%s format is not supported on this server.', 'optipress' ),
					strtoupper( $format )
				)
			);
		}

		// Calculate original file sizes
		$original_size = filesize( $file_path );
		$original_total_size = $original_size;

		// Get metadata for image sizes
		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Convert the full-size image
		$converted_full = $this->convert_image( $file_path, $format, $engine, $attachment_id );

		if ( ! $converted_full ) {
			return new \WP_Error( 'conversion_failed', __( 'Image conversion failed.', 'optipress' ) );
		}

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
					$original_total_size += filesize( $size_path );

					$converted = $this->convert_image( $size_path, $format, $engine, $attachment_id );
					if ( $converted ) {
						$converted_sizes[] = $size_name;
					}
				}
			}
		}

		// Calculate converted total size
		$converted_total_size = 0;
		$path_info = pathinfo( $file_path );
		$converted_path = trailingslashit( $path_info['dirname'] ) . $path_info['filename'] . '.' . $format;

		if ( file_exists( $converted_path ) ) {
			$converted_total_size += filesize( $converted_path );
		}

		// Add size of all converted image sizes
		if ( ! empty( $converted_sizes ) && isset( $metadata['sizes'] ) ) {
			$upload_dir = dirname( $file_path );

			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( ! isset( $size_data['file'] ) || ! in_array( $size_name, $converted_sizes, true ) ) {
					continue;
				}

				$size_path_info = pathinfo( $size_data['file'] );
				$converted_size_path = trailingslashit( $upload_dir ) . $size_path_info['filename'] . '.' . $format;

				if ( file_exists( $converted_size_path ) ) {
					$converted_total_size += filesize( $converted_size_path );
				}
			}
		}

		// Calculate savings
		$bytes_saved = $original_total_size - $converted_total_size;
		$percent_saved = $original_total_size > 0 ? ( $bytes_saved / $original_total_size ) * 100 : 0;

		// Store conversion metadata
		update_post_meta( $attachment_id, '_optipress_converted', true );
		update_post_meta( $attachment_id, '_optipress_format', $format );
		update_post_meta( $attachment_id, '_optipress_engine', $engine->get_name() );
		update_post_meta( $attachment_id, '_optipress_converted_sizes', $converted_sizes );
		update_post_meta( $attachment_id, '_optipress_conversion_date', current_time( 'mysql' ) );
		update_post_meta( $attachment_id, '_optipress_original_size', $original_total_size );
		update_post_meta( $attachment_id, '_optipress_converted_size', $converted_total_size );
		update_post_meta( $attachment_id, '_optipress_bytes_saved', $bytes_saved );
		update_post_meta( $attachment_id, '_optipress_percent_saved', round( $percent_saved, 2 ) );

		// Update WordPress attachment metadata to refresh cache and URLs
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Clear WordPress object cache for this attachment
		clean_attachment_cache( $attachment_id );

		return true;
	}

	/**
	 * Revert a single attachment to original
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function revert_single_attachment( $attachment_id ) {
		// Check if attachment is converted
		if ( ! $this->is_converted( $attachment_id ) ) {
			return new \WP_Error( 'not_converted', __( 'Image is not converted.', 'optipress' ) );
		}

		// Get file path
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Image file not found.', 'optipress' ) );
		}

		// Get format
		$format = get_post_meta( $attachment_id, '_optipress_format', true );
		if ( empty( $format ) ) {
			return new \WP_Error( 'no_format', __( 'Conversion format not found.', 'optipress' ) );
		}

		// Get metadata for image sizes
		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Delete converted full-size image
		$path_info = pathinfo( $file_path );
		$converted_path = trailingslashit( $path_info['dirname'] ) . $path_info['filename'] . '.' . $format;

		if ( file_exists( $converted_path ) ) {
			@unlink( $converted_path );
		}

		// Delete all converted image sizes
		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$upload_dir = dirname( $file_path );

			foreach ( $metadata['sizes'] as $size_data ) {
				if ( ! isset( $size_data['file'] ) ) {
					continue;
				}

				$size_path_info = pathinfo( $size_data['file'] );
				$converted_size_path = trailingslashit( $upload_dir ) . $size_path_info['filename'] . '.' . $format;

				if ( file_exists( $converted_size_path ) ) {
					@unlink( $converted_size_path );
				}
			}
		}

		// Remove all conversion metadata
		delete_post_meta( $attachment_id, '_optipress_converted' );
		delete_post_meta( $attachment_id, '_optipress_format' );
		delete_post_meta( $attachment_id, '_optipress_engine' );
		delete_post_meta( $attachment_id, '_optipress_converted_sizes' );
		delete_post_meta( $attachment_id, '_optipress_conversion_date' );
		delete_post_meta( $attachment_id, '_optipress_original_size' );
		delete_post_meta( $attachment_id, '_optipress_converted_size' );
		delete_post_meta( $attachment_id, '_optipress_bytes_saved' );
		delete_post_meta( $attachment_id, '_optipress_percent_saved' );

		// Update WordPress attachment metadata to refresh cache and URLs
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Clear WordPress object cache for this attachment
		clean_attachment_cache( $attachment_id );

		return true;
	}

	/**
	 * AJAX handler to check conversion status
	 */
	public function ajax_check_conversion_status() {
		// Verify nonce
		check_ajax_referer( 'optipress_upload', 'nonce' );

		// Get attachment ID
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'optipress' ) ) );
		}

		// Check if currently converting
		$is_converting = get_post_meta( $attachment_id, '_optipress_converting', true );

		if ( $is_converting ) {
			// Conversion in progress
			wp_send_json_success( array(
				'status'  => 'processing',
				'message' => __( 'Converting image...', 'optipress' ),
			) );
		}

		// Check if conversion is complete
		$is_converted = $this->is_converted( $attachment_id );
		$conversion_info = $this->get_conversion_info( $attachment_id );

		if ( $is_converted && $conversion_info ) {
			// Get savings info
			$bytes_saved = get_post_meta( $attachment_id, '_optipress_bytes_saved', true );
			$percent_saved = get_post_meta( $attachment_id, '_optipress_percent_saved', true );
			$format = $conversion_info['format'];

			wp_send_json_success( array(
				'status'        => 'completed',
				'format'        => strtoupper( $format ),
				'bytes_saved'   => $bytes_saved ? absint( $bytes_saved ) : 0,
				'percent_saved' => $percent_saved ? floatval( $percent_saved ) : 0,
				'message'       => sprintf(
					/* translators: 1: Format name, 2: Percentage saved */
					__( 'Converted to %1$s - Saved %2$s%%', 'optipress' ),
					strtoupper( $format ),
					number_format( abs( $percent_saved ), 1 )
				),
			) );
		} else {
			// Not converted (probably not a convertible image type)
			wp_send_json_success( array(
				'status'  => 'not_applicable',
				'message' => __( 'Image not converted', 'optipress' ),
			) );
		}
	}

	/**
	 * Display conversion summary notice
	 */
	public function display_conversion_summary() {
		// Only on media library pages
		$screen = get_current_screen();
		if ( ! $screen || 'upload' !== $screen->id ) {
			return;
		}

		// Check for conversion summary transient
		$summary = get_transient( 'optipress_conversion_summary_' . get_current_user_id() );

		if ( ! $summary ) {
			return;
		}

		// Delete transient so it only shows once
		delete_transient( 'optipress_conversion_summary_' . get_current_user_id() );

		// Display notice
		$total = isset( $summary['total'] ) ? $summary['total'] : 0;
		$converted = isset( $summary['converted'] ) ? $summary['converted'] : 0;
		$bytes_saved = isset( $summary['bytes_saved'] ) ? $summary['bytes_saved'] : 0;

		if ( $converted > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'OptiPress:', 'optipress' ),
				sprintf(
					/* translators: 1: Number of images converted, 2: Total images uploaded, 3: Space saved */
					esc_html__( 'Successfully optimized %1$d of %2$d uploaded images, saving %3$s.', 'optipress' ),
					$converted,
					$total,
					size_format( $bytes_saved )
				)
			);
		}
	}

	/**
	 * Track conversion in queue for summary notice
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $metadata      Attachment metadata.
	 */
	public function track_conversion_for_summary( $attachment_id, $metadata ) {
		// Get or create summary transient
		$summary = get_transient( 'optipress_conversion_summary_' . get_current_user_id() );

		if ( ! $summary ) {
			$summary = array(
				'total'       => 0,
				'converted'   => 0,
				'bytes_saved' => 0,
			);
		}

		$summary['total']++;

		// Check if conversion was successful
		if ( $this->is_converted( $attachment_id ) ) {
			$summary['converted']++;
			$bytes_saved = get_post_meta( $attachment_id, '_optipress_bytes_saved', true );
			if ( $bytes_saved > 0 ) {
				$summary['bytes_saved'] += $bytes_saved;
			}
		}

		// Store for 5 minutes
		set_transient( 'optipress_conversion_summary_' . get_current_user_id(), $summary, 300 );
	}
}