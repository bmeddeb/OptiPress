<?php
/**
 * Plugin Name: OptiPress
 * Plugin URI: https://optipress.meddeb.me
 * Description: Image optimization and safe SVG handling for WordPress. Converts images to WebP/AVIF and enables secure SVG uploads.
 * Version:     0.5.5
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: Ben Meddeb
 * Author URI: https://meddeb.me
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: optipress
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'OPTIPRESS_VERSION', '0.5.5' );
define( 'OPTIPRESS_PLUGIN_FILE', __FILE__ );
define( 'OPTIPRESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPTIPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader
if ( file_exists( OPTIPRESS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once OPTIPRESS_PLUGIN_DIR . 'vendor/autoload.php';
}

// Plugin Update Checker (GitHub releases)
if ( file_exists( OPTIPRESS_PLUGIN_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once OPTIPRESS_PLUGIN_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

	$optipress_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/bmeddeb/OptiPress/',
		__FILE__,
		'optipress'
	);

	// Set branch for updates (main branch)
	$optipress_update_checker->setBranch( 'main' );

	// Enable release assets (downloads .zip files from GitHub releases)
	$optipress_update_checker->getVcsApi()->enableReleaseAssets();

	// Optional: Private repo authentication (if needed in the future)
	// Define OPTIPRESS_GITHUB_TOKEN in wp-config.php for private repos
	if ( defined( 'OPTIPRESS_GITHUB_TOKEN' ) ) {
		$optipress_update_checker->setAuthentication( OPTIPRESS_GITHUB_TOKEN );
	}
}

/**
 * Load textdomain on init hook (WordPress 6.7+ requirement)
 */
function optipress_load_textdomain() {
	load_plugin_textdomain( 'optipress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'optipress_load_textdomain' );

/**
 * Plugin activation hook
 */
function optipress_activate() {
	// Set default options
	$default_options = array(
		'engine'               => 'auto',      // auto, gd, imagick
		'format'               => 'webp',      // webp, avif
		'quality'              => 85,          // 1-100
		'auto_convert'         => true,
		'keep_originals'       => true,
		'svg_enabled'          => false,
		'enable_content_filter' => true,
		'use_picture_element'  => false,
		'delivery_method'      => 'htaccess',  // htaccess, content_filter, both
		'advanced_previews'    => true,        // Generate previews for TIFF/PSD/RAW
	);

	add_option( 'optipress_options', $default_options );

	// Flush rewrite rules
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'optipress_activate' );

/**
 * Plugin deactivation hook
 */
function optipress_deactivate() {
	// Flush rewrite rules
	flush_rewrite_rules();

	// Clear scheduled events
	$timestamp = wp_next_scheduled( 'optipress_cleanup_security_log' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'optipress_cleanup_security_log' );
	}
}
register_deactivation_hook( __FILE__, 'optipress_deactivate' );

/**
 * Load required files
 */
function optipress_load_files() {
	// Core classes
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-system-check.php';
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-mime-type-map.php';
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-advanced-formats.php';

	// Image conversion engines
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/engines/interface-image-engine.php';
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/engines/class-gd-engine.php';
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/engines/class-imagick-engine.php';
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/engines/class-engine-registry.php';

	// Image converter
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-image-converter.php';

	// SVG sanitizer
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-svg-sanitizer.php';

	// Batch processor
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-batch-processor.php';

	// Content filter for front-end delivery
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-content-filter.php';

	// Admin interface
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-admin-interface.php';

	// Attachment meta box
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-attachment-meta-box.php';
}

/**
 * Initialize plugin
 */
function optipress_init() {
	// Load required files
	optipress_load_files();

	// Backfill missing default for existing installs
	$options = get_option( 'optipress_options', array() );
	if ( ! array_key_exists( 'advanced_previews', $options ) ) {
		$options['advanced_previews'] = true;
		update_option( 'optipress_options', $options );
	}

	// Initialize System Check
	\OptiPress\System_Check::get_instance();

	// Initialize Engine Registry
	\OptiPress\Engines\Engine_Registry::get_instance();

	// Initialize Image Converter
	\OptiPress\Image_Converter::get_instance();

	// Initialize SVG Sanitizer
	\OptiPress\SVG_Sanitizer::get_instance();

	// Initialize Advanced Formats (TIFF/PSD/RAW previews)
	\OptiPress\Advanced_Formats::get_instance();

	// Initialize Content Filter (front-end only)
	if ( ! is_admin() ) {
		\OptiPress\Content_Filter::get_instance();
	}

	// Initialize Batch Processor (admin-only)
	if ( is_admin() ) {
		\OptiPress\Batch_Processor::get_instance();
	}

	// Initialize Admin Interface
	if ( is_admin() ) {
		\OptiPress\Admin_Interface::get_instance();
		\OptiPress\Attachment_Meta_Box::get_instance();
	}

	// Allow supported image formats for upload
	add_filter( 'upload_mimes', 'optipress_allow_supported_mimes' );
	add_filter( 'wp_check_filetype_and_ext', 'optipress_fix_mime_type_validation', 10, 4 );

	// Fix JavaScript plupload settings to allow our formats
	add_filter( 'plupload_default_settings', 'optipress_fix_plupload_mime_types' );
}
add_action( 'plugins_loaded', 'optipress_init' );

/**
 * Allow additional image MIME types that engines can convert
 *
 * Adds MIME types to WordPress upload whitelist based on available engine support.
 * Only adds formats that can be converted to WebP/AVIF by available engines.
 *
 * @param array $mimes Existing MIME types.
 * @return array Modified MIME types.
 */
function optipress_allow_supported_mimes( $mimes ) {
	$registry = \OptiPress\Engines\Engine_Registry::get_instance();
	$supported_formats = $registry->get_all_supported_input_formats();

	// Get upload mimes for supported formats
	$new_mimes = \OptiPress\MIME_Type_Map::get_upload_mimes_for_supported( $supported_formats );

	// Merge with existing mimes (don't override WordPress defaults)
	foreach ( $new_mimes as $ext => $mime ) {
		if ( ! isset( $mimes[ $ext ] ) ) {
			$mimes[ $ext ] = $mime;
		}
	}

	return $mimes;
}

/**
 * Fix WordPress MIME type validation for supported formats
 *
 * WordPress performs additional MIME type validation beyond upload_mimes for security.
 * This filter corrects the MIME type detection for formats that WordPress doesn't
 * natively recognize but our engines can handle.
 *
 * @param array  $file_data File data with type and ext.
 * @param string $file      Full path to the file.
 * @param string $filename  File name.
 * @param array  $mimes     Mime types keyed by extension.
 * @return array Modified file data.
 */
function optipress_fix_mime_type_validation( $file_data, $file, $filename, $mimes ) {
	// If WordPress already detected the type correctly, don't interfere
	if ( ! empty( $file_data['ext'] ) && ! empty( $file_data['type'] ) ) {
		return $file_data;
	}

	// Get file extension
	$file_ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

	// Check if this is a supported format
	$registry = \OptiPress\Engines\Engine_Registry::get_instance();
	$supported_formats = $registry->get_all_supported_input_formats();

	// Get our extension to MIME map
	$extension_map = \OptiPress\MIME_Type_Map::get_extension_to_mime_map( $supported_formats );

	if ( isset( $extension_map[ $file_ext ] ) ) {
		$mime_type = $extension_map[ $file_ext ];

		// Double-check it's in the allowed mimes
		if ( isset( $mimes ) && in_array( $mime_type, $mimes, true ) ) {
			$file_data['ext']  = $file_ext;
			$file_data['type'] = $mime_type;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( sprintf( 'OptiPress: Fixed MIME type for %s: %s', $filename, $mime_type ) );
			}
		}
	}

	return $file_data;
}

