<?php
/**
 * Admin UI for OptiPress Library Organizer
 *
 * Handles admin interface pages and interactions.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer_Admin_UI
 *
 * Manages admin pages and UI elements for the Library Organizer.
 */
class OptiPress_Organizer_Admin_UI {

	/**
	 * Initialize the class and set up hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Capability required to manage organizer admin.
	 *
	 * @return string
	 */
	private function get_capability() {
		/**
		 * Filter capability used for Library Organizer admin.
		 *
		 * @param string $cap Default capability.
		 */
		return apply_filters( 'optipress_organizer_capability', 'manage_options' );
	}

	/**
	 * Add admin menu pages.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		// Placeholder for future submenu registrations under the main OptiPress menu.
		// Ensure capability gating is applied when menus are added.
		// Example (commented):
		// add_submenu_page(
		// 	'optipress',
		// 	__( 'Library', 'optipress' ),
		// 	__( 'Library', 'optipress' ),
		// 	$this->get_capability(),
		// 	'optipress-library',
		// 	array( $this, 'render_library_page' )
		// );
	}

	/**
	 * Render the library page.
	 *
	 * @return void
	 */
	public function render_library_page() {
		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'optipress' ) );
		}
		// TODO: Implement UI in Step 3.6+
		echo '<div class="wrap"><h1>OptiPress Library</h1><p>Coming soon...</p></div>';
	}

	/**
	 * Render the collections management page.
	 *
	 * @return void
	 */
	public function render_collections_page() {
		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'optipress' ) );
		}
		// TODO: Implement in Step 3.6+
	}

	/**
	 * Render the item edit page.
	 *
	 * @param int $item_id Item post ID.
	 * @return void
	 */
	public function render_item_edit_page( $item_id ) {
		if ( ! current_user_can( $this->get_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'optipress' ) );
		}
		// TODO: Implement in Step 3.16
	}

	/**
	 * Enqueue admin assets (CSS, JS).
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only enqueue on Organizer admin pages and when user has capability
		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}
		// TODO: Implement in Step 3.5
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	public function register_ajax_handlers() {
		// TODO: Implement as needed; ensure each handler validates capability:
		// if ( ! current_user_can( $this->get_capability() ) ) wp_send_json_error(...);
	}
}
