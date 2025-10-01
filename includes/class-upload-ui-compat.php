<?php
/**
 * Upload UI Compatibility
 *
 * Ensures WordPress UI allows advanced image types client-side.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * Upload_UI_Compat class
 *
 * Ensures the WordPress UI (file input accept lists, block editor,
 * and Plupload uploaders) allows advanced image types (PSD/TIFF/RAW, etc.)
 * so uploads aren't blocked client-side before hitting PHP.
 *
 * Sources of truth:
 *  - Engine_Registry::get_all_supported_input_formats()
 *  - MIME_Type_Map::get_upload_mimes_for_supported()
 *
 * Customization:
 *  - Filter 'optipress_client_allowed_exts' to edit extensions
 *  - Filter 'optipress_client_allowed_mimes' to edit mime map
 *  - Filter 'optipress_enable_upload_ui_compat' to disable (bool)
 */
final class Upload_UI_Compat {
	/**
	 * Singleton instance
	 *
	 * @var Upload_UI_Compat|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Upload_UI_Compat
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - registers hooks
	 */
	private function __construct() {
		$enabled = apply_filters( 'optipress_enable_upload_ui_compat', true );
		if ( ! $enabled ) {
			return;
		}

		add_filter( 'mime_types', array( $this, 'filter_core_mime_types' ) );               // affects <input accept>
		add_filter( 'block_editor_settings_all', array( $this, 'filter_block_editor' ) );   // Gutenberg drag/drop picker
		add_filter( 'plupload_default_settings', array( $this, 'filter_plupload_defaults' ) ); // global template
		add_filter( 'plupload_init', array( $this, 'filter_plupload_init' ) );              // per-instance init (Media modal, blocks)
	}

	/**
	 * Compute allowed extensions (comma string) and mime map using OptiPress registries.
	 *
	 * @return array Array containing [extensions array, mime map array, comma-separated extensions string].
	 */
	private function compute_allowed() {
		$exts     = array();
		$mime_map = array();

		// Pull formats that your engines can actually ingest
		if ( class_exists( '\\OptiPress\\Engines\\Engine_Registry' ) && class_exists( '\\OptiPress\\MIME_Type_Map' ) ) {
			$registry          = \OptiPress\Engines\Engine_Registry::get_instance();
			$supported_formats = $registry->get_all_supported_input_formats();

			// ext => mime; keys can be pipe-separated, e.g., 'tiff|tif'
			$mime_map = \OptiPress\MIME_Type_Map::get_upload_mimes_for_supported( $supported_formats );

			// explode pipe groups into individual exts
			foreach ( $mime_map as $ext_key => $mime ) {
				foreach ( explode( '|', $ext_key ) as $e ) {
					$e = trim( strtolower( $e ) );
					if ( $e && ! in_array( $e, $exts, true ) ) {
						$exts[] = $e;
					}
				}
			}
		}

		// Make sure PSD is present (some builds might omit it by default)
		if ( ! in_array( 'psd', $exts, true ) ) {
			$exts[] = 'psd';
		}
		if ( ! isset( $mime_map['psd'] ) ) {
			$mime_map['psd'] = 'image/vnd.adobe.photoshop';
		}

		// Ensure JP2 family is present (requires OpenJPEG delegate)
		$jp2_exts = array( 'jp2', 'j2k', 'jpf', 'jpx', 'jpm' );
		foreach ( $jp2_exts as $jp2_ext ) {
			if ( ! in_array( $jp2_ext, $exts, true ) ) {
				$exts[] = $jp2_ext;
			}
		}
		if ( ! isset( $mime_map['jp2'] ) ) {
			$mime_map['jp2'] = 'image/jp2';
		}
		if ( ! isset( $mime_map['j2k'] ) ) {
			$mime_map['j2k'] = 'image/jp2';
		}
		if ( ! isset( $mime_map['jpf'] ) ) {
			$mime_map['jpf'] = 'image/jpx';
		}
		if ( ! isset( $mime_map['jpx'] ) ) {
			$mime_map['jpx'] = 'image/jpx';
		}
		if ( ! isset( $mime_map['jpm'] ) ) {
			$mime_map['jpm'] = 'image/jpm';
		}

		// Ensure HEIC/HEIF is present (requires libheif delegate)
		if ( ! in_array( 'heic', $exts, true ) ) {
			$exts[] = 'heic';
		}
		if ( ! in_array( 'heif', $exts, true ) ) {
			$exts[] = 'heif';
		}
		if ( ! isset( $mime_map['heic'] ) ) {
			$mime_map['heic'] = 'image/heic';
		}
		if ( ! isset( $mime_map['heif'] ) ) {
			$mime_map['heif'] = 'image/heif';
		}

		// Give integrators a way to adjust
		$exts     = apply_filters( 'optipress_client_allowed_exts', $exts );       // array of 'psd','tif',...
		$mime_map = apply_filters( 'optipress_client_allowed_mimes', $mime_map );  // ['psd'=>'image/vnd.adobe.photoshop', ...]

		// Build comma string for Plupload
		$exts_comma = implode( ',', array_unique( $exts ) );

		return array( $exts, $mime_map, $exts_comma );
	}

