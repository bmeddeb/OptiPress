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

		foreach ( $sizes as $name => $spec ) {
			$w = max( 0, (int) ( $spec['width']  ?? 0 ) );
			$h = max( 0, (int) ( $spec['height'] ?? 0 ) );
			$crop = (bool) ( $spec['crop'] ?? false );

			if ( 0 === $w && 0 === $h ) {
				continue; // nothing to do
			}

			$dest_filename = sprintf( '%s-%s.%s', $filename, $this->suffix( $w, $h, $crop ), $ext );
			$dest_abs      = trailingslashit( $dirname ) . $dest_filename;

			try {
				$im = new \Imagick();
				$im->readImage( $src_abs );
				$im->autoOrient();

				// Set output format to match source preview
				$im->setImageFormat( $ext );
				$im->setImageCompressionQuality( $quality );

				$orig_w = $im->getImageWidth();
				$orig_h = $im->getImageHeight();

				if ( $crop && $w > 0 && $h > 0 ) {
					// Cover-like crop: scale to fill, then center-crop
					$ratio_src = $orig_w / max( 1, $orig_h );
					$ratio_tgt = $w / max( 1, $h );

					if ( $ratio_src > $ratio_tgt ) {
						// wider than target: fit height, then crop width
						$new_h = $h;
						$new_w = (int) round( $h * $ratio_src );
					} else {
						// taller than target: fit width, then crop height
						$new_w = $w;
						$new_h = (int) round( $w / max( 1e-6, $ratio_src ) );
					}
					$im->resizeImage( $new_w, $new_h, \Imagick::FILTER_LANCZOS, 1 );

					$x = max( 0, (int) floor( ( $new_w - $w ) / 2 ) );
					$y = max( 0, (int) floor( ( $new_h - $h ) / 2 ) );
					$im->cropImage( $w, $h, $x, $y );
				} else {
					// Contain-like resize, preserve aspect ratio
					if ( $w > 0 && $h > 0 ) {
						$im->thumbnailImage( $w, $h, true, true );
					} elseif ( $w > 0 ) {
						$im->thumbnailImage( $w, 0, true );
					} else {
						$im->thumbnailImage( 0, $h, true );
					}
				}

				$im->stripImage(); // drop metadata in thumbs
				$im->writeImage( $dest_abs );
				$width  = $im->getImageWidth();
				$height = $im->getImageHeight();
				$im->clear();
				$im->destroy();

				// Record in WP's expected metadata format
				$metadata['sizes'][ $name ] = array(
					'file'      => basename( $dest_abs ),
					'width'     => $width,
					'height'    => $height,
					'mime-type' => $this->mime_from_ext( $ext ),
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
				return 'image/' . $ext; // fallback
		}
	}
}
