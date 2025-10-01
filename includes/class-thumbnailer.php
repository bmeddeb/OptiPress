<?php
/**
 * Thumbnailer
 *
 * Owns thumbnail generation:
 *  - Optionally disables WP core intermediate sizes.
 *  - Creates sizes with Imagick directly, from the *current attachment file* (your preview).
 *  - Writes standard metadata so WP srcset/UI keep working.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * Thumbnailer class
 *
 * Filters:
 *  - 'optipress_thumbnailer_enabled' (bool)
 *  - 'optipress_thumbnailer_disable_core' (bool)
 *  - 'optipress_thumbnailer_profiles' (array size specs)
 *  - 'optipress_thumbnailer_quality' (int)
 */
final class Thumbnailer {
	/**
	 * Singleton instance
	 *
	 * @var Thumbnailer|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Thumbnailer
	 */
	public static function get_instance() {
		return self::$instance ?? ( self::$instance = new self() );
	}

	/**
	 * Constructor - registers hooks
	 */
	private function __construct() {
		$enabled = apply_filters( 'optipress_thumbnailer_enabled', true );
		if ( ! $enabled ) {
			return;
		}

		/**
		 * Stop WP from creating its own sizes.
		 * You can make this conditional if you only want to take over for certain mimes/exts.
		 */
		$disable_core = apply_filters( 'optipress_thumbnailer_disable_core', true );
		if ( $disable_core ) {
			add_filter( 'intermediate_image_sizes_advanced', array( $this, 'disable_core_sizes' ), 10, 3 );
		}

		// After WP gathers base metadata, we inject our sizes.
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'inject_custom_sizes' ), 15, 2 );
	}

	/**
	 * Disable core WordPress thumbnail generation
	 *
	 * @param array $sizes        Array of image sizes.
	 * @param array $metadata     Image metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Empty array to prevent core generation.
	 */
	public function disable_core_sizes( $sizes, $metadata, $attachment_id ) {
		// Returning [] prevents core from generating thumbnails.
		return array();
	}

	/**
	 * Returns an array of size specs. Keys are size names, values are arrays:
	 * ['width' => int, 'height' => int, 'crop' => bool]
	 *
	 * @return array Size specifications.
	 */
	private function get_size_profile() {
		$defaults = array(
			'thumbnail'     => array( 'width' => 150,  'height' => 150,  'crop' => true ),
			'medium'        => array( 'width' => 300,  'height' => 0,    'crop' => false ),
			'medium_large'  => array( 'width' => 768,  'height' => 0,    'crop' => false ),
			'large'         => array( 'width' => 1024, 'height' => 0,    'crop' => false ),
			// Add any custom sizes you like:
			'xl'            => array( 'width' => 1600, 'height' => 0,    'crop' => false ),
		);
		return apply_filters( 'optipress_thumbnailer_profiles', $defaults );
	}

	/**
	 * Inject custom thumbnail sizes into attachment metadata
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Modified metadata with custom sizes.
	 */
	public function inject_custom_sizes( $metadata, $attachment_id ) {
		// Only operate on images (and only if we have Imagick)
		if ( ! wp_attachment_is_image( $attachment_id ) || ! class_exists( '\Imagick' ) ) {
			return $metadata;
		}

		$src_abs = get_attached_file( $attachment_id, true ); // this should be your preview file
		if ( ! $src_abs || ! file_exists( $src_abs ) ) {
			return $metadata;
		}

		$upload_dir = wp_get_upload_dir();
		$basedir    = trailingslashit( $upload_dir['basedir'] );
		$baseurl    = trailingslashit( $upload_dir['baseurl'] );

		$pathinfo   = pathinfo( $src_abs );
		$dirname    = $pathinfo['dirname'];
		$filename   = $pathinfo['filename'];
		$ext        = strtolower( $pathinfo['extension'] ); // webp/avif/jpg expected

		$sizes = $this->get_size_profile();
		if ( ! is_array( $sizes ) || empty( $sizes ) ) {
			return $metadata;
		}

		$quality = (int) apply_filters( 'optipress_thumbnailer_quality', 82 );

		// Build/refresh sizes
		$metadata = is_array( $metadata ) ? $metadata : array();
		$metadata['sizes'] = $metadata['sizes'] ?? array();

		// Get global format setting for 'auto' mode
		$options = get_option( 'optipress_options', array() );
		$global_format = isset( $options['format'] ) ? $options['format'] : 'webp';
		$global_ext = ( 'avif' === $global_format ) ? 'avif' : 'webp';

		// Probe source preview once for base ext
		$source_ext = strtolower( $ext ); // ext of preview file (webp/avif/jpg/png)

		foreach ( $sizes as $name => $spec ) {
			$w = max( 0, (int) ( $spec['width']  ?? 0 ) );
			$h = max( 0, (int) ( $spec['height'] ?? 0 ) );
			$crop = (bool) ( $spec['crop'] ?? false );
			$fmt  = strtolower( (string) ( $spec['format'] ?? 'auto' ) );

			if ( 0 === $w && 0 === $h ) {
				continue; // nothing to do
			}

			// Decide output ext for this size
			if ( in_array( $fmt, array( 'avif', 'webp', 'jpeg', 'png' ), true ) ) {
				// Explicit format specified
				$target_ext = ( 'jpeg' === $fmt ) ? 'jpg' : $fmt;
			} elseif ( 'inherit' === $fmt ) {
				// Legacy 'inherit' - keep source format
				$target_ext = $source_ext;
			} else {
				// 'auto' or unrecognized - use global Image Optimization format
				$target_ext = $global_ext;
			}

			$dest_filename = sprintf( '%s-%s.%s', $filename, $this->suffix( $w, $h, $crop ), $target_ext );
			$dest_abs      = trailingslashit( $dirname ) . $dest_filename;

			try {
				$im = new \Imagick();
				$im->readImage( $src_abs );
				$im->autoOrient();

				// Set output format per size
				$im->setImageFormat( $target_ext );
				$im->setImageCompressionQuality( $quality );

				$orig_w = (int) $im->getImageWidth();
				$orig_h = (int) $im->getImageHeight();

				if ( $orig_w <= 0 || $orig_h <= 0 ) {
					throw new \RuntimeException( 'Source image has invalid geometry: ' . $orig_w . 'x' . $orig_h );
				}

				/**
				 * Compute positive target dimensions.
				 * Returns [$resize_w, $resize_h, $crop_x, $crop_y, $crop_w, $crop_h]
				 * If no crop: crop_x/y are 0 and crop_w/h equal resize_w/h.
				 */
				$calc = function ( $ow, $oh, $tw, $th, $do_crop ) {
					if ( $tw <= 0 && $th <= 0 ) {
						// No-op; caller will skip
						return array( 0, 0, 0, 0, 0, 0 );
					}

					if ( $do_crop && $tw > 0 && $th > 0 ) {
						// Cover: scale up to fill, then center-crop tw×th
						$scale = max( $tw / $ow, $th / $oh );
						$rw = max( 1, (int) ceil( $ow * $scale ) );
						$rh = max( 1, (int) ceil( $oh * $scale ) );
						// center crop box
						$cx = max( 0, (int) floor( ( $rw - $tw ) / 2 ) );
						$cy = max( 0, (int) floor( ( $rh - $th ) / 2 ) );
						return array( $rw, $rh, $cx, $cy, $tw, $th );
					}

					if ( $tw > 0 && $th > 0 ) {
						// Contain: fit inside box, keep aspect
						$scale = min( $tw / $ow, $th / $oh );
						$rw = max( 1, (int) floor( $ow * $scale ) );
						$rh = max( 1, (int) floor( $oh * $scale ) );
						return array( $rw, $rh, 0, 0, $rw, $rh );
					}

					if ( $tw > 0 ) {
						// Width-limited
						$scale = $tw / $ow;
						$rw = max( 1, $tw );
						$rh = max( 1, (int) round( $oh * $scale ) );
						return array( $rw, $rh, 0, 0, $rw, $rh );
					}

					// Height-limited
					$scale = $th / $oh;
					$rh = max( 1, $th );
					$rw = max( 1, (int) round( $ow * $scale ) );
					return array( $rw, $rh, 0, 0, $rw, $rh );
				};

				list( $rw, $rh, $cx, $cy, $cw, $ch ) = $calc( $orig_w, $orig_h, $w, $h, $crop );

				// Skip if nothing to do (both targets zero)
				if ( $rw <= 0 || $rh <= 0 ) {
					throw new \RuntimeException( 'Requested no-op resize (both width and height are zero).' );
				}

				// Debug logging (optional but helpful)
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log(
						sprintf(
							'[OptiPress Thumbnailer] %s: src=%dx%d -> resize=%dx%d crop=%s box=%dx%d@%d,%d fmt=%s',
							$name,
							$orig_w,
							$orig_h,
							$rw,
							$rh,
							$crop ? 'yes' : 'no',
							$cw,
							$ch,
							$cx,
							$cy,
							$target_ext
						)
					);
				}

				// Resize with positive integers only
				$im->resizeImage( $rw, $rh, \Imagick::FILTER_LANCZOS, 1 );

				// Crop if requested (cover path)
				if ( $crop && $cw > 0 && $ch > 0 && ( $rw !== $cw || $rh !== $ch ) ) {
					// For transparent formats, keep alpha background clean
					if ( in_array( $target_ext, array( 'webp', 'avif', 'png' ), true ) ) {
						$im->setImageBackgroundColor( 'transparent' );
					}
					$im->cropImage( $cw, $ch, max( 0, $cx ), max( 0, $cy ) );
					$im->setImagePage( 0, 0, 0, 0 ); // reset canvas
				}

				$im->stripImage(); // drop metadata in thumbs
				$im->writeImage( $dest_abs );
				$width  = (int) $im->getImageWidth();
				$height = (int) $im->getImageHeight();
				$im->clear();
				$im->destroy();

				// Record in WP's expected metadata format
				$metadata['sizes'][ $name ] = array(
					'file'      => basename( $dest_abs ),
					'width'     => $width,
					'height'    => $height,
					'mime-type' => $this->mime_from_ext( $target_ext ),
				);

			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( '[OptiPress Thumbnailer] Size "' . $name . '" failed: ' . $e->getMessage() );
				}
				// Skip this size; continue others
			}
		}

		// Ensure base width/height recorded (from the preview file)
		if ( empty( $metadata['width'] ) || empty( $metadata['height'] ) ) {
			try {
				$probe = new \Imagick( $src_abs );
				$metadata['width']  = $probe->getImageWidth();
				$metadata['height'] = $probe->getImageHeight();
				$probe->clear();
				$probe->destroy();
			} catch ( \Throwable $e ) {
				// ignore
			}
		}

		return $metadata;
	}

	/**
	 * Generate filename suffix for size
	 *
	 * @param int  $w    Width.
	 * @param int  $h    Height.
	 * @param bool $crop Whether to crop.
	 * @return string Suffix string.
	 */
	private function suffix( $w, $h, $crop ) {
		// e.g., 300x200-c or 768w
		if ( $w && $h ) {
			return $w . 'x' . $h . ( $crop ? '-c' : '' );
		}
		return $w ? ( $w . 'w' ) : ( $h . 'h' );
	}

	/**
	 * Get MIME type from file extension
	 *
	 * @param string $ext File extension.
	 * @return string MIME type.
	 */
	private function mime_from_ext( $ext ) {
		switch ( strtolower( $ext ) ) {
			case 'webp':
				return 'image/webp';
			case 'avif':
				return 'image/avif';
			case 'jpg':
			case 'jpeg':
				return 'image/jpeg';
			case 'png':
				return 'image/png';
			default:
				return 'image/' . strtolower( $ext ); // fallback
		}
	}
}
