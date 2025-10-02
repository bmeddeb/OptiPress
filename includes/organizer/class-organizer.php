<?php
/**
 * OptiPress Library Organizer Bootstrap
 *
 * Main class that initializes the Library Organizer system.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer
 *
 * Bootstrap class for the Library Organizer feature.
 */
class OptiPress_Organizer {

	/**
	 * Singleton instance.
	 *
	 * @var OptiPress_Organizer
	 */
	private static $instance = null;

	/**
	 * Post types manager.
	 *
	 * @var OptiPress_Organizer_Post_Types
	 */
	public $post_types;

	/**
	 * Taxonomies manager.
	 *
	 * @var OptiPress_Organizer_Taxonomies
	 */
	public $taxonomies;

	/**
	 * Database manager.
	 *
	 * @var OptiPress_Organizer_Database
	 */
	public $database;

	/**
	 * Item manager.
	 *
	 * @var OptiPress_Organizer_Item_Manager
	 */
	public $items;

	/**
	 * File manager.
	 *
	 * @var OptiPress_Organizer_File_Manager
	 */
	public $files;

	/**
	 * Collection manager.
	 *
	 * @var OptiPress_Organizer_Collection_Manager
	 */
	public $collections;

	/**
	 * Access control.
	 *
	 * @var OptiPress_Organizer_Access_Control
	 */
	public $access;

	/**
	 * Download handler.
	 *
	 * @var OptiPress_Organizer_Download_Handler
	 */
	public $downloads;

	/**
	 * Metadata extractor.
	 *
	 * @var OptiPress_Organizer_Metadata_Extractor
	 */
	public $metadata;

	/**
	 * Admin UI.
	 *
	 * @var OptiPress_Organizer_Admin_UI
	 */
	public $admin_ui;

	/**
	 * REST API.
	 *
	 * @var OptiPress_Organizer_REST_API
	 */
	public $rest_api;

	/**
	 * Get singleton instance.
	 *
	 * @return OptiPress_Organizer
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize the organizer system.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_components();
	}

	/**
	 * Load class dependencies.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$organizer_path = OPTIPRESS_PLUGIN_DIR . 'includes/organizer/';

		require_once $organizer_path . 'class-validator.php';
		require_once $organizer_path . 'class-post-types.php';
		require_once $organizer_path . 'class-taxonomies.php';
		require_once $organizer_path . 'class-database.php';
		require_once $organizer_path . 'class-file-system.php';
		require_once $organizer_path . 'class-item-manager.php';
		require_once $organizer_path . 'class-file-manager.php';
		require_once $organizer_path . 'class-collection-manager.php';
		require_once $organizer_path . 'class-access-control.php';
		require_once $organizer_path . 'class-download-handler.php';
		require_once $organizer_path . 'class-metadata-extractor.php';
		require_once $organizer_path . 'class-admin-ui.php';
		require_once $organizer_path . 'class-rest-api.php';
	}

	/**
	 * Initialize components.
	 *
	 * @return void
	 */
	private function init_components() {
		// Core data structures
		$this->post_types = new OptiPress_Organizer_Post_Types();
		$this->taxonomies = new OptiPress_Organizer_Taxonomies();
		$this->database   = new OptiPress_Organizer_Database();

		// Managers
		$this->items       = new OptiPress_Organizer_Item_Manager();
		$this->files       = new OptiPress_Organizer_File_Manager();
		$this->collections = new OptiPress_Organizer_Collection_Manager();

		// Features
		$this->access   = new OptiPress_Organizer_Access_Control();
		$this->downloads = new OptiPress_Organizer_Download_Handler();
		$this->metadata = new OptiPress_Organizer_Metadata_Extractor();

		// UI & API
		if ( is_admin() ) {
			$this->admin_ui = new OptiPress_Organizer_Admin_UI();
		}

		$this->rest_api = new OptiPress_Organizer_REST_API();
	}

	/**
	 * Get item manager.
	 *
	 * @return OptiPress_Organizer_Item_Manager
	 */
	public function get_item_manager() {
		return $this->items;
	}

	/**
	 * Get file manager.
	 *
	 * @return OptiPress_Organizer_File_Manager
	 */
	public function get_file_manager() {
		return $this->files;
	}

	/**
	 * Get collection manager.
	 *
	 * @return OptiPress_Organizer_Collection_Manager
	 */
	public function get_collection_manager() {
		return $this->collections;
	}
}

/**
 * Get the main OptiPress_Organizer instance.
 *
 * @return OptiPress_Organizer
 */
function optipress_organizer() {
	return OptiPress_Organizer::instance();
}
