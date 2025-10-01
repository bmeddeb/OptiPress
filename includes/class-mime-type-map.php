<?php
/**
 * MIME Type Mapping
 *
 * Centralized mapping between MIME types and file extensions for image formats.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * MIME_Type_Map class
 *
 * Provides comprehensive mapping between image MIME types and file extensions.
 * Used for upload validation and JavaScript file type detection.
 */
class MIME_Type_Map {

	/**
	 * Get comprehensive MIME type to extension mapping
	 *
	 * Returns array where keys are MIME types and values are pipe-separated extensions.
	 * Format matches WordPress upload_mimes filter.
	 *
	 * @return array MIME type to extensions mapping.
	 */
	public static function get_mime_to_extension_map() {
		return array(
			// Standard formats (already in WordPress, but included for completeness)
			'image/jpeg'                  => 'jpg|jpeg|jpe',
			'image/png'                   => 'png',
			'image/gif'                   => 'gif',
			'image/webp'                  => 'webp',
			'image/bmp'                   => 'bmp',
			'image/x-ms-bmp'              => 'bmp',
			'image/x-bmp'                 => 'bmp',

			// Extended formats
			'image/tiff'                  => 'tiff|tif',
			'image/avif'                  => 'avif',
			'image/heic'                  => 'heic',
			'image/heif'                  => 'heif',
			'image/x-icon'                => 'ico',
			'image/vnd.microsoft.icon'    => 'ico',

			// JPEG 2000 variants
			'image/jp2'                   => 'jp2|j2k|j2c',
			'image/jpx'                   => 'jpx|jpf',
			'image/jpm'                   => 'jpm',

			// Professional formats
			'image/vnd.adobe.photoshop'   => 'psd',
			'image/x-tga'                 => 'tga|tpic',
			'image/x-targa'               => 'tga',

			// Wireless/mobile formats
			'image/vnd.wap.wbmp'          => 'wbmp',

			// X Window formats
			'image/x-xbitmap'             => 'xbm',
			'image/x-xpixmap'             => 'xpm',

			// Portable formats (NetPBM)
			'image/x-portable-pixmap'     => 'ppm',
			'image/x-portable-graymap'    => 'pgm',
			'image/x-portable-bitmap'     => 'pbm',
			'image/x-portable-anymap'     => 'pnm',

			// Camera RAW formats
			'image/x-canon-cr2'           => 'cr2',
			'image/x-canon-crw'           => 'crw',
			'image/x-nikon-nef'           => 'nef|nrw',
			'image/x-sony-arw'            => 'arw|srf|sr2',
			'image/x-adobe-dng'           => 'dng',
			'image/x-fuji-raf'            => 'raf',
			'image/x-olympus-orf'         => 'orf',
			'image/x-panasonic-raw'       => 'raw|rw2',
			'image/x-pentax-pef'          => 'pef',
			'image/x-samsung-srw'         => 'srw',
			'image/x-kodak-dcr'           => 'dcr',

			// Other specialized formats
			'image/x-pcx'                 => 'pcx',
			'image/vnd.zbrush.pcx'        => 'pcx',
		);
	}

	/**
	 * Get extension to MIME type mapping (for JavaScript)
	 *
	 * Converts the MIME-to-extension map into extension-to-MIME map.
	 * Used by JavaScript to detect file types from filenames.
	 *
	 * @param array $supported_mimes Optional. Filter by supported MIME types.
	 * @return array Extension to MIME type mapping (extension => mime).
	 */
	public static function get_extension_to_mime_map( $supported_mimes = array() ) {
		$mime_map = self::get_mime_to_extension_map();
		$extension_map = array();

		foreach ( $mime_map as $mime => $extensions ) {
			// Skip if we're filtering and this MIME isn't supported
			if ( ! empty( $supported_mimes ) && ! in_array( $mime, $supported_mimes, true ) ) {
				continue;
			}

			// Split pipe-separated extensions
			$ext_list = explode( '|', $extensions );

			foreach ( $ext_list as $ext ) {
				$ext = trim( $ext );
				// First occurrence wins (don't override)
				if ( ! isset( $extension_map[ $ext ] ) ) {
					$extension_map[ $ext ] = $mime;
				}
			}
		}

		return $extension_map;
	}

	/**
	 * Get upload_mimes compatible array for supported formats
	 *
	 * Returns array formatted for WordPress upload_mimes filter, filtered
	 * by actually supported MIME types from engines.
	 *
	 * @param array $supported_mimes Supported MIME types from engines.
	 * @return array Upload mimes array (extension => mime).
	 */
	public static function get_upload_mimes_for_supported( $supported_mimes ) {
		$mime_map = self::get_mime_to_extension_map();
		$upload_mimes = array();

		foreach ( $supported_mimes as $mime ) {
			if ( isset( $mime_map[ $mime ] ) ) {
				$upload_mimes[ $mime_map[ $mime ] ] = $mime;
			}
		}

		return $upload_mimes;
	}
}
