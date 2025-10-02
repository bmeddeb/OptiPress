<?php
/**
 * Custom Post Types for OptiPress Library Organizer
 *
 * Registers optipress_item (parent) and optipress_file (child) post types.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer_Post_Types
 *
 * Handles registration of custom post types for the Library Organizer.
 */
class OptiPress_Organizer_Post_Types {

	/**
	 * Initialize the class and set up hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Register custom post types.
	 *
	 * @return void
	 */
	public function register_post_types() {
		$this->register_item_post_type();
		$this->register_file_post_type();
	}

	/**
	 * Register optipress_item post type (parent).
	 *
	 * @return void
	 */
	private function register_item_post_type() {
		$labels = array(
			'name'                  => _x( 'Library Items', 'Post type general name', 'optipress' ),
			'singular_name'         => _x( 'Library Item', 'Post type singular name', 'optipress' ),
			'menu_name'             => _x( 'Library Items', 'Admin Menu text', 'optipress' ),
			'name_admin_bar'        => _x( 'Library Item', 'Add New on Toolbar', 'optipress' ),
			'add_new'               => __( 'Add New', 'optipress' ),
			'add_new_item'          => __( 'Add New Library Item', 'optipress' ),
			'new_item'              => __( 'New Library Item', 'optipress' ),
			'edit_item'             => __( 'Edit Library Item', 'optipress' ),
			'view_item'             => __( 'View Library Item', 'optipress' ),
			'all_items'             => __( 'All Library Items', 'optipress' ),
			'search_items'          => __( 'Search Library Items', 'optipress' ),
			'parent_item_colon'     => __( 'Parent Library Items:', 'optipress' ),
			'not_found'             => __( 'No library items found.', 'optipress' ),
			'not_found_in_trash'    => __( 'No library items found in Trash.', 'optipress' ),
			'featured_image'        => _x( 'Preview Image', 'Overrides the "Featured Image" phrase', 'optipress' ),
			'set_featured_image'    => _x( 'Set preview image', 'Overrides the "Set featured image" phrase', 'optipress' ),
			'remove_featured_image' => _x( 'Remove preview image', 'Overrides the "Remove featured image" phrase', 'optipress' ),
			'use_featured_image'    => _x( 'Use as preview image', 'Overrides the "Use as featured image" phrase', 'optipress' ),
			'archives'              => _x( 'Library Item archives', 'The post type archive label', 'optipress' ),
			'insert_into_item'      => _x( 'Insert into library item', 'Overrides the "Insert into post" phrase', 'optipress' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this library item', 'Overrides the "Uploaded to this post" phrase', 'optipress' ),
			'filter_items_list'     => _x( 'Filter library items list', 'Screen reader text', 'optipress' ),
			'items_list_navigation' => _x( 'Library items list navigation', 'Screen reader text', 'optipress' ),
			'items_list'            => _x( 'Library items list', 'Screen reader text', 'optipress' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false, // Will be added as submenu under OptiPress
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'optipress-library' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'show_in_rest'       => true,
			'rest_base'          => 'optipress_items',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
		);

		register_post_type( 'optipress_item', $args );
	}

	/**
	 * Register optipress_file post type (child).
	 *
	 * @return void
	 */
	private function register_file_post_type() {
		$labels = array(
			'name'                  => _x( 'Library Files', 'Post type general name', 'optipress' ),
			'singular_name'         => _x( 'Library File', 'Post type singular name', 'optipress' ),
			'menu_name'             => _x( 'Library Files', 'Admin Menu text', 'optipress' ),
			'name_admin_bar'        => _x( 'Library File', 'Add New on Toolbar', 'optipress' ),
			'add_new'               => __( 'Add New', 'optipress' ),
			'add_new_item'          => __( 'Add New Library File', 'optipress' ),
			'new_item'              => __( 'New Library File', 'optipress' ),
			'edit_item'             => __( 'Edit Library File', 'optipress' ),
			'view_item'             => __( 'View Library File', 'optipress' ),
			'all_items'             => __( 'All Library Files', 'optipress' ),
			'search_items'          => __( 'Search Library Files', 'optipress' ),
			'parent_item_colon'     => __( 'Parent Library Item:', 'optipress' ),
			'not_found'             => __( 'No library files found.', 'optipress' ),
			'not_found_in_trash'    => __( 'No library files found in Trash.', 'optipress' ),
			'filter_items_list'     => _x( 'Filter library files list', 'Screen reader text', 'optipress' ),
			'items_list_navigation' => _x( 'Library files list navigation', 'Screen reader text', 'optipress' ),
			'items_list'            => _x( 'Library files list', 'Screen reader text', 'optipress' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false, // Hidden from admin menu - accessed via parent item
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'custom-fields' ),
			'show_in_rest'       => true,
			'rest_base'          => 'optipress_files',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
		);

		register_post_type( 'optipress_file', $args );
	}
}
