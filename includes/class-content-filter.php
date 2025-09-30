<?php
/**
 * Content Filter Class
 *
 * Handles front-end delivery of optimized images by filtering post content.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * Content_Filter class
 *
 * Filters the_content to replace image URLs with optimized versions.
 * Works alongside WordPress image filters for comprehensive coverage.
 */
class Content_Filter {

	/**
	 * Singleton instance
	 *
	 * @var Content_Filter
	 */
	private static $instance = null;

	/**
	 * Plugin options
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Upload directory info
	 *
	 * @var array
	 */
	private $upload_dir = array();

	/**
	 * Get singleton instance
	 *
	 * @return Content_Filter
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
		$this->options    = get_option( 'optipress_options', array() );
		$this->upload_dir = wp_get_upload_dir();

		// Check if content filtering is enabled
		$enable_content_filter = isset( $this->options['enable_content_filter'] ) ? $this->options['enable_content_filter'] : true;

		if ( ! $enable_content_filter ) {
			return;
		}

		// Hook into content filters
		add_filter( 'the_content', array( $this, 'filter_content' ), 999 );
		add_filter( 'post_thumbnail_html', array( $this, 'filter_content' ), 999 );
		add_filter( 'get_avatar', array( $this, 'filter_content' ), 999 );
		add_filter( 'widget_text', array( $this, 'filter_content' ), 999 );
	}

	/**
	 * Filter content to replace images with optimized versions
	 *
	 * @param string $content Content to filter.
	 * @return string Filtered content.
	 */
	public function filter_content( $content ) {
		// Don't process if we're in admin or doing AJAX
		if ( is_admin() || wp_doing_ajax() ) {
			return $content;
		}

		// Check if browser supports WebP/AVIF
		$format = $this->get_browser_supported_format();

		if ( ! $format ) {
			return $content;
		}

		// Check if picture element is enabled
		$use_picture = isset( $this->options['use_picture_element'] ) ? $this->options['use_picture_element'] : false;

		if ( $use_picture ) {
			$content = $this->replace_with_picture_elements( $content, $format );
		} else {
			$content = $this->replace_image_urls( $content, $format );
		}

		return $content;
	}

	/**
	 * Get browser supported format based on Accept header
	 *
	 * @return string|false Format (webp/avif) or false if not supported.
	 */
	private function get_browser_supported_format() {
		// Get configured format
		$configured_format = isset( $this->options['format'] ) ? $this->options['format'] : 'webp';

		// Check Accept header
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? $_SERVER['HTTP_ACCEPT'] : '';

		// Check for AVIF support first (better compression)
		if ( 'avif' === $configured_format && strpos( $accept, 'image/avif' ) !== false ) {
			return 'avif';
		}

		// Check for WebP support
		if ( strpos( $accept, 'image/webp' ) !== false ) {
			return 'webp';
		}

		return false;
	}

	/**
	 * Replace image URLs in content
	 *
	 * @param string $content Content with images.
	 * @param string $format  Target format (webp/avif).
	 * @return string Content with replaced URLs.
	 */
	private function replace_image_urls( $content, $format ) {
		// Match all img tags
		$pattern = '/<img([^>]+)>/i';

		$content = preg_replace_callback(
			$pattern,
			function( $matches ) use ( $format ) {
				return $this->process_img_tag( $matches[0], $format );
			},
			$content
		);

		return $content;
	}

	/**
	 * Process individual img tag
	 *
	 * @param string $img_tag Original img tag.
	 * @param string $format  Target format.
	 * @return string Processed img tag.
	 */
	private function process_img_tag( $img_tag, $format ) {
		// Extract src attribute
		if ( ! preg_match( '/src=["\']([^"\']+)["\']/i', $img_tag, $src_match ) ) {
			return $img_tag;
		}

		$original_src = $src_match[1];

		// Check if this is a local image from uploads directory
		if ( ! $this->is_local_upload( $original_src ) ) {
			return $img_tag;
		}

		// Check if this is a convertible format (jpg, png)
		if ( ! $this->is_convertible_image( $original_src ) ) {
			return $img_tag;
		}

		// Generate optimized URL
		$optimized_src = $this->get_optimized_url( $original_src, $format );

		// Check if optimized file exists
		if ( ! $this->file_exists_from_url( $optimized_src ) ) {
			return $img_tag;
		}

		// Replace src
		$img_tag = str_replace( $original_src, $optimized_src, $img_tag );

		// Process srcset if present
		if ( preg_match( '/srcset=["\']([^"\']+)["\']/i', $img_tag, $srcset_match ) ) {
			$original_srcset = $srcset_match[1];
			$optimized_srcset = $this->convert_srcset( $original_srcset, $format );

			if ( $optimized_srcset !== $original_srcset ) {
				$img_tag = str_replace( $original_srcset, $optimized_srcset, $img_tag );
			}
		}

		return $img_tag;
	}

