<?php
/**
 * Metadata Extractor for OptiPress Library Organizer
 *
 * Extracts EXIF, IPTC, and other metadata from image files.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer_Metadata_Extractor
 *
 * Handles metadata extraction from various image formats.
 */
class OptiPress_Organizer_Metadata_Extractor {

	/**
	 * Extract EXIF data from a file.
	 *
	 * @param string $file_path File path.
	 * @return array|false EXIF data or false on failure.
	 */
	public function extract_exif( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		// Check if exif functions are available
		if ( ! function_exists( 'exif_read_data' ) ) {
			return false;
		}

		// Suppress errors as exif_read_data can be noisy
		$exif_data = @exif_read_data( $file_path, 0, true );

		if ( ! $exif_data ) {
			return false;
		}

		// Parse and organize EXIF data
		$organized = array();

		// Camera information
		if ( ! empty( $exif_data['IFD0']['Make'] ) ) {
			$organized['camera_make'] = trim( $exif_data['IFD0']['Make'] );
		}

		if ( ! empty( $exif_data['IFD0']['Model'] ) ) {
			$organized['camera_model'] = trim( $exif_data['IFD0']['Model'] );
		}

		// Lens information
		if ( ! empty( $exif_data['EXIF']['LensModel'] ) ) {
			$organized['lens_model'] = trim( $exif_data['EXIF']['LensModel'] );
		}

		// Shooting settings
		if ( ! empty( $exif_data['EXIF']['ExposureTime'] ) ) {
			$organized['exposure_time'] = $exif_data['EXIF']['ExposureTime'];
		}

		if ( ! empty( $exif_data['EXIF']['FNumber'] ) ) {
			$organized['f_number'] = $exif_data['EXIF']['FNumber'];
		}

		if ( ! empty( $exif_data['EXIF']['ISOSpeedRatings'] ) ) {
			$organized['iso'] = $exif_data['EXIF']['ISOSpeedRatings'];
		}

		if ( ! empty( $exif_data['EXIF']['FocalLength'] ) ) {
			$organized['focal_length'] = $exif_data['EXIF']['FocalLength'];
		}

		// Date taken
		if ( ! empty( $exif_data['EXIF']['DateTimeOriginal'] ) ) {
			$organized['date_taken'] = $exif_data['EXIF']['DateTimeOriginal'];
		}

		// GPS information
		if ( ! empty( $exif_data['GPS'] ) ) {
			$organized['gps'] = $this->parse_gps_data( $exif_data['GPS'] );
		}

		// Orientation
		if ( ! empty( $exif_data['IFD0']['Orientation'] ) ) {
			$organized['orientation'] = $exif_data['IFD0']['Orientation'];
		}

		// Software
		if ( ! empty( $exif_data['IFD0']['Software'] ) ) {
			$organized['software'] = trim( $exif_data['IFD0']['Software'] );
		}

		// Copyright
		if ( ! empty( $exif_data['IFD0']['Copyright'] ) ) {
			$organized['copyright'] = trim( $exif_data['IFD0']['Copyright'] );
		}

		// Store raw data for advanced use
		$organized['raw_exif'] = $exif_data;

		return $organized;
	}

	/**
	 * Parse GPS data from EXIF.
	 *
	 * @param array $gps_data GPS EXIF data.
	 * @return array Parsed GPS coordinates.
	 */
	private function parse_gps_data( $gps_data ) {
		$gps = array();

		if ( ! empty( $gps_data['GPSLatitude'] ) && ! empty( $gps_data['GPSLatitudeRef'] ) ) {
			$lat = $this->gps_to_decimal( $gps_data['GPSLatitude'], $gps_data['GPSLatitudeRef'] );
			if ( $lat !== false ) {
				$gps['latitude'] = $lat;
			}
		}

		if ( ! empty( $gps_data['GPSLongitude'] ) && ! empty( $gps_data['GPSLongitudeRef'] ) ) {
			$lon = $this->gps_to_decimal( $gps_data['GPSLongitude'], $gps_data['GPSLongitudeRef'] );
			if ( $lon !== false ) {
				$gps['longitude'] = $lon;
			}
		}

		if ( ! empty( $gps_data['GPSAltitude'] ) ) {
			$gps['altitude'] = $this->eval_fraction( $gps_data['GPSAltitude'] );
		}

		return $gps;
	}

	/**
	 * Convert GPS coordinates to decimal format.
	 *
	 * @param array  $coordinate GPS coordinate array.
	 * @param string $hemisphere Hemisphere (N, S, E, W).
	 * @return float|false Decimal coordinate or false on failure.
	 */
	private function gps_to_decimal( $coordinate, $hemisphere ) {
		if ( ! is_array( $coordinate ) || count( $coordinate ) < 3 ) {
			return false;
		}

		$degrees = $this->eval_fraction( $coordinate[0] );
		$minutes = $this->eval_fraction( $coordinate[1] );
		$seconds = $this->eval_fraction( $coordinate[2] );

		$decimal = $degrees + ( $minutes / 60 ) + ( $seconds / 3600 );

		// Adjust for hemisphere
		if ( in_array( $hemisphere, array( 'S', 'W' ), true ) ) {
			$decimal *= -1;
		}

		return $decimal;
	}

	/**
	 * Evaluate fraction string to decimal.
	 *
	 * @param string $fraction Fraction string (e.g., "50/1").
	 * @return float Decimal value.
	 */
	private function eval_fraction( $fraction ) {
		if ( strpos( $fraction, '/' ) !== false ) {
			$parts = explode( '/', $fraction );
			if ( count( $parts ) === 2 && $parts[1] != 0 ) {
				return floatval( $parts[0] ) / floatval( $parts[1] );
			}
		}

		return floatval( $fraction );
	}

