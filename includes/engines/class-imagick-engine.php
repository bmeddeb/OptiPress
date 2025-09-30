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
			error_log( 'OptiPress: Source file does not exist or is not readable: ' . $source_path );
			return false;
		}

		if ( ! $this->supports_format( $format ) ) {
			error_log( 'OptiPress: Format not supported: ' . $format );
			return false;
		}

		// Check available memory before processing
		$memory_limit = $this->get_memory_limit();
		$memory_usage = memory_get_usage( true );
		$available_memory = $memory_limit - $memory_usage;

		// Require at least 64MB free memory for image processing
		if ( $available_memory < 67108864 ) {
			error_log( sprintf(
				'OptiPress: Insufficient memory for conversion. Available: %s, Required: 64MB',
				size_format( $available_memory )
			) );
			return false;
		}

		// Check file size - skip very large files that might cause issues
		$file_size = filesize( $source_path );
		if ( $file_size > 10485760 ) { // 10MB
			error_log( sprintf(
				'OptiPress: File too large for safe conversion: %s (%s)',
				basename( $source_path ),
				size_format( $file_size )
			) );
			return false;
		}

		// Validate quality
		$quality = max( 1, min( 100, intval( $quality ) ) );
		$format  = strtoupper( $format );

		$imagick = null;

		try {
			// Set resource limits for Imagick to prevent crashes
			$this->set_imagick_resource_limits();

			$imagick = new \Imagick();

			// Set resource limit for this instance
			$imagick->setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 ); // 256MB
			$imagick->setResourceLimit( \Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024 );    // 512MB

			// Load source image with error handling
			try {
				if ( ! $imagick->readImage( $source_path ) ) {
					error_log( 'OptiPress: Failed to read image: ' . $source_path );
					return false;
				}
			} catch ( \ImagickException $e ) {
				error_log( 'OptiPress: ImagickException while reading image: ' . $e->getMessage() );
				$this->cleanup_imagick( $imagick );
				return false;
			}

			// Check image dimensions to prevent memory issues
			$width = $imagick->getImageWidth();
			$height = $imagick->getImageHeight();
			$pixels = $width * $height;

			// Skip extremely large images (> 25 megapixels)
			if ( $pixels > 25000000 ) {
				error_log( sprintf(
					'OptiPress: Image dimensions too large: %dx%d (%s pixels)',
					$width,
					$height,
					number_format( $pixels )
				) );
				$this->cleanup_imagick( $imagick );
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
					// AVIF-specific settings with safe defaults
					// AVIF encoding is memory-intensive, use conservative settings
					$imagick->setOption( 'heic:quality', (string) $quality );

					// Limit encoding speed to reduce memory usage (0=slowest/best, 8=fastest)
					$imagick->setOption( 'heic:speed', '6' );
					break;
			}

			// Remove EXIF data to reduce file size
			try {
				$imagick->stripImage();
			} catch ( \Exception $e ) {
				// Ignore if stripping fails
			}

			// Write converted image with error handling
			try {
				$result = $imagick->writeImage( $dest_path );
			} catch ( \ImagickException $e ) {
				error_log( 'OptiPress: ImagickException while writing image: ' . $e->getMessage() );
				$this->cleanup_imagick( $imagick );

				// Clean up partial file if it exists
				if ( file_exists( $dest_path ) ) {
					@unlink( $dest_path );
				}
				return false;
			}

			// Clean up
			$this->cleanup_imagick( $imagick );

			// Verify output file was created
			if ( ! file_exists( $dest_path ) || filesize( $dest_path ) === 0 ) {
				error_log( 'OptiPress: Conversion produced no output file or empty file' );
				return false;
			}

			return $result;

		} catch ( \ImagickException $e ) {
			error_log( 'OptiPress: ImagickException: ' . $e->getMessage() );
			$this->cleanup_imagick( $imagick );
			return false;
		} catch ( \Exception $e ) {
			error_log( 'OptiPress: Exception during conversion: ' . $e->getMessage() );
			$this->cleanup_imagick( $imagick );
			return false;
		} catch ( \Throwable $e ) {
			// Catch any other errors (PHP 7+)
			error_log( 'OptiPress: Fatal error during conversion: ' . $e->getMessage() );
			$this->cleanup_imagick( $imagick );
			return false;
		}
	}

	/**
	 * Clean up Imagick resources
	 *
	 * @param \Imagick|null $imagick Imagick instance to clean up.
	 */
	private function cleanup_imagick( $imagick ) {
		if ( $imagick instanceof \Imagick ) {
			try {
				$imagick->clear();
				$imagick->destroy();
			} catch ( \Exception $e ) {
				// Ignore cleanup errors
			}
		}
	}

	/**
	 * Set global Imagick resource limits
	 */
	private function set_imagick_resource_limits() {
		if ( ! $this->is_available() ) {
			return;
		}

		try {
			// Set conservative memory limits to prevent crashes
			\Imagick::setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 ); // 256MB
			\Imagick::setResourceLimit( \Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024 );    // 512MB
			\Imagick::setResourceLimit( \Imagick::RESOURCETYPE_TIME, 60 );                   // 60 seconds
		} catch ( \Exception $e ) {
			// Ignore if setting limits fails
		}
	}

	/**
	 * Get PHP memory limit in bytes
	 *
	 * @return int Memory limit in bytes.
	 */
	private function get_memory_limit() {
		$memory_limit = ini_get( 'memory_limit' );

		if ( '-1' === $memory_limit ) {
			// No limit
			return PHP_INT_MAX;
		}

		// Convert to bytes
		$unit = strtolower( substr( $memory_limit, -1 ) );
		$value = (int) $memory_limit;

		switch ( $unit ) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
		}

		return $value;
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