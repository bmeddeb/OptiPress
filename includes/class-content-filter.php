<?php
/**
 * Content Filter Class
 *
 * Handles front-end delivery of optimized images by filtering post content.
 * Uses WP_HTML_Tag_Processor (WP 6.2+) for safe HTML manipulation.
 *
 * This class operates at the HTML output level, processing rendered content
 * to catch images that weren't processed by WordPress core image functions.
 *
 * DELIVERY PATH PRECEDENCE:
 * 1. Image_Converter (class-image-converter.php): Filters WordPress API functions
 *    - Handles images served via wp_get_attachment_url(), the_post_thumbnail(), etc.
 *    - First line of defense for properly coded themes/plugins
 *
 * 2. Content_Filter (this class): Filters HTML content output
 *    - Handles hardcoded image URLs in post content, widgets, etc.
 *    - Safety net for images that bypass WordPress image functions
 *    - Processes: the_content, post_thumbnail_html, get_avatar, widget_text
 *
 * DOUBLE PROCESSING GUARD:
 * - If an image is already converted by Image_Converter, Content_Filter detects
 *   the converted URL format and skips reprocessing
 * - Uses file_exists check to verify converted file availability
 * - Static cache prevents repeated filesystem checks on same URL
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
	 * Static cache for file existence checks
	 *
	 * @var array
	 */
	private static $file_cache = array();

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
	 * Implements format fallback: AVIF → WebP → none
	 *
	 * @return string|false Format (webp/avif) or false if not supported.
	 */
	private function get_browser_supported_format() {
		// Get configured format
		$configured_format = isset( $this->options['format'] ) ? $this->options['format'] : 'webp';

		// Check Accept header
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';

		$supports_avif = strpos( $accept, 'image/avif' ) !== false;
		$supports_webp = strpos( $accept, 'image/webp' ) !== false;

		// Determine format with fallback logic
		$format = false;

		if ( 'avif' === $configured_format ) {
			// Prefer AVIF if configured and supported
			if ( $supports_avif ) {
				$format = 'avif';
			} elseif ( $supports_webp ) {
				// Fallback to WebP if AVIF not supported
				$format = 'webp';
			}
		} elseif ( 'webp' === $configured_format ) {
			// Use WebP if configured and supported
			if ( $supports_webp ) {
				$format = 'webp';
			}
		}

		/**
		 * Filter the detected client format.
		 *
		 * Useful for proxy/CDN scenarios where Accept header may not be reliable.
		 *
		 * @param string|false $format           Detected format or false.
		 * @param string       $configured_format Configured format from settings.
		 * @param string       $accept            HTTP Accept header.
		 */
		return apply_filters( 'optipress_client_format', $format, $configured_format, $accept );
	}

	/**
	 * Replace image URLs in content using WP_HTML_Tag_Processor
	 *
	 * @param string $content Content with images.
	 * @param string $format  Target format (webp/avif).
	 * @return string Content with replaced URLs.
	 */
	private function replace_image_urls( $content, $format ) {
		// Use WP_HTML_Tag_Processor for safe HTML manipulation (WP 6.2+)
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			// Fallback to regex for older WP versions (shouldn't happen with WP 6.7+ requirement)
			return $this->replace_image_urls_regex( $content, $format );
		}

		$processor = new \WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag( 'img' ) ) {
			// Process src attribute
			$src = $processor->get_attribute( 'src' );

			if ( $src && $this->should_optimize_image( $src ) ) {
				$optimized_src = $this->get_optimized_url( $src, $format );

				if ( $this->file_exists_from_url( $optimized_src ) ) {
					$processor->set_attribute( 'src', $optimized_src );
				}
			}

			// Process srcset attribute
			$srcset = $processor->get_attribute( 'srcset' );

			if ( $srcset ) {
				$optimized_srcset = $this->convert_srcset( $srcset, $format );

				if ( $optimized_srcset !== $srcset ) {
					$processor->set_attribute( 'srcset', $optimized_srcset );
				}
			}
		}

		return $processor->get_updated_html();
	}

	/**
	 * Fallback regex-based image URL replacement
	 *
	 * Used only if WP_HTML_Tag_Processor is not available.
	 *
	 * @param string $content Content with images.
	 * @param string $format  Target format.
	 * @return string Content with replaced URLs.
	 */
	private function replace_image_urls_regex( $content, $format ) {
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
	 * Process individual img tag (regex fallback)
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

		// Check if should optimize
		if ( ! $this->should_optimize_image( $original_src ) ) {
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
	 * Check if image should be optimized
	 *
	 * @param string $url Image URL.
	 * @return bool True if should optimize.
	 */
	private function should_optimize_image( $url ) {
		return $this->is_local_upload( $url ) && $this->is_convertible_image( $url );
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

			// Check if should optimize
			if ( $this->should_optimize_image( $url ) ) {
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
	 * Replace images with picture elements using WP_HTML_Tag_Processor
	 *
	 * @param string $content Content with images.
	 * @param string $format  Target format.
	 * @return string Content with picture elements.
	 */
	private function replace_with_picture_elements( $content, $format ) {
		// Use WP_HTML_Tag_Processor for safe HTML manipulation
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			// Fallback to regex for older WP versions
			return $this->replace_with_picture_elements_regex( $content, $format );
		}

		$processor = new \WP_HTML_Tag_Processor( $content );
		$replacements = array();

		// First pass: collect all img tags that need replacement
		while ( $processor->next_tag( 'img' ) ) {
			$src = $processor->get_attribute( 'src' );

			if ( ! $src || ! $this->should_optimize_image( $src ) ) {
				continue;
			}

			$optimized_src = $this->get_optimized_url( $src, $format );

			if ( ! $this->file_exists_from_url( $optimized_src ) ) {
				continue;
			}

			// Get the full img tag
			$img_tag = $processor->get_updated_html();
			$start = $processor->get_token_start();
			$length = $processor->get_token_length();

			// Store replacement
			$replacements[] = array(
				'start'  => $start,
				'length' => $length,
				'old'    => substr( $img_tag, $start, $length ),
				'new'    => $this->create_picture_element_from_attributes( $processor, $format ),
			);
		}

		// Second pass: apply replacements (in reverse order to maintain positions)
		$modified_content = $content;
		foreach ( array_reverse( $replacements ) as $replacement ) {
			$modified_content = substr_replace(
				$modified_content,
				$replacement['new'],
				$replacement['start'],
				$replacement['length']
			);
		}

		return $modified_content;
	}

	/**
	 * Create picture element from WP_HTML_Tag_Processor attributes
	 *
	 * @param \WP_HTML_Tag_Processor $processor Tag processor at img tag.
	 * @param string                 $format   Target format.
	 * @return string Picture element HTML.
	 */
	private function create_picture_element_from_attributes( $processor, $format ) {
		$src = $processor->get_attribute( 'src' );
		$srcset = $processor->get_attribute( 'srcset' );

		$optimized_src = $this->get_optimized_url( $src, $format );
		$mime_type = 'avif' === $format ? 'image/avif' : 'image/webp';

		// Build picture element
		$picture = '<picture>';

		if ( $srcset ) {
			$optimized_srcset = $this->convert_srcset( $srcset, $format );
			$picture .= sprintf( '<source srcset="%s" type="%s">', esc_attr( $optimized_srcset ), esc_attr( $mime_type ) );
		} else {
			$picture .= sprintf( '<source srcset="%s" type="%s">', esc_url( $optimized_src ), esc_attr( $mime_type ) );
		}

		// Rebuild img tag from processor
		$img_attributes = array();
		foreach ( $processor->get_attribute_names() as $attr_name ) {
			$attr_value = $processor->get_attribute( $attr_name );
			if ( null !== $attr_value ) {
				$img_attributes[] = sprintf( '%s="%s"', $attr_name, esc_attr( $attr_value ) );
			}
		}

		$picture .= '<img ' . implode( ' ', $img_attributes ) . '>';
		$picture .= '</picture>';

		return $picture;
	}

	/**
	 * Fallback regex-based picture element replacement
	 *
	 * @param string $content Content with images.
	 * @param string $format  Target format.
	 * @return string Content with picture elements.
	 */
	private function replace_with_picture_elements_regex( $content, $format ) {
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
	 * Create picture element from img tag (regex fallback)
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

		// Check if should optimize
		if ( ! $this->should_optimize_image( $original_src ) ) {
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
	 * Check if file exists from URL with static caching
	 *
	 * @param string $url File URL.
	 * @return bool True if file exists.
	 */
	private function file_exists_from_url( $url ) {
		// Check static cache first
		if ( isset( self::$file_cache[ $url ] ) ) {
			return self::$file_cache[ $url ];
		}

		$file_path = $this->url_to_path( $url );
		$exists = $file_path && file_exists( $file_path );

		// Cache result
		self::$file_cache[ $url ] = $exists;

		return $exists;
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