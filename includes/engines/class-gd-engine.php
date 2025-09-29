<?php
/**
 * GD Image Engine
 *
 * Image conversion engine using PHP GD library.
 *
 * @package OptiPress
 */

namespace OptiPress\Engines;

defined( 'ABSPATH' ) || exit;

/**
 * GD_Engine class
 *
 * Implements image conversion using PHP's GD library.
 * - WebP: Always supported if GD is available
 * - AVIF: Requires PHP 8.1+
 */
class GD_Engine implements ImageEngineInterface {

	/**
	 * Check if GD library is available
	 *
	 * @return bool Whether GD is available.
	 */
	public function is_available() {
		return extension_loaded( 'gd' ) && function_exists( 'gd_info' );
	}

	/**
	 * Check if GD supports a specific format
	 *
	 * @param string $format Format to check ('webp' or 'avif').
	 * @return bool Whether the format is supported.
	 */
	public function supports_format( $format ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$format = strtolower( $format );

		switch ( $format ) {
			case 'webp':
				return function_exists( 'imagewebp' );

			case 'avif':
				// AVIF in GD requires PHP 8.1+
				return version_compare( PHP_VERSION, '8.1.0', '>=' ) && function_exists( 'imageavif' );

			default:
				return false;
		}
	}

	/**
	 * Convert an image using GD library
	 *
	 * @param string $source_path Path to source image.
	 * @param string $dest_path   Path for converted image.
	 * @param string $format      Target format ('webp' or 'avif').
	 * @param int    $quality     Quality level (1-100).
	 * @return bool Whether conversion was successful.
	 */
	public function convert( $source_path, $dest_path, $format, $quality ) {
		// Validate inputs
		if ( ! file_exists( $source_path ) || ! is_readable( $source_path ) ) {
			return false;
		}

		if ( ! $this->supports_format( $format ) ) {
			return false;
		}

		// Validate quality
		$quality = max( 1, min( 100, intval( $quality ) ) );

		// Load source image
		$image_resource = $this->load_image( $source_path );

		if ( false === $image_resource ) {
			return false;
		}

		// Convert to target format
		$result = false;
		$format = strtolower( $format );

		try {
			switch ( $format ) {
				case 'webp':
					$result = imagewebp( $image_resource, $dest_path, $quality );
					break;

				case 'avif':
					// AVIF quality parameter works differently in GD
					// Use -1 for lossless, 0-100 for lossy (lower = better quality)
					$avif_quality = 100 - $quality;
					$result       = imageavif( $image_resource, $dest_path, $avif_quality );
					break;
			}
		} catch ( \Exception $e ) {
			$result = false;
		}

		// Free memory
		imagedestroy( $image_resource );

		return $result;
	}

	/**
	 * Load image from file into GD resource
	 *
	 * @param string $file_path Path to image file.
	 * @return resource|false GD image resource or false on failure.
	 */
	private function load_image( $file_path ) {
		$image_info = @getimagesize( $file_path );

		if ( false === $image_info ) {
			return false;
		}

		$mime_type = $image_info['mime'];
		$image     = false;

		switch ( $mime_type ) {
			case 'image/jpeg':
				$image = @imagecreatefromjpeg( $file_path );
				break;

			case 'image/png':
				$image = @imagecreatefrompng( $file_path );

				// Preserve transparency for PNG
				if ( false !== $image ) {
					imagealphablending( $image, false );
					imagesavealpha( $image, true );
				}
				break;

			case 'image/gif':
				$image = @imagecreatefromgif( $file_path );

				// Preserve transparency for GIF
				if ( false !== $image ) {
					imagealphablending( $image, false );
					imagesavealpha( $image, true );
				}
				break;

			case 'image/webp':
				if ( function_exists( 'imagecreatefromwebp' ) ) {
					$image = @imagecreatefromwebp( $file_path );
				}
				break;
		}

		return $image;
	}

	/**
	 * Get engine name
	 *
	 * @return string Engine name.
	 */
	public function get_name() {
		return 'gd';
	}
}