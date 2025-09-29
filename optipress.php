<?php
/**
 * Plugin Name: OptiPress
 * Plugin URI: https://optipress.meddeb.me
 * Description: Image optimization and safe SVG handling for WordPress. Converts images to WebP/AVIF and enables secure SVG uploads.
 * Version:     0.3.0
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
define( 'OPTIPRESS_VERSION', '0.3.0' );
define( 'OPTIPRESS_PLUGIN_FILE', __FILE__ );
define( 'OPTIPRESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPTIPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader
if ( file_exists( OPTIPRESS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once OPTIPRESS_PLUGIN_DIR . 'vendor/autoload.php';
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
		'engine'          => 'auto',      // auto, gd, imagick
		'format'          => 'webp',      // webp, avif
		'quality'         => 85,          // 1-100
		'auto_convert'    => true,
		'keep_originals'  => true,
		'svg_enabled'     => false,
		'delivery_method' => 'htaccess',  // htaccess, content_filter, both
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
}
register_deactivation_hook( __FILE__, 'optipress_deactivate' );

/**
 * Load required files
 */
function optipress_load_files() {
	// Core classes
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-system-check.php';

	// Image conversion engines
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/engines/interface-image-engine.php';
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/engines/class-gd-engine.php';
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/engines/class-imagick-engine.php';
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/engines/class-engine-registry.php';

	// Image converter
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-image-converter.php';

	// SVG sanitizer
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-svg-sanitizer.php';

	// Admin interface
	require_once OPTIPRESS_PLUGIN_DIR . 'includes/class-admin-interface.php';

	// Additional classes will be loaded here as they are developed:
	// - Batch_Processor
}

/**
 * Initialize plugin
 */
function optipress_init() {
	// Load required files
	optipress_load_files();

	// Initialize System Check
	\OptiPress\System_Check::get_instance();

	// Initialize Engine Registry
	\OptiPress\Engines\Engine_Registry::get_instance();

	// Initialize Image Converter
	\OptiPress\Image_Converter::get_instance();

	// Initialize SVG Sanitizer
	\OptiPress\SVG_Sanitizer::get_instance();

	// Initialize Admin Interface
	if ( is_admin() ) {
		\OptiPress\Admin_Interface::get_instance();
	}

	// Additional initialization will be added in subsequent phases
}
add_action( 'plugins_loaded', 'optipress_init' );