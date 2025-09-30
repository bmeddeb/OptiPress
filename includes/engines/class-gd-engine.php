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
	 * Check if GD supports a specific output format
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
	 * Get list of supported input image formats
	 *
	 * Dynamically detects which image formats GD can read on this server.
	 *
	 * @return array Array of supported MIME types.
	 */
	public function get_supported_input_formats() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$formats = array();

		// JPEG - always supported if GD is available
		if ( function_exists( 'imagecreatefromjpeg' ) ) {
			$formats[] = 'image/jpeg';
		}

		// PNG - always supported if GD is available
		if ( function_exists( 'imagecreatefrompng' ) ) {
			$formats[] = 'image/png';
		}

		// GIF
		if ( function_exists( 'imagecreatefromgif' ) ) {
			$formats[] = 'image/gif';
		}

		// WebP (can convert WebP to WebP/AVIF for recompression)
		if ( function_exists( 'imagecreatefromwebp' ) ) {
			$formats[] = 'image/webp';
		}

		// BMP (PHP 7.2+)
		if ( function_exists( 'imagecreatefrombmp' ) ) {
			$formats[] = 'image/bmp';
			$formats[] = 'image/x-ms-bmp';
			$formats[] = 'image/x-bmp';
		}

		// TIFF (PHP 7.4+)
		if ( function_exists( 'imagecreatefromtiff' ) ) {
			$formats[] = 'image/tiff';
		}

		// WBMP (Wireless Bitmap)
		if ( function_exists( 'imagecreatefromwbmp' ) ) {
			$formats[] = 'image/vnd.wap.wbmp';
		}

		// XBM (X BitMap)
		if ( function_exists( 'imagecreatefromxbm' ) ) {
			$formats[] = 'image/x-xbitmap';
			$formats[] = 'image/xbm';
		}

		// AVIF input (PHP 8.1+)
		if ( function_exists( 'imagecreatefromavif' ) ) {
			$formats[] = 'image/avif';
		}

		return $formats;
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
			error_log( 'OptiPress GD: Source file does not exist or is not readable: ' . $source_path );
			return false;
		}

		if ( ! $this->supports_format( $format ) ) {
			error_log( 'OptiPress GD: Format not supported: ' . $format );
			return false;
		}

		// Check available memory before processing
		$memory_limit = $this->get_memory_limit();
		$memory_usage = memory_get_usage( true );
		$available_memory = $memory_limit - $memory_usage;

		// Require at least 64MB free memory for image processing
		if ( $available_memory < 67108864 ) {
			error_log( sprintf(
				'OptiPress GD: Insufficient memory for conversion. Available: %s, Required: 64MB',
				size_format( $available_memory )
			) );
			return false;
		}

		// Check file size - skip very large files
		$file_size = filesize( $source_path );
		/**
		 * Filter the maximum file size for GD conversion.
		 *
		 * @param int $max_filesize Maximum file size in bytes. Default 10485760 (10MB).
		 */
		$max_filesize = apply_filters( 'optipress_max_filesize_bytes', 10485760 );

		if ( $file_size > $max_filesize ) {
			error_log( sprintf(
				'OptiPress GD: File too large for safe conversion: %s (%s)',
				basename( $source_path ),
				size_format( $file_size )
			) );
			return false;
		}

		// Validate quality
		$quality = max( 1, min( 100, intval( $quality ) ) );

		// Load source image
		$image_resource = $this->load_image( $source_path );

		if ( false === $image_resource ) {
			error_log( 'OptiPress GD: Failed to load image: ' . $source_path );
			return false;
		}

		// Check image dimensions to prevent memory issues
		$width = imagesx( $image_resource );
		$height = imagesy( $image_resource );
		$pixels = $width * $height;

		/**
		 * Filter the maximum pixel count for GD conversion.
		 *
		 * @param int $max_pixels Maximum pixels (width * height). Default 25000000 (25 megapixels).
		 */
		$max_pixels = apply_filters( 'optipress_max_pixels', 25000000 );

		// Skip extremely large images
		if ( $pixels > $max_pixels ) {
			error_log( sprintf(
				'OptiPress GD: Image dimensions too large: %dx%d (%s pixels)',
				$width,
				$height,
				number_format( $pixels )
			) );
			imagedestroy( $image_resource );
			return false;
		}

		// Convert to target format
		$result = false;
		$format = strtolower( $format );

		try {
			switch ( $format ) {
				case 'webp':
					$result = @imagewebp( $image_resource, $dest_path, $quality );
					if ( false === $result ) {
						error_log( 'OptiPress GD: imagewebp() failed' );
					}
					break;

				case 'avif':
					// AVIF quality parameter works differently in GD
					// Use -1 for lossless, 0-100 for lossy (lower = better quality)
					$avif_quality = 100 - $quality;
					$result       = @imageavif( $image_resource, $dest_path, $avif_quality );
					if ( false === $result ) {
						error_log( 'OptiPress GD: imageavif() failed' );
					}
					break;
			}
		} catch ( \Exception $e ) {
			error_log( 'OptiPress GD: Exception during conversion: ' . $e->getMessage() );
			$result = false;
		} catch ( \Throwable $e ) {
			error_log( 'OptiPress GD: Fatal error during conversion: ' . $e->getMessage() );
			$result = false;
		}

		// Free memory
		imagedestroy( $image_resource );

		// Verify output file was created
		if ( $result && ( ! file_exists( $dest_path ) || filesize( $dest_path ) === 0 ) ) {
			error_log( 'OptiPress GD: Conversion produced no output file or empty file' );
			return false;
		}

		return $result;
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

					// Preserve transparency for WebP
					if ( false !== $image ) {
						imagealphablending( $image, false );
						imagesavealpha( $image, true );
					}
				}
				break;

			case 'image/bmp':
			case 'image/x-ms-bmp':
			case 'image/x-bmp':
				if ( function_exists( 'imagecreatefrombmp' ) ) {
					$image = @imagecreatefrombmp( $file_path );
				}
				break;

			case 'image/tiff':
				if ( function_exists( 'imagecreatefromtiff' ) ) {
					$image = @imagecreatefromtiff( $file_path );
				}
				break;

			case 'image/vnd.wap.wbmp':
				if ( function_exists( 'imagecreatefromwbmp' ) ) {
					$image = @imagecreatefromwbmp( $file_path );
				}
				break;

			case 'image/x-xbitmap':
			case 'image/xbm':
				if ( function_exists( 'imagecreatefromxbm' ) ) {
					$image = @imagecreatefromxbm( $file_path );
				}
				break;

			case 'image/avif':
				if ( function_exists( 'imagecreatefromavif' ) ) {
					$image = @imagecreatefromavif( $file_path );

					// Preserve transparency for AVIF
					if ( false !== $image ) {
						imagealphablending( $image, false );
						imagesavealpha( $image, true );
					}
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