	/**
	 * Convert srcset attribute
	 *
	 * @param string $srcset Original srcset.
	 * @param string $format Target format.
	 * @return string Converted srcset.
	 */
	private function convert_srcset( $srcset, $format ) {
		$sources = explode( ',', $srcset );
		$converted_sources = array();

		foreach ( $sources as $source ) {
			$source = trim( $source );

			// Parse URL and descriptor (e.g., "image.jpg 1024w")
			$parts = preg_split( '/\s+/', $source );

			if ( empty( $parts[0] ) ) {
				continue;
			}

			$url = $parts[0];
			$descriptor = isset( $parts[1] ) ? ' ' . $parts[1] : '';

			// Check if local and convertible
			if ( $this->is_local_upload( $url ) && $this->is_convertible_image( $url ) ) {
				$optimized_url = $this->get_optimized_url( $url, $format );

				// Only use optimized version if file exists
				if ( $this->file_exists_from_url( $optimized_url ) ) {
					$converted_sources[] = $optimized_url . $descriptor;
					continue;
				}
			}

			// Keep original if conversion not available
			$converted_sources[] = $source;
		}

		return implode( ', ', $converted_sources );
	}

	/**
	 * Replace images with picture elements
	 *
	 * @param string $content Content with images.
	 * @param string $format  Target format.
	 * @return string Content with picture elements.
	 */
	private function replace_with_picture_elements( $content, $format ) {
		// Match all img tags
		$pattern = '/<img([^>]+)>/i';

		$content = preg_replace_callback(
			$pattern,
			function( $matches ) use ( $format ) {
				return $this->create_picture_element( $matches[0], $format );
			},
			$content
		);

		return $content;
	}

	/**
	 * Create picture element from img tag
	 *
	 * @param string $img_tag Original img tag.
	 * @param string $format  Target format.
	 * @return string Picture element or original img tag.
	 */
	private function create_picture_element( $img_tag, $format ) {
		// Extract src attribute
		if ( ! preg_match( '/src=["\']([^"\']+)["\']/i', $img_tag, $src_match ) ) {
			return $img_tag;
		}

		$original_src = $src_match[1];

		// Check if this is a local image from uploads directory
		if ( ! $this->is_local_upload( $original_src ) ) {
			return $img_tag;
		}

		// Check if this is a convertible format
		if ( ! $this->is_convertible_image( $original_src ) ) {
			return $img_tag;
		}

		// Generate optimized URL
		$optimized_src = $this->get_optimized_url( $original_src, $format );

		// Check if optimized file exists
		if ( ! $this->file_exists_from_url( $optimized_src ) ) {
			return $img_tag;
		}

		// Build picture element
		$mime_type = 'avif' === $format ? 'image/avif' : 'image/webp';

		$picture = '<picture>';
		$picture .= sprintf( '<source srcset="%s" type="%s">', esc_url( $optimized_src ), esc_attr( $mime_type ) );

		// Process srcset for source element if present
		if ( preg_match( '/srcset=["\']([^"\']+)["\']/i', $img_tag, $srcset_match ) ) {
			$optimized_srcset = $this->convert_srcset( $srcset_match[1], $format );
			$picture = '<picture>';
			$picture .= sprintf( '<source srcset="%s" type="%s">', esc_attr( $optimized_srcset ), esc_attr( $mime_type ) );
		}

		// Add original img as fallback
		$picture .= $img_tag;
		$picture .= '</picture>';

		return $picture;
	}

	/**
	 * Check if URL is from local uploads directory
	 *
	 * @param string $url URL to check.
	 * @return bool True if local upload.
	 */
	private function is_local_upload( $url ) {
		$upload_url = $this->upload_dir['baseurl'];
		return strpos( $url, $upload_url ) === 0;
	}

	/**
	 * Check if image is convertible format (jpg/png)
	 *
	 * @param string $url Image URL.
	 * @return bool True if convertible.
	 */
	private function is_convertible_image( $url ) {
		$extension = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
		return in_array( $extension, array( 'jpg', 'jpeg', 'png' ), true );
	}

	/**
	 * Get optimized URL for an image
	 *
	 * @param string $url    Original URL.
	 * @param string $format Target format.
	 * @return string Optimized URL.
	 */
	private function get_optimized_url( $url, $format ) {
		$pathinfo = pathinfo( $url );
		return $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.' . $format;
	}

	/**
	 * Check if file exists from URL
	 *
	 * @param string $url File URL.
	 * @return bool True if file exists.
	 */
	private function file_exists_from_url( $url ) {
		$file_path = $this->url_to_path( $url );
		return $file_path && file_exists( $file_path );
	}

	/**
	 * Convert URL to file path
	 *
	 * @param string $url File URL.
	 * @return string|false File path or false.
	 */
	private function url_to_path( $url ) {
		$upload_dir = $this->upload_dir['basedir'];
		$upload_url = $this->upload_dir['baseurl'];

		if ( strpos( $url, $upload_url ) !== 0 ) {
			return false;
		}

		$relative_path = str_replace( $upload_url, '', $url );
		return $upload_dir . $relative_path;
	}
}