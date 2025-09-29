<?php
/**
 * SVG Sanitizer Class
 *
 * Handles secure SVG upload and sanitization.
 *
 * @package OptiPress
 */

namespace OptiPress;

use enshrined\svgSanitize\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * SVG_Sanitizer class
 *
 * Enables secure SVG uploads with server-side sanitization.
 * NEVER stores or serves unsanitized SVG files.
 */
class SVG_Sanitizer {

	/**
	 * Singleton instance
	 *
	 * @var SVG_Sanitizer
	 */
	private static $instance = null;

	/**
	 * Plugin options
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Sanitizer instance
	 *
	 * @var Sanitizer
	 */
	private $sanitizer = null;

	/**
	 * Get singleton instance
	 *
	 * @return SVG_Sanitizer
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

		// Only enable SVG support if explicitly enabled in settings
		if ( $this->is_svg_enabled() ) {
			$this->init_hooks();
		}
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Enable SVG MIME type
		add_filter( 'upload_mimes', array( $this, 'enable_svg_mime_type' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check_svg_filetype' ), 10, 4 );

		// Sanitize SVG on upload
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'validate_svg_upload' ) );
		add_filter( 'wp_handle_upload', array( $this, 'sanitize_svg_upload' ), 10, 2 );

		// Fix SVG display in media library
		add_action( 'admin_head', array( $this, 'fix_svg_display' ) );
	}

	/**
	 * Initialize sanitizer instance
	 *
	 * @return Sanitizer
	 */
	private function get_sanitizer() {
		if ( null === $this->sanitizer ) {
			$this->sanitizer = new Sanitizer();
			$this->sanitizer->removeRemoteReferences( true );
			$this->sanitizer->minify( true );
		}

		return $this->sanitizer;
	}

	/**
	 * Enable SVG MIME type for uploads
	 *
	 * @param array $mimes Allowed MIME types.
	 * @return array Modified MIME types.
	 */
	public function enable_svg_mime_type( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';

		return $mimes;
	}

	/**
	 * Check SVG filetype and extension
	 *
	 * @param array  $data     File data.
	 * @param string $file     File path.
	 * @param string $filename File name.
	 * @param array  $mimes    Allowed MIME types.
	 * @return array Modified file data.
	 */
	public function check_svg_filetype( $data, $file, $filename, $mimes ) {
		$filetype = wp_check_filetype( $filename, $mimes );

		if ( 'svg' === $filetype['ext'] ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}

		return $data;
	}

	/**
	 * Validate SVG upload before processing
	 *
	 * Checks file size and basic requirements.
	 *
	 * @param array $file Upload file data.
	 * @return array Modified file data or WP_Error on failure.
	 */
	public function validate_svg_upload( $file ) {
		// Only process SVG files
		if ( 'image/svg+xml' !== $file['type'] ) {
			return $file;
		}

		// Check file size (default max: 2MB)
		$max_size = apply_filters( 'optipress_svg_max_size', 2 * MB_IN_BYTES );

		if ( $file['size'] > $max_size ) {
			$file['error'] = sprintf(
				/* translators: %s: Maximum file size */
				__( 'SVG file is too large. Maximum size: %s', 'optipress' ),
				size_format( $max_size )
			);
		}

		return $file;
	}

	/**
	 * Sanitize SVG file on upload
	 *
	 * CRITICAL: This is the authoritative sanitization point.
	 * Never stores or serves the original uploaded file.
	 *
	 * @param array  $upload Upload data.
	 * @param string $context Upload context.
	 * @return array Modified upload data or WP_Error on failure.
	 */
	public function sanitize_svg_upload( $upload, $context ) {
		// Only process SVG files
		if ( 'image/svg+xml' !== $upload['type'] ) {
			return $upload;
		}

		$file_path = $upload['file'];

		// Read file contents
		$svg_content = file_get_contents( $file_path );

		if ( false === $svg_content ) {
			return $this->handle_sanitization_error( $upload, __( 'Unable to read SVG file.', 'optipress' ) );
		}

		// Sanitize with safe XML parsing
		$sanitized = $this->sanitize_svg_content( $svg_content );

		if ( false === $sanitized ) {
			return $this->handle_sanitization_error( $upload, __( 'SVG sanitization failed. File may contain malicious content.', 'optipress' ) );
		}

		// Overwrite file with sanitized version
		$write_result = file_put_contents( $file_path, $sanitized );

		if ( false === $write_result ) {
			return $this->handle_sanitization_error( $upload, __( 'Unable to save sanitized SVG file.', 'optipress' ) );
		}

		// Log successful sanitization
		$this->log_security_event( 'svg_sanitized', $file_path, 'SVG file sanitized successfully.' );

		return $upload;
	}

