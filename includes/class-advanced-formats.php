<?php
/**
 * Advanced Formats Handler
 *
 * Handles TIFF/PSD/RAW formats by generating flattened previews.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * Advanced_Formats class
 *
 * - Treats TIFF/PSD/RAW as displayable so metadata generation runs.
 * - On upload, flattens & converts first frame/layer to a preview (AVIF/WebP/JPEG).
 * - Preserves the original file on disk and stores it in attachment meta (original_file).
 * - Points the attachment to the preview so WordPress can generate normal sizes.
 */
final class Advanced_Formats {
	/**
	 * Singleton instance
	 *
	 * @var Advanced_Formats|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Advanced_Formats
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - registers hooks
	 */
	private function __construct() {
		// Allow turning this off without needing a UI (you can add a checkbox later).
		$enabled = apply_filters( 'optipress_enable_advanced_previews', true );
		$options = get_option( 'optipress_options' );
		if ( isset( $options['advanced_previews'] ) ) {
			$enabled = (bool) $options['advanced_previews'];
		}

		// Always register displayable + editor preference; preview generation only if enabled.
		add_filter( 'file_is_displayable_image', array( $this, 'mark_displayable' ), 10, 2 );
		add_filter( 'wp_image_editors', array( $this, 'prefer_imagick' ) );

		if ( $enabled ) {
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_preview_metadata' ), 10, 2 );
			add_action( 'admin_notices', array( $this, 'maybe_show_imagick_notice' ) );
		}
	}

	/**
	 * Extensions we treat as advanced still-images that need flattening/preview.
	 *
	 * @return array Array of file extensions.
	 */
	private function advanced_exts() {
		return array(
			// TIFF/PSD (widely supported by Imagick)
			'tif', 'tiff', 'psd',
			// RAW formats (support depends on build/delegates, e.g., libraw)
			'dng', 'arw', 'cr2', 'cr3', 'nef', 'orf', 'rw2', 'raf',
			// JPEG 2000 family (requires OpenJPEG delegate)
			'jp2', 'j2k', 'jpf', 'jpx', 'jpm',
			// HEIF/HEIC (requires libheif delegate)
			'heic', 'heif',
		);
	}