	/**
	 * Extract IPTC data from a file.
	 *
	 * @param string $file_path File path.
	 * @return array|false IPTC data or false on failure.
	 */
	public function extract_iptc( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		// Get image info
		$size = @getimagesize( $file_path, $info );

		if ( ! $size || empty( $info['APP13'] ) ) {
			return false;
		}

		// Parse IPTC data
		$iptc = @iptcparse( $info['APP13'] );

		if ( ! $iptc ) {
			return false;
		}

		$organized = array();

		// Title
		if ( ! empty( $iptc['2#005'][0] ) ) {
			$organized['title'] = trim( $iptc['2#005'][0] );
		}

		// Description/Caption
		if ( ! empty( $iptc['2#120'][0] ) ) {
			$organized['caption'] = trim( $iptc['2#120'][0] );
		}

		// Keywords
		if ( ! empty( $iptc['2#025'] ) ) {
			$organized['keywords'] = array_map( 'trim', $iptc['2#025'] );
		}

		// Copyright
		if ( ! empty( $iptc['2#116'][0] ) ) {
			$organized['copyright'] = trim( $iptc['2#116'][0] );
		}

		// Creator/Photographer
		if ( ! empty( $iptc['2#080'][0] ) ) {
			$organized['creator'] = trim( $iptc['2#080'][0] );
		}

		// Credit
		if ( ! empty( $iptc['2#110'][0] ) ) {
			$organized['credit'] = trim( $iptc['2#110'][0] );
		}

		// Source
		if ( ! empty( $iptc['2#115'][0] ) ) {
			$organized['source'] = trim( $iptc['2#115'][0] );
		}

		// Store raw data
		$organized['raw_iptc'] = $iptc;

		return $organized;
	}

	/**
	 * Extract image dimensions.
	 *
	 * @param string $file_path File path.
	 * @return array|false Array with width and height, or false on failure.
	 */
	public function extract_dimensions( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		$size = @getimagesize( $file_path );

		if ( ! $size ) {
			return false;
		}

		return array(
			'width'  => $size[0],
			'height' => $size[1],
			'type'   => $size[2],
			'mime'   => $size['mime'],
		);
	}

	/**
	 * Get file information (size, MIME type, etc.).
	 *
	 * @param string $file_path File path.
	 * @return array File information.
	 */
	public function get_file_info( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'exists'   => false,
				'readable' => false,
			);
		}

		$info = array(
			'exists'   => true,
			'readable' => true,
			'size'     => filesize( $file_path ),
			'filename' => basename( $file_path ),
			'extension' => strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ),
		);

		// Get MIME type
		$mime = wp_check_filetype( $file_path );
		$info['mime_type'] = $mime['type'] ? $mime['type'] : 'application/octet-stream';

		// Get dimensions if it's an image
		$dimensions = $this->extract_dimensions( $file_path );
		if ( $dimensions ) {
			$info['width'] = $dimensions['width'];
			$info['height'] = $dimensions['height'];
			$info['dimensions'] = $dimensions['width'] . 'x' . $dimensions['height'];
		}

		return $info;
	}

	/**
	 * Extract all metadata from a file.
	 *
	 * @param string $file_path File path.
	 * @return array Complete metadata array.
	 */
	public function extract_all_metadata( $file_path ) {
		$metadata = array();

		// File info
		$file_info = $this->get_file_info( $file_path );
		if ( ! empty( $file_info ) ) {
			$metadata['file_info'] = $file_info;
		}

		// EXIF data
		$exif = $this->extract_exif( $file_path );
		if ( $exif ) {
			$metadata['exif'] = $exif;
		}

		// IPTC data
		$iptc = $this->extract_iptc( $file_path );
		if ( $iptc ) {
			$metadata['iptc'] = $iptc;
		}

		// Dimensions
		$dimensions = $this->extract_dimensions( $file_path );
		if ( $dimensions ) {
			$metadata['dimensions'] = $dimensions;
		}

		return $metadata;
	}

	/**
	 * Store metadata for a file.
	 *
	 * @param int   $file_id File post ID.
	 * @param array $metadata Metadata array.
	 * @return bool Success status.
	 */
	public function store_metadata( $file_id, $metadata ) {
		if ( ! $file_id ) {
			return false;
		}

		// Store EXIF data if provided
		if ( ! empty( $metadata['exif'] ) ) {
			update_post_meta( $file_id, '_optipress_exif_data', $metadata['exif'] );
		}

		// Store IPTC data if provided
		if ( ! empty( $metadata['iptc'] ) ) {
			update_post_meta( $file_id, '_optipress_iptc_data', $metadata['iptc'] );
		}

		// Store dimensions if provided
		if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
			update_post_meta( $file_id, '_optipress_dimensions', $metadata['width'] . 'x' . $metadata['height'] );
		} elseif ( ! empty( $metadata['dimensions']['width'] ) && ! empty( $metadata['dimensions']['height'] ) ) {
			update_post_meta( $file_id, '_optipress_dimensions', $metadata['dimensions']['width'] . 'x' . $metadata['dimensions']['height'] );
		}

		// Store file info if provided
		if ( ! empty( $metadata['file_info'] ) ) {
			update_post_meta( $file_id, '_optipress_file_info', $metadata['file_info'] );
		}

		// Store complete metadata
		update_post_meta( $file_id, '_optipress_complete_metadata', $metadata );

		return true;
	}
}
