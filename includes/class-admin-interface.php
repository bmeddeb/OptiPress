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
		$sanitized = array();

		// Engine
		$allowed_engines = array( 'auto', 'gd', 'imagick' );
		$sanitized['engine'] = isset( $input['engine'] ) && in_array( $input['engine'], $allowed_engines, true )
			? $input['engine']
			: 'auto';

		// Format
		$allowed_formats = array( 'webp', 'avif' );
		$sanitized['format'] = isset( $input['format'] ) && in_array( $input['format'], $allowed_formats, true )
			? $input['format']
			: 'webp';

		// Quality
		$sanitized['quality'] = isset( $input['quality'] )
			? max( 1, min( 100, intval( $input['quality'] ) ) )
			: 85;

		// Boolean options
		$sanitized['auto_convert']    = isset( $input['auto_convert'] ) && $input['auto_convert'];
		$sanitized['keep_originals']  = isset( $input['keep_originals'] ) && $input['keep_originals'];
		$sanitized['svg_enabled']     = isset( $input['svg_enabled'] ) && $input['svg_enabled'];

		// Delivery method
		$allowed_delivery = array( 'htaccess', 'content_filter', 'both' );
		$sanitized['delivery_method'] = isset( $input['delivery_method'] ) && in_array( $input['delivery_method'], $allowed_delivery, true )
			? $input['delivery_method']
			: 'htaccess';

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
				OPTIPRESS_PLUGIN_URL . 'admin/js/admin-settings.js',
				array( 'jquery' ),
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
				)
			);
		}

		// Upload progress tracking on media pages
		if ( in_array( $hook, array( 'upload.php', 'media-new.php', 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_script(
				'optipress-upload-progress',
				OPTIPRESS_PLUGIN_URL . 'admin/js/upload-progress.js',
				array( 'jquery', 'media-upload' ),
				OPTIPRESS_VERSION,
				true
			);
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