	/**
	 * Core mime map → influences <input accept="..."> in many places.
	 *
	 * @param array $m Existing mime types.
	 * @return array Modified mime types.
	 */
	public function filter_core_mime_types( $m ) {
		list( , $mime_map, ) = $this->compute_allowed();
		foreach ( $mime_map as $ext_key => $mime ) {
			// If key contains pipes (e.g., 'tiff|tif'), split and assign same mime to each ext
			foreach ( explode( '|', $ext_key ) as $ext ) {
				$ext = trim( strtolower( $ext ) );
				if ( ! $ext ) {
					continue;
				}
				$m[ $ext ] = $mime;
			}
		}
		return $m;
	}

	/**
	 * Block editor: ensure its internal allowlist includes our mimes.
	 *
	 * @param array $settings Block editor settings.
	 * @return array Modified settings.
	 */
	public function filter_block_editor( $settings ) {
		if ( ! isset( $settings['allowedMimeTypes'] ) || ! is_array( $settings['allowedMimeTypes'] ) ) {
			$settings['allowedMimeTypes'] = array();
		}
		list( , $mime_map, ) = $this->compute_allowed();
		foreach ( $mime_map as $ext_key => $mime ) {
			foreach ( explode( '|', $ext_key ) as $ext ) {
				$ext = trim( strtolower( $ext ) );
				if ( ! $ext ) {
					continue;
				}
				$settings['allowedMimeTypes'][ $ext ] = $mime;
			}
		}
		return $settings;
	}

	/**
	 * Plupload defaults: adds a bucket with all our extensions.
	 *
	 * @param array $s Plupload settings.
	 * @return array Modified settings.
	 */
	public function filter_plupload_defaults( $s ) {
		list( , , $exts_comma ) = $this->compute_allowed();
		if ( ! isset( $s['filters'] ) ) {
			$s['filters'] = array();
		}
		if ( ! isset( $s['filters']['mime_types'] ) || ! is_array( $s['filters']['mime_types'] ) ) {
			$s['filters']['mime_types'] = array();
		}
		$s['filters']['mime_types'][] = array(
			'title'      => __( 'OptiPress Supported Images', 'optipress' ),
			'extensions' => $exts_comma,
		);

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'OptiPress Upload_UI_Compat: Plupload extensions allowed: ' . $exts_comma );
		}

		return $s;
	}

	/**
	 * Plupload per-instance init: some uploaders ignore defaults; this catches all.
	 *
	 * @param array $s Plupload init settings.
	 * @return array Modified settings.
	 */
	public function filter_plupload_init( $s ) {
		list( , , $exts_comma ) = $this->compute_allowed();
		if ( ! isset( $s['filters'] ) ) {
			$s['filters'] = array();
		}
		if ( ! isset( $s['filters']['mime_types'] ) || ! is_array( $s['filters']['mime_types'] ) ) {
			$s['filters']['mime_types'] = array();
		}
		$s['filters']['mime_types'][] = array(
			'title'      => __( 'OptiPress Supported Images', 'optipress' ),
			'extensions' => $exts_comma,
		);
		return $s;
	}
}