	/**
	 * Make WP consider these files "displayable" so metadata generation runs.
	 *
	 * @param bool   $result Whether the file is displayable.
	 * @param string $path   File path.
	 * @return bool
	 */
	public function mark_displayable( $result, $path ) {
		if ( $result ) {
			return true;
		}
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, $this->advanced_exts(), true ) ) {
			return true;
		}
		return $result;
	}

	/**
	 * Prefer Imagick if available (better for TIFF/PSD/RAW).
	 *
	 * @param array $editors List of image editor class names.
	 * @return array
	 */
	public function prefer_imagick( $editors ) {
		if ( class_exists( 'WP_Image_Editor_Imagick' ) ) {
			return array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' );
		}
		return $editors;
	}

	/**
	 * On upload, flatten & convert to preview, point attachment to it, and let WP make sizes.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function generate_preview_metadata( $metadata, $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return $metadata;
		}

		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $this->advanced_exts(), true ) ) {
			return $metadata; // not TIFF/PSD/RAW
		}

		if ( ! class_exists( 'Imagick' ) ) {
			// No Imagick → we can't flatten/preview; keep default behavior.
			return $metadata;
		}

		// Guard against very large files that might blow memory
		$file_size = @filesize( $file );
		/**
		 * Filter the maximum file size for advanced format preview generation.
		 *
		 * @param int $max_size Maximum file size in bytes. Default 209715200 (200MB).
		 */
		$max_size = apply_filters( 'optipress_advanced_max_filesize', 209715200 );
		if ( false !== $file_size && $file_size > $max_size ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf(
					'OptiPress: Skipping preview for %s - file too large (%s > %s)',
					basename( $file ),
					size_format( $file_size ),
					size_format( $max_size )
				) );
			}
			return $metadata; // skip preview on very large files
		}

		try {
			$im = new \Imagick();

			// Conservative resource limits; configurable via filter
			/**
			 * Filter Imagick resource limits for advanced format processing.
			 *
			 * @param array $limits Array with 'memory' and 'map' keys (in MB).
			 */
			$limits = apply_filters( 'optipress_imagick_limits', array( 'memory' => 256, 'map' => 256 ) );
			$im->setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, $limits['memory'] );
			$im->setResourceLimit( \Imagick::RESOURCETYPE_MAP, $limits['map'] );

			$im->readImage( $file );

			// First frame/layer only, then flatten (handles PSD layers & multi-page TIFF).
			if ( $im->getNumberImages() > 1 ) {
				$im->setIteratorIndex( 0 );
			}
			$im = $im->mergeImageLayers( \Imagick::LAYERMETHOD_FLATTEN );
			$im->autoOrient();

			// Pick target preview format based on editor support (AVIF → WebP → JPEG).
			$target_mime = 'image/jpeg';
			if ( function_exists( 'wp_image_editor_supports' ) && wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ) {
				$target_mime = 'image/avif';
			} elseif ( function_exists( 'wp_image_editor_supports' ) && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
				$target_mime = 'image/webp';
			}

			$preview_ext = ( $target_mime === 'image/avif' ) ? 'avif' : ( ( $target_mime === 'image/webp' ) ? 'webp' : 'jpg' );
			$base_dir    = dirname( $file );
			$base_name   = pathinfo( $file, PATHINFO_FILENAME );
			$preview     = trailingslashit( $base_dir ) . $base_name . '-preview.' . $preview_ext;

			// Encode preview
			if ( $target_mime === 'image/avif' ) {
				$im->setImageFormat( 'avif' );
				$im->setImageCompressionQuality( 60 );
			} elseif ( $target_mime === 'image/webp' ) {
				$im->setImageFormat( 'webp' );
				$im->setImageCompressionQuality( 70 );
			} else {
				$im->setImageFormat( 'jpeg' );
				$im->setImageCompressionQuality( 82 );
				$im->stripImage();
			}

			$im->writeImage( $preview );
			$im->clear();
			$im->destroy();

			// Store original path and repoint attachment to preview
			$meta = is_array( $metadata ) ? $metadata : array();
			$meta['original_file'] = _wp_relative_upload_path( $file );

			update_attached_file( $attachment_id, $preview );

			// Build all registered sizes from the preview
			$editor = wp_get_image_editor( $preview );
			if ( ! is_wp_error( $editor ) ) {
				$sizes_meta = $editor->multi_resize( wp_get_registered_image_subsizes() );

				$meta['file']  = _wp_relative_upload_path( $preview );
				$meta['sizes'] = array();
				if ( is_array( $sizes_meta ) ) {
					foreach ( $sizes_meta as $name => $size ) {
						if ( ! empty( $size['file'] ) ) {
							$meta['sizes'][ $name ] = $size;
						}
					}
				}

				$dim = $editor->get_size();
				if ( is_array( $dim ) ) {
					$meta['width']  = isset( $dim['width'] ) ? $dim['width'] : ( isset( $meta['width'] ) ? $meta['width'] : null );
					$meta['height'] = isset( $dim['height'] ) ? $dim['height'] : ( isset( $meta['height'] ) ? $meta['height'] : null );
				}
			}

			return $meta;

		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( 'OptiPress Advanced_Formats preview failed for ' . $file . ': ' . $e->getMessage() );
			}
			return $metadata; // Fail-safe: keep original metadata.
		}
	}

	/**
	 * Warn admins if Imagick is missing (uploads allowed, but no previews).
	 */
	public function maybe_show_imagick_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( class_exists( 'Imagick' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'OptiPress: Imagick is not available. TIFF/PSD/RAW uploads may be allowed, but preview/thumbnail generation will be skipped. Install/enable Imagick for full functionality.', 'optipress' );
		echo '</p></div>';
	}
}
