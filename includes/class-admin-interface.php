<?php
/**
 * Admin Interface Class
 *
 * Handles admin settings page and UI.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * Admin_Interface class
 *
 * Creates and manages the plugin settings interface.
 */
class Admin_Interface {

	/**
	 * Singleton instance
	 *
	 * @var Admin_Interface
	 */
	private static $instance = null;

	/**
	 * Current page
	 *
	 * @var string
	 */
	private $current_page = '';

	/**
	 * Get singleton instance
	 *
	 * @return Admin_Interface
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Register settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Enqueue admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_optipress_check_compatibility', array( $this, 'ajax_check_compatibility' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		// Add top-level OptiPress menu (Dashboard as first page)
		add_menu_page(
			__( 'OptiPress Dashboard', 'optipress' ),
			__( 'OptiPress', 'optipress' ),
			'manage_options',
			'optipress',
			array( $this, 'render_dashboard_page' ),
			OPTIPRESS_PLUGIN_URL . 'assets/img/OptiPress-icon.png',
			65
		);

		// Dashboard submenu (same as parent to rename it)
		add_submenu_page(
			'optipress',
			__( 'OptiPress Dashboard', 'optipress' ),
			__( 'Dashboard', 'optipress' ),
			'manage_options',
			'optipress',
			array( $this, 'render_dashboard_page' )
		);

		// Image Optimization submenu
		add_submenu_page(
			'optipress',
			__( 'Image Optimization', 'optipress' ),
			__( 'Image Optimization', 'optipress' ),
			'manage_options',
			'optipress-optimization',
			array( $this, 'render_optimization_page' )
		);

		// SVG Support submenu
		add_submenu_page(
			'optipress',
			__( 'SVG Support', 'optipress' ),
			__( 'SVG Support', 'optipress' ),
			'manage_options',
			'optipress-svg',
			array( $this, 'render_svg_page' )
		);

		// System Status submenu
		add_submenu_page(
			'optipress',
			__( 'System Status', 'optipress' ),
			__( 'System Status', 'optipress' ),
			'manage_options',
			'optipress-status',
			array( $this, 'render_status_page' )
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting(
			'optipress_options',
			'optipress_options',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings before saving
	 *
	 * @param array $input Settings input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		// Get existing options to preserve values not in current form submission
		$existing = get_option( 'optipress_options', array() );

		// Start with existing values
		$sanitized = wp_parse_args( $existing, array(
			'engine'                => 'auto',
			'format'                => 'webp',
			'quality'               => 85,
			'auto_convert'          => true,
			'keep_originals'        => true,
			'svg_enabled'           => false,
			'svg_preview_enabled'   => false,
			'enable_content_filter' => true,
			'use_picture_element'   => false,
			'delivery_method'       => 'htaccess',
		) );

		// Engine (only update if present in input)
		if ( isset( $input['engine'] ) ) {
			$allowed_engines = array( 'auto', 'gd', 'imagick' );
			$sanitized['engine'] = in_array( $input['engine'], $allowed_engines, true )
				? $input['engine']
				: $sanitized['engine'];
		}

		// Format (only update if present in input)
		if ( isset( $input['format'] ) ) {
			$allowed_formats = array( 'webp', 'avif' );
			$sanitized['format'] = in_array( $input['format'], $allowed_formats, true )
				? $input['format']
				: $sanitized['format'];
		}

		// Quality (only update if present in input)
		if ( isset( $input['quality'] ) ) {
			$sanitized['quality'] = max( 1, min( 100, intval( $input['quality'] ) ) );
		}

		// Boolean options - these are tricky because unchecked checkboxes don't send any value
		// We need to detect which page we're on to know which fields to update
		// Optimization page fields
		if ( isset( $input['auto_convert'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'page=optipress-optimization' ) !== false ) ) {
			$sanitized['auto_convert'] = isset( $input['auto_convert'] ) && $input['auto_convert'];
		}
		if ( isset( $input['keep_originals'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'page=optipress-optimization' ) !== false ) ) {
			$sanitized['keep_originals'] = isset( $input['keep_originals'] ) && $input['keep_originals'];
		}

		// SVG page fields
		if ( isset( $input['svg_enabled'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'page=optipress-svg' ) !== false ) ) {
			$sanitized['svg_enabled'] = isset( $input['svg_enabled'] ) && $input['svg_enabled'];
		}
		if ( isset( $input['svg_preview_enabled'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'page=optipress-svg' ) !== false ) ) {
			$sanitized['svg_preview_enabled'] = isset( $input['svg_preview_enabled'] ) && $input['svg_preview_enabled'];
		}

		// Front-end delivery options (optimization page)
		if ( isset( $input['enable_content_filter'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'page=optipress-optimization' ) !== false ) ) {
			$sanitized['enable_content_filter'] = isset( $input['enable_content_filter'] ) && $input['enable_content_filter'];
		}
		if ( isset( $input['use_picture_element'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'page=optipress-optimization' ) !== false ) ) {
			$sanitized['use_picture_element'] = isset( $input['use_picture_element'] ) && $input['use_picture_element'];
		}

		// Delivery method (only update if present in input)
		if ( isset( $input['delivery_method'] ) ) {
			$allowed_delivery = array( 'htaccess', 'content_filter', 'both' );
			$sanitized['delivery_method'] = in_array( $input['delivery_method'], $allowed_delivery, true )
				? $input['delivery_method']
				: $sanitized['delivery_method'];
		}

		return $sanitized;
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Settings page assets (all OptiPress admin pages)
		$optipress_pages = array(
			'toplevel_page_optipress',
			'optipress_page_optipress-optimization',
			'optipress_page_optipress-svg',
			'optipress_page_optipress-status',
		);

		if ( in_array( $hook, $optipress_pages, true ) ) {
			// CSS
			wp_enqueue_style(
				'optipress-admin',
				OPTIPRESS_PLUGIN_URL . 'admin/css/admin-styles.css',
				array(),
				OPTIPRESS_VERSION
			);

			// JavaScript
			wp_enqueue_script(
				'optipress-admin',
				OPTIPRESS_PLUGIN_URL . 'admin/js/admin-settings.bundle.js',
				array( 'jquery', 'optipress-admin-notices' ),
				OPTIPRESS_VERSION,
				true
			);

			// Admin notices helper (provides OptipressNotices)
			wp_enqueue_script(
				'optipress-admin-notices',
				OPTIPRESS_PLUGIN_URL . 'admin/js/admin-notices.bundle.js',
				array( 'jquery', 'wp-data' ),
				OPTIPRESS_VERSION,
				true
			);

			// Batch processor script
			wp_enqueue_script(
				'optipress-batch-processor',
				OPTIPRESS_PLUGIN_URL . 'admin/js/batch-processor.bundle.js',
				array( 'jquery', 'optipress-admin', 'optipress-admin-notices', 'wp-data' ),
				OPTIPRESS_VERSION,
				true
			);

			// Localize script
			wp_localize_script(
				'optipress-admin',
				'optipressAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'optipress_admin' ),
					'i18n'    => array(
						'confirmBatch'      => __( 'Start converting all remaining images? This may take several minutes.', 'optipress' ),
						'confirmRevert'     => __( 'Delete all converted images and revert to originals? This cannot be undone.', 'optipress' ),
						'confirmSvgBatch'   => __( 'Sanitize all existing SVG files? This will overwrite the files with sanitized versions.', 'optipress' ),
						'processing'        => __( 'Processing:', 'optipress' ),
						'reverting'         => __( 'Reverting:', 'optipress' ),
						'sanitizing'        => __( 'Sanitizing:', 'optipress' ),
						'complete'          => __( 'Complete:', 'optipress' ),
						'error'             => __( 'Error', 'optipress' ),
						'unknownError'      => __( 'An unknown error occurred.', 'optipress' ),
						'batchComplete'     => __( 'Successfully converted %d images!', 'optipress' ),
						'revertComplete'    => __( 'Successfully reverted %d images!', 'optipress' ),
						'svgBatchComplete'  => __( 'Successfully sanitized %d SVG files!', 'optipress' ),
					),
				)
			);
		}

		// Upload progress tracking on media pages
		if ( in_array( $hook, array( 'upload.php', 'media-new.php', 'post.php', 'post-new.php' ), true ) ) {
			// Enqueue CSS for upload status
			wp_enqueue_style(
				'optipress-admin',
				OPTIPRESS_PLUGIN_URL . 'admin/css/admin-styles.css',
				array(),
				OPTIPRESS_VERSION
			);

			wp_enqueue_script(
				'optipress-upload-progress',
				OPTIPRESS_PLUGIN_URL . 'admin/js/upload-progress.bundle.js',
				array( 'jquery', 'media-views', 'media-upload', 'wp-util' ),
				OPTIPRESS_VERSION,
				true
			);

		// Get supported formats for JavaScript
		$registry = \OptiPress\Engines\Engine_Registry::get_instance();
		$supported_formats = $registry->get_all_supported_input_formats();

		// Build extension to MIME type map dynamically
		$extension_map = \OptiPress\MIME_Type_Map::get_extension_to_mime_map( $supported_formats );

			// Localize upload script
			wp_localize_script(
				'optipress-upload-progress',
				'optipressUpload',
				array(
				'nonce' => wp_create_nonce( 'optipress_upload' ),
				'supportedMimeTypes' => $supported_formats,
				'extensionMap' => $extension_map,
				)
			);

			// SVG Preview (if enabled)
			$options = get_option( 'optipress_options', array() );
			if ( ! empty( $options['svg_enabled'] ) && ! empty( $options['svg_preview_enabled'] ) ) {
				wp_enqueue_script(
					'optipress-svg-preview',
					OPTIPRESS_PLUGIN_URL . 'admin/js/svg-preview.bundle.js',
					array( 'jquery', 'media-upload' ),
					OPTIPRESS_VERSION,
					true
				);

				// Localize SVG preview script
				wp_localize_script(
					'optipress-svg-preview',
					'optipressSvgPreview',
					array(
						'enabled' => true,
						'nonce'   => wp_create_nonce( 'optipress_svg_preview' ),
					)
				);
			}
		}
	}

	/**
	 * Render dashboard page
	 */
	public function render_dashboard_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'optipress' ) );
		}

		?>
		<div class="wrap optipress-dashboard">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="optipress-dashboard-cards">
				<div class="optipress-card">
					<h2><?php esc_html_e( 'Image Optimization', 'optipress' ); ?></h2>
					<p><?php esc_html_e( 'Configure image conversion settings, formats, and quality options.', 'optipress' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=optipress-optimization' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Go to Settings', 'optipress' ); ?>
					</a>
				</div>

				<div class="optipress-card">
					<h2><?php esc_html_e( 'Thumbnails', 'optipress' ); ?></h2>
					<p><?php esc_html_e( 'Manage thumbnail size profiles and image dimensions.', 'optipress' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=optipress-thumbnails' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Manage Sizes', 'optipress' ); ?>
					</a>
				</div>

				<div class="optipress-card">
					<h2><?php esc_html_e( 'SVG Support', 'optipress' ); ?></h2>
					<p><?php esc_html_e( 'Enable and configure secure SVG file uploads and sanitization.', 'optipress' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=optipress-svg' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Configure SVG', 'optipress' ); ?>
					</a>
				</div>

				<div class="optipress-card">
					<h2><?php esc_html_e( 'System Status', 'optipress' ); ?></h2>
					<p><?php esc_html_e( 'Check system capabilities and supported image formats.', 'optipress' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=optipress-status' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'View Status', 'optipress' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render optimization page
	 */
	public function render_optimization_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'optipress' ) );
		}

		// Get current options
		$options = get_option( 'optipress_options', array() );

		?>
		<div class="wrap optipress-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'optipress_messages' ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'optipress_options' );
				$this->render_optimization_tab( $options );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render SVG page
	 */
	public function render_svg_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'optipress' ) );
		}

		// Get current options
		$options = get_option( 'optipress_options', array() );

		?>
		<div class="wrap optipress-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'optipress_messages' ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'optipress_options' );
				$this->render_svg_tab( $options );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render status page
	 */
	public function render_status_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'optipress' ) );
		}

		?>
		<div class="wrap optipress-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'optipress_messages' ); ?>

			<?php $this->render_status_tab(); ?>
		</div>
		<?php
	}

	/**
	 * Render optimization tab
	 *
	 * @param array $options Current options.
	 */
	private function render_optimization_tab( $options ) {
		$view_file = OPTIPRESS_PLUGIN_DIR . 'admin/views/settings-optimization.php';

		if ( file_exists( $view_file ) ) {
			include $view_file;
		}
	}

	/**
	 * Render SVG tab
	 *
	 * @param array $options Current options.
	 */
	private function render_svg_tab( $options ) {
		$view_file = OPTIPRESS_PLUGIN_DIR . 'admin/views/settings-svg.php';

		if ( file_exists( $view_file ) ) {
			include $view_file;
		}
	}

	/**
	 * Render status tab
	 */
	private function render_status_tab() {
		$view_file = OPTIPRESS_PLUGIN_DIR . 'admin/views/settings-system-status.php';

		if ( file_exists( $view_file ) ) {
			include $view_file;
		}
	}

	/**
	 * AJAX handler for compatibility check
	 */
	public function ajax_check_compatibility() {
		// Verify nonce
		check_ajax_referer( 'optipress_admin', 'nonce' );

		// Verify permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'optipress' ) ) );
		}

		// Get parameters
		$engine = isset( $_POST['engine'] ) ? sanitize_text_field( $_POST['engine'] ) : 'auto';
		$format = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : 'webp';

		// Get engine registry
		$registry = \OptiPress\Engines\Engine_Registry::get_instance();

		// Validate compatibility
		$validation = $registry->validate_engine_format( $engine, $format );

		if ( $validation['valid'] ) {
			wp_send_json_success( array(
				'message' => $validation['message'],
				'valid'   => true,
			) );
		} else {
			wp_send_json_error( array(
				'message' => $validation['message'],
				'valid'   => false,
			) );
		}
	}
}