/**
 * Fix plupload JavaScript MIME types for client-side validation
 *
 * WordPress's media uploader uses plupload which validates file types in JavaScript
 * BEFORE sending to server. This causes immediate rejection of PSD, TIFF, JP2, etc.
 * We need to add our supported MIME types to the plupload settings.
 *
 * @param array $plupload_settings Plupload settings.
 * @return array Modified settings.
 */
function optipress_fix_plupload_mime_types( $plupload_settings ) {
	// Get supported formats
	$registry = \OptiPress\Engines\Engine_Registry::get_instance();
	$supported_formats = $registry->get_all_supported_input_formats();

	// Get MIME to extension map
	$mime_map = \OptiPress\MIME_Type_Map::get_upload_mimes_for_supported( $supported_formats );

	// Build extension list for plupload
	$extensions = array();
	$mime_types = array();

	foreach ( $mime_map as $ext => $mime ) {
		// Split pipe-separated extensions (e.g., "tiff|tif")
		$ext_parts = explode( '|', $ext );
		$extensions = array_merge( $extensions, $ext_parts );

		// Add MIME type
		if ( ! in_array( $mime, $mime_types, true ) ) {
			$mime_types[] = $mime;
		}
	}

	// Update plupload filters
	if ( ! isset( $plupload_settings['filters'] ) ) {
		$plupload_settings['filters'] = array();
	}

	if ( ! isset( $plupload_settings['filters']['mime_types'] ) ) {
		$plupload_settings['filters']['mime_types'] = array();
	}

	// Add OptiPress formats to allowed list
	$plupload_settings['filters']['mime_types'][] = array(
		'title'      => __( 'OptiPress Supported Images', 'optipress' ),
		'extensions' => implode( ',', array_unique( $extensions ) ),
	);

	// Ensure max_file_size is set (prevents undefined warnings in some WP versions)
	if ( ! isset( $plupload_settings['filters']['max_file_size'] ) ) {
		$plupload_settings['filters']['max_file_size'] = '0b';
	}

	// Debug logging
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( 'OptiPress: Plupload extensions being allowed: ' . implode( ',', array_unique( $extensions ) ) );
		error_log( 'OptiPress: Plupload MIME types: ' . implode( ',', $mime_types ) );
	}

	return $plupload_settings;
}