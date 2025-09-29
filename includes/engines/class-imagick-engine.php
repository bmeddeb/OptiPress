<?php
/**
 * Imagick Image Engine
 *
 * Image conversion engine using ImageMagick extension.
 *
 * @package OptiPress
 */

namespace OptiPress\Engines;

defined( 'ABSPATH' ) || exit;

/**
 * Imagick_Engine class
 *
 * Implements image conversion using PHP's Imagick extension.
 * Generally provides better quality and performance than GD.
 * Format support depends on ImageMagick installation.
 */
class Imagick_Engine implements ImageEngineInterface {

	/**
	 * Check if Imagick extension is available
	 *
	 * @return bool Whether Imagick is available.
	 */
	public function is_available() {
		return extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
	}

	/**
	 * Check if Imagick supports a specific format
	 *
	 * @param string $format Format to check ('webp' or 'avif').
	 * @return bool Whether the format is supported.
	 */
	public function supports_format( $format ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$format = strtoupper( $format );

		try {
			$formats = \Imagick::queryFormats( $format );
			return in_array( $format, $formats, true );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Convert an image using Imagick
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
		$format  = strtoupper( $format );

		try {
			$imagick = new \Imagick();

			// Load source image
			if ( ! $imagick->readImage( $source_path ) ) {
				return false;
			}

			// Set compression quality
			$imagick->setImageCompressionQuality( $quality );

			// Set format
			$imagick->setImageFormat( $format );

			// Additional format-specific settings
			switch ( $format ) {
				case 'WEBP':
					// Enable WebP lossless for quality >= 95
					if ( $quality >= 95 ) {
						$imagick->setOption( 'webp:lossless', 'true' );
					} else {
						$imagick->setOption( 'webp:method', '6' ); // Best quality
					}
					break;

				case 'AVIF':
					// AVIF-specific settings
					// Use crf (Constant Rate Factor) for better quality control
					// Lower crf = better quality (range: 0-63, default: 23)
					$crf = (int) ( ( 100 - $quality ) * 0.63 );
					$imagick->setOption( 'heic:quality', (string) $quality );
					break;
			}

			// Remove EXIF data to reduce file size (optional)
			try {
				$imagick->stripImage();
			} catch ( \Exception $e ) {
				// Ignore if stripping fails
			}

			// Write converted image
			$result = $imagick->writeImage( $dest_path );

			// Clean up
			$imagick->clear();
			$imagick->destroy();

			return $result;

		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get engine name
	 *
	 * @return string Engine name.
	 */
	public function get_name() {
		return 'imagick';
	}

	/**
	 * Get Imagick version information
	 *
	 * @return string|null Version string or null if not available.
	 */
	public function get_version() {
		if ( ! $this->is_available() ) {
			return null;
		}

		try {
			$imagick = new \Imagick();
			$version = $imagick->getVersion();
			return isset( $version['versionString'] ) ? $version['versionString'] : null;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Get all supported formats
	 *
	 * @return array List of supported formats.
	 */
	public function get_supported_formats() {
		if ( ! $this->is_available() ) {
			return array();
		}

		try {
			return \Imagick::queryFormats();
		} catch ( \Exception $e ) {
			return array();
		}
	}
}