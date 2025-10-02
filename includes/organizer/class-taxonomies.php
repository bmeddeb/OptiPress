<?php
/**
 * Taxonomies for OptiPress Library Organizer
 *
 * Registers collections, tags, access levels, and file type taxonomies.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer_Taxonomies
 *
 * Handles registration of taxonomies for the Library Organizer.
 */
class OptiPress_Organizer_Taxonomies {

	/**
	 * Initialize the class and set up hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Register all taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		$this->register_collection_taxonomy();
		$this->register_tag_taxonomy();
		$this->register_access_taxonomy();
		$this->register_file_type_taxonomy();
	}

	/**
	 * Register optipress_collection taxonomy (hierarchical).
	 *
	 * @return void
	 */
	private function register_collection_taxonomy() {
		$labels = array(
			'name'                       => _x( 'Collections', 'taxonomy general name', 'optipress' ),
			'singular_name'              => _x( 'Collection', 'taxonomy singular name', 'optipress' ),
			'search_items'               => __( 'Search Collections', 'optipress' ),
			'popular_items'              => __( 'Popular Collections', 'optipress' ),
			'all_items'                  => __( 'All Collections', 'optipress' ),
			'parent_item'                => __( 'Parent Collection', 'optipress' ),
			'parent_item_colon'          => __( 'Parent Collection:', 'optipress' ),
			'edit_item'                  => __( 'Edit Collection', 'optipress' ),
			'update_item'                => __( 'Update Collection', 'optipress' ),
			'add_new_item'               => __( 'Add New Collection', 'optipress' ),
			'new_item_name'              => __( 'New Collection Name', 'optipress' ),
			'separate_items_with_commas' => __( 'Separate collections with commas', 'optipress' ),
			'add_or_remove_items'        => __( 'Add or remove collections', 'optipress' ),
			'choose_from_most_used'      => __( 'Choose from the most used collections', 'optipress' ),
			'not_found'                  => __( 'No collections found.', 'optipress' ),
			'menu_name'                  => __( 'Collections', 'optipress' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'show_in_rest'      => true,
			'rest_base'         => 'optipress_collections',
		);

		register_taxonomy( 'optipress_collection', array( 'optipress_item' ), $args );
	}

	/**
	 * Register optipress_tag taxonomy (non-hierarchical).
	 *
	 * @return void
	 */
	private function register_tag_taxonomy() {
		$labels = array(
			'name'                       => _x( 'Tags', 'taxonomy general name', 'optipress' ),
			'singular_name'              => _x( 'Tag', 'taxonomy singular name', 'optipress' ),
			'search_items'               => __( 'Search Tags', 'optipress' ),
			'popular_items'              => __( 'Popular Tags', 'optipress' ),
			'all_items'                  => __( 'All Tags', 'optipress' ),
			'edit_item'                  => __( 'Edit Tag', 'optipress' ),
			'update_item'                => __( 'Update Tag', 'optipress' ),
			'add_new_item'               => __( 'Add New Tag', 'optipress' ),
			'new_item_name'              => __( 'New Tag Name', 'optipress' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'optipress' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'optipress' ),
			'choose_from_most_used'      => __( 'Choose from the most used tags', 'optipress' ),
			'not_found'                  => __( 'No tags found.', 'optipress' ),
			'menu_name'                  => __( 'Tags', 'optipress' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => true,
			'show_in_rest'      => true,
			'rest_base'         => 'optipress_tags',
		);

		register_taxonomy( 'optipress_tag', array( 'optipress_item' ), $args );
	}

	/**
	 * Register optipress_access taxonomy (non-hierarchical).
	 *
	 * @return void
	 */
	private function register_access_taxonomy() {
		$labels = array(
			'name'                       => _x( 'Access Levels', 'taxonomy general name', 'optipress' ),
			'singular_name'              => _x( 'Access Level', 'taxonomy singular name', 'optipress' ),
			'search_items'               => __( 'Search Access Levels', 'optipress' ),
			'popular_items'              => __( 'Common Access Levels', 'optipress' ),
			'all_items'                  => __( 'All Access Levels', 'optipress' ),
			'edit_item'                  => __( 'Edit Access Level', 'optipress' ),
			'update_item'                => __( 'Update Access Level', 'optipress' ),
			'add_new_item'               => __( 'Add New Access Level', 'optipress' ),
			'new_item_name'              => __( 'New Access Level Name', 'optipress' ),
			'separate_items_with_commas' => __( 'Separate access levels with commas', 'optipress' ),
			'add_or_remove_items'        => __( 'Add or remove access levels', 'optipress' ),
			'choose_from_most_used'      => __( 'Choose from common access levels', 'optipress' ),
			'not_found'                  => __( 'No access levels found.', 'optipress' ),
			'menu_name'                  => __( 'Access Levels', 'optipress' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'show_in_rest'      => true,
			'rest_base'         => 'optipress_access_levels',
		);

		register_taxonomy( 'optipress_access', array( 'optipress_item' ), $args );
	}

	/**
	 * Register optipress_file_type taxonomy (non-hierarchical).
	 *
	 * @return void
	 */
	private function register_file_type_taxonomy() {
		$labels = array(
			'name'                       => _x( 'File Types', 'taxonomy general name', 'optipress' ),
			'singular_name'              => _x( 'File Type', 'taxonomy singular name', 'optipress' ),
			'search_items'               => __( 'Search File Types', 'optipress' ),
			'popular_items'              => __( 'Common File Types', 'optipress' ),
			'all_items'                  => __( 'All File Types', 'optipress' ),
			'edit_item'                  => __( 'Edit File Type', 'optipress' ),
			'update_item'                => __( 'Update File Type', 'optipress' ),
			'add_new_item'               => __( 'Add New File Type', 'optipress' ),
			'new_item_name'              => __( 'New File Type Name', 'optipress' ),
			'separate_items_with_commas' => __( 'Separate file types with commas', 'optipress' ),
			'add_or_remove_items'        => __( 'Add or remove file types', 'optipress' ),
			'choose_from_most_used'      => __( 'Choose from common file types', 'optipress' ),
			'not_found'                  => __( 'No file types found.', 'optipress' ),
			'menu_name'                  => __( 'File Types', 'optipress' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'show_in_rest'      => true,
			'rest_base'         => 'optipress_file_types',
		);

		register_taxonomy( 'optipress_file_type', array( 'optipress_item' ), $args );
	}
}
