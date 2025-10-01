<?php
/**
 * Plugin Name: OptiPress
 * Plugin URI: https://optipress.meddeb.me
 * Description: Image optimization and safe SVG handling for WordPress. Converts images to WebP/AVIF and enables secure SVG uploads.
 * Version:     0.6.0
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
define( 'OPTIPRESS_VERSION', '0.6.0' );
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
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-upload-ui-compat.php';

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

	// Attachment preview panel
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-attachment-preview-panel.php';

	// Size Profiles
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-size-profiles.php';

	// Thumbnailer
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-thumbnailer.php';

	// WP-CLI commands
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-cli.php';
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

	// Initialize Upload UI Compatibility
	\OptiPress\Upload_UI_Compat::get_instance();

	// Initialize Content Filter (front-end only)
	if ( ! is_admin() ) {
		\OptiPress\Content_Filter::get_instance();
	}

	// Initialize Admin Interface (must be before Size_Profiles to create parent menu)
	if ( is_admin() ) {
		\OptiPress\Admin_Interface::get_instance();
		\OptiPress\Attachment_Meta_Box::get_instance();
		\OptiPress\Attachment_Preview_Panel::get_instance();
	}

	// Initialize Size Profiles (responsive image size management - after Admin_Interface)
	\OptiPress\Size_Profiles::get_instance();

	// Initialize Thumbnailer (custom thumbnail generation)
	\OptiPress\Thumbnailer::get_instance();

	// Initialize Batch Processor (admin-only)
	if ( is_admin() ) {
		\OptiPress\Batch_Processor::get_instance();
	}

	// Allow supported image formats for upload
	add_filter( 'upload_mimes', 'optipress_allow_supported_mimes' );
	add_filter( 'wp_check_filetype_and_ext', 'optipress_fix_mime_type_validation', 10, 4 );
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
		}
	}

	return $file_data;
}