	/**
	 * Sanitize SVG content
	 *
	 * Uses enshrined/svg-sanitize with additional hardening.
	 *
	 * @param string $svg_content Raw SVG content.
	 * @return string|false Sanitized SVG or false on failure.
	 */
	private function sanitize_svg_content( $svg_content ) {
		// Set safe libxml flags to prevent XXE attacks
		$prev_use_errors = libxml_use_internal_errors( true );
		$prev_entity_loader = libxml_disable_entity_loader( true );

		try {
			// Get sanitizer instance
			$sanitizer = $this->get_sanitizer();

			// Run primary sanitization
			$sanitized = $sanitizer->sanitize( $svg_content );

			if ( false === $sanitized || empty( $sanitized ) ) {
				return false;
			}

			// Additional hardening: Remove dangerous elements that might slip through
			$sanitized = $this->apply_additional_hardening( $sanitized );

			// Validate result is valid XML
			$dom = new \DOMDocument();
			$dom->recover = true;

			if ( ! @$dom->loadXML( $sanitized ) ) {
				return false;
			}

			return $sanitized;

		} catch ( \Exception $e ) {
			$this->log_security_event( 'svg_sanitization_exception', '', $e->getMessage() );
			return false;
		} finally {
			// Restore libxml settings
			libxml_use_internal_errors( $prev_use_errors );
			libxml_disable_entity_loader( $prev_entity_loader );
		}
	}

	/**
	 * Apply additional regex-based hardening
	 *
	 * Extra security layer to remove dangerous patterns.
	 *
	 * @param string $svg Sanitized SVG content.
	 * @return string Further hardened SVG content.
	 */
	private function apply_additional_hardening( $svg ) {
		// Remove foreignObject tags (can contain arbitrary HTML)
		$svg = preg_replace( '/<foreignObject[^>]*>.*?<\/foreignObject>/is', '', $svg );

		// Remove event handlers (onclick, onload, etc.)
		$svg = preg_replace( '/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg );

		// Remove javascript: protocol
		$svg = preg_replace( '/javascript\s*:/i', '', $svg );

		// Remove data: URIs (except for safe data:image types)
		$svg = preg_replace( '/data:(?!image\/(?:png|jpg|jpeg|gif|webp|svg\+xml))[^,]*,/i', '', $svg );

		return $svg;
	}

	/**
	 * Handle sanitization error
	 *
	 * @param array  $upload  Upload data.
	 * @param string $message Error message.
	 * @return array Upload data with error.
	 */
	private function handle_sanitization_error( $upload, $message ) {
		// Delete the uploaded file for security
		if ( isset( $upload['file'] ) && file_exists( $upload['file'] ) ) {
			wp_delete_file( $upload['file'] );
		}

		// Log security event
		$this->log_security_event( 'svg_sanitization_failed', $upload['file'], $message );

		// Return error
		$upload['error'] = $message;

		return $upload;
	}

	/**
	 * Log security event
	 *
	 * @param string $event_type Event type.
	 * @param string $file_path  File path.
	 * @param string $message    Log message.
	 */
	private function log_security_event( $event_type, $file_path, $message ) {
		// Log to WordPress debug log if enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log(
				sprintf(
					'OptiPress SVG Security [%s]: %s | File: %s',
					$event_type,
					$message,
					$file_path
				)
			);
		}

		// Store in options for admin review (keep last 100 events)
		$security_log = get_option( 'optipress_security_log', array() );

		$security_log[] = array(
			'type'    => $event_type,
			'file'    => basename( $file_path ),
			'message' => $message,
			'time'    => current_time( 'mysql' ),
			'user'    => get_current_user_id(),
		);

		// Keep only last 100 events
		if ( count( $security_log ) > 100 ) {
			$security_log = array_slice( $security_log, -100 );
		}

