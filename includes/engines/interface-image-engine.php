<?php
/**
 * Image Engine Interface
 *
 * Defines the contract for all image conversion engines.
 *
 * @package OptiPress
 */

namespace OptiPress\Engines;

defined( 'ABSPATH' ) || exit;

/**
 * ImageEngineInterface
 *
 * All image conversion engines must implement this interface.
 * This allows for easy extensibility by adding new engines in the future.
 */
interface ImageEngineInterface {

	/**
	 * Check if the engine is available on the server
	 *
	 * @return bool Whether the engine is available.
	 */
	public function is_available();

	/**
	 * Check if the engine supports a specific output format
	 *
	 * @param string $format Format to check ('webp' or 'avif').
	 * @return bool Whether the format is supported.
	 */
	public function supports_format( $format );

	/**
	 * Get list of supported input image formats (MIME types)
	 *
	 * Returns an array of MIME types that this engine can read and convert.
	 * For example: ['image/jpeg', 'image/png', 'image/gif', 'image/tiff']
	 *
	 * @return array Array of supported MIME types.
	 */
	public function get_supported_input_formats();

	/**
	 * Convert an image from source to destination
	 *
	 * @param string $source_path Path to source image file.
	 * @param string $dest_path   Path where converted image should be saved.
	 * @param string $format      Target format ('webp' or 'avif').
	 * @param int    $quality     Quality level (1-100).
	 * @return bool Whether the conversion was successful.
	 */
	public function convert( $source_path, $dest_path, $format, $quality );

	/**
	 * Get the engine name
	 *
	 * @return string Engine name (e.g., 'gd', 'imagick').
	 */
	public function get_name();
}