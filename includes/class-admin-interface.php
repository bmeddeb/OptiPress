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
	 * Current active tab
	 *
	 * @var string
	 */
	private $active_tab = 'optimization';

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
		add_options_page(
			__( 'OptiPress Settings', 'optipress' ),
			__( 'OptiPress', 'optipress' ),
			'manage_options',
			'optipress-settings',
			array( $this, 'render_settings_page' )
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
		// We need to detect if we're on the tab that contains these fields
		// Optimization tab fields
		if ( isset( $input['auto_convert'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'tab=optimization' ) !== false ) ) {
			$sanitized['auto_convert'] = isset( $input['auto_convert'] ) && $input['auto_convert'];
		}
		if ( isset( $input['keep_originals'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'tab=optimization' ) !== false ) ) {
			$sanitized['keep_originals'] = isset( $input['keep_originals'] ) && $input['keep_originals'];
		}

		// SVG tab fields
		if ( isset( $input['svg_enabled'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'tab=svg' ) !== false ) ) {
			$sanitized['svg_enabled'] = isset( $input['svg_enabled'] ) && $input['svg_enabled'];
		}
		if ( isset( $input['svg_preview_enabled'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'tab=svg' ) !== false ) ) {
			$sanitized['svg_preview_enabled'] = isset( $input['svg_preview_enabled'] ) && $input['svg_preview_enabled'];
		}

		// Front-end delivery options (optimization tab)
		if ( isset( $input['enable_content_filter'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'tab=optimization' ) !== false ) ) {
			$sanitized['enable_content_filter'] = isset( $input['enable_content_filter'] ) && $input['enable_content_filter'];
		}
		if ( isset( $input['use_picture_element'] ) || ( isset( $_POST['_wp_http_referer'] ) && strpos( $_POST['_wp_http_referer'], 'tab=optimization' ) !== false ) ) {
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
		// Settings page assets
		if ( 'settings_page_optipress-settings' === $hook ) {
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

			// Localize upload script
			wp_localize_script(
				'optipress-upload-progress',
				'optipressUpload',
				array(
					'nonce' => wp_create_nonce( 'optipress_upload' ),
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
	 * Render settings page
	 */
	public function render_settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'optipress' ) );
		}

		// Get current tab
		$this->active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'optimization';

		// Get current options
		$options = get_option( 'optipress_options', array() );

		?>
		<div class="wrap optipress-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'optipress_messages' ); ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=optipress-settings&tab=optimization" class="nav-tab <?php echo 'optimization' === $this->active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Image Optimization', 'optipress' ); ?>
				</a>
				<a href="?page=optipress-settings&tab=svg" class="nav-tab <?php echo 'svg' === $this->active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'SVG Support', 'optipress' ); ?>
				</a>
				<a href="?page=optipress-settings&tab=status" class="nav-tab <?php echo 'status' === $this->active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'System Status', 'optipress' ); ?>
				</a>
			</h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'optipress_options' );

				switch ( $this->active_tab ) {
					case 'optimization':
						$this->render_optimization_tab( $options );
						break;

					case 'svg':
						$this->render_svg_tab( $options );
						break;

					case 'status':
						$this->render_status_tab();
						break;
				}

				// Don't show submit button on status tab
				if ( 'status' !== $this->active_tab ) {
					submit_button();
				}
				?>
			</form>
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