		update_option( 'optipress_security_log', $security_log );
	}

	/**
	 * Fix SVG display in media library
	 *
	 * Adds CSS to display SVG thumbnails properly.
	 */
	public function fix_svg_display() {
		?>
		<style>
			table.media .column-title .media-icon img[src$=".svg"],
			.attachment-info .thumbnail img[src$=".svg"],
			.media-modal-content .attachment-preview .thumbnail-image[src$=".svg"] {
				width: 100% !important;
				height: auto !important;
			}
		</style>
		<?php
	}

	/**
	 * Generate rasterized thumbnail for SVG
	 *
	 * Creates a PNG preview for better compatibility with media library.
	 * Requires Imagick extension.
	 *
	 * @param string $svg_path Path to SVG file.
	 * @return string|false Path to generated thumbnail or false on failure.
	 */
	public function generate_svg_thumbnail( $svg_path ) {
		// Check if Imagick is available
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			return false;
		}

		try {
			$imagick = new \Imagick();

			// Set background to transparent
			$imagick->setBackgroundColor( new \ImagickPixel( 'transparent' ) );

			// Read SVG
			if ( ! $imagick->readImage( $svg_path ) ) {
				return false;
			}

			// Set format to PNG
			$imagick->setImageFormat( 'png' );

			// Resize to reasonable thumbnail size (300px width, maintain aspect ratio)
			$imagick->thumbnailImage( 300, 0 );

			// Generate thumbnail path
			$path_info      = pathinfo( $svg_path );
			$thumbnail_path = trailingslashit( $path_info['dirname'] ) . $path_info['filename'] . '-thumbnail.png';

			// Write thumbnail
			$result = $imagick->writeImage( $thumbnail_path );

			// Clean up
			$imagick->clear();
			$imagick->destroy();

			return $result ? $thumbnail_path : false;

		} catch ( \Exception $e ) {
			$this->log_security_event( 'svg_thumbnail_failed', $svg_path, $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check if SVG support is enabled
	 *
	 * @return bool Whether SVG support is enabled.
	 */
	private function is_svg_enabled() {
		return isset( $this->options['svg_enabled'] ) && true === $this->options['svg_enabled'];
	}

	/**
	 * Batch sanitize existing SVG files
	 *
	 * Re-sanitizes SVG files already in media library.
	 * Used for batch processing in admin.
	 *
	 * @param int $limit Number of files to process per batch.
	 * @param int $offset Offset for pagination.
	 * @return array {
	 *     Batch processing results.
	 *
	 *     @type int   $processed   Number of files processed.
	 *     @type int   $succeeded   Number of successful sanitizations.
	 *     @type int   $failed      Number of failed sanitizations.
	 *     @type array $errors      Array of error messages.
	 * }
	 */
	public function batch_sanitize( $limit = 10, $offset = 0 ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image/svg+xml',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'fields'         => 'ids',
		);

		$attachments = get_posts( $args );

		$results = array(
			'processed' => 0,
			'succeeded' => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		foreach ( $attachments as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$results['failed']++;
				$results['errors'][] = sprintf(
					/* translators: %d: Attachment ID */
					__( 'File not found for attachment ID %d', 'optipress' ),
					$attachment_id
				);
				continue;
			}

			// Read and sanitize
			$svg_content = file_get_contents( $file_path );

			if ( false === $svg_content ) {
				$results['failed']++;
				continue;
			}

			$sanitized = $this->sanitize_svg_content( $svg_content );

			if ( false === $sanitized ) {
				$results['failed']++;
				$results['errors'][] = sprintf(
					/* translators: %s: File name */
					__( 'Sanitization failed for %s', 'optipress' ),
					basename( $file_path )
				);
				continue;
			}

			// Overwrite with sanitized version
			$write_result = file_put_contents( $file_path, $sanitized );

			if ( false === $write_result ) {
				$results['failed']++;
				continue;
			}

			$results['succeeded']++;
			$results['processed']++;
		}

		return $results;
	}

	/**
	 * Get security log
	 *
	 * @param int $limit Number of log entries to retrieve.
	 * @return array Security log entries.
	 */
	public function get_security_log( $limit = 50 ) {
		$log = get_option( 'optipress_security_log', array() );

		if ( $limit > 0 && count( $log ) > $limit ) {
			return array_slice( $log, -$limit );
		}

		return $log;
	}
}