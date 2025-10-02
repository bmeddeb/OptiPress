<?php
/**
 * Item Manager for OptiPress Library Organizer
 *
 * Handles CRUD operations for library items.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer_Item_Manager
 *
 * Manages library items (optipress_item posts).
 */
class OptiPress_Organizer_Item_Manager {

	/**
	 * Create a new library item.
	 *
	 * @param array $data Item data (title, description, collection_id, etc.).
	 * @return int|WP_Error Item ID on success, WP_Error on failure.
	 */
	public function create_item( $data ) {
		// Validate required fields
		if ( empty( $data['title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Item title is required.', 'optipress' ) );
		}

		// Prepare post data
		$post_data = array(
			'post_type'    => 'optipress_item',
			'post_title'   => sanitize_text_field( $data['title'] ),
			'post_content' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
			'post_status'  => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'publish',
			'post_author'  => isset( $data['author_id'] ) ? absint( $data['author_id'] ) : get_current_user_id(),
		);

		// Create the post
		$item_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		// Assign to collection if specified
		if ( ! empty( $data['collection_id'] ) ) {
			wp_set_post_terms( $item_id, array( absint( $data['collection_id'] ) ), 'optipress_collection' );
		}

		// Assign tags if specified
		if ( ! empty( $data['tags'] ) ) {
			if ( is_array( $data['tags'] ) ) {
				wp_set_post_terms( $item_id, $data['tags'], 'optipress_tag' );
			}
		}

		// Set access level if specified
		if ( ! empty( $data['access_level'] ) ) {
			wp_set_post_terms( $item_id, array( sanitize_key( $data['access_level'] ) ), 'optipress_access' );
		}

		// Set file type if specified
		if ( ! empty( $data['file_type'] ) ) {
			wp_set_post_terms( $item_id, array( sanitize_key( $data['file_type'] ) ), 'optipress_file_type' );
		}

		// Store metadata
		if ( ! empty( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
			update_post_meta( $item_id, '_optipress_metadata', $data['metadata'] );
		}

		// Set display file if specified
		if ( ! empty( $data['display_file_id'] ) ) {
			update_post_meta( $item_id, '_optipress_display_file', absint( $data['display_file_id'] ) );
		}

		// Initialize view count
		update_post_meta( $item_id, '_optipress_view_count', 0 );

		// Allow plugins to hook into item creation
		do_action( 'optipress_organizer_item_created', $item_id, $data );

		return $item_id;
	}

	/**
	 * Get an item by ID.
	 *
	 * @param int $item_id Item post ID.
	 * @return WP_Post|null Item post object or null.
	 */
	public function get_item( $item_id ) {
		if ( ! $item_id ) {
			return null;
		}

		$post = get_post( $item_id );

		// Verify it's an optipress_item post type
		if ( ! $post || $post->post_type !== 'optipress_item' ) {
			return null;
		}

		return $post;
	}

	/**
	 * Get item with full details (metadata, taxonomies, files).
	 *
	 * @param int $item_id Item post ID.
	 * @return array|null Item data array or null on failure.
	 */
	public function get_item_details( $item_id ) {
		$item = $this->get_item( $item_id );

		if ( ! $item ) {
			return null;
		}

		// Get collections
		$collections = wp_get_post_terms( $item_id, 'optipress_collection' );

		// Get tags
		$tags = wp_get_post_terms( $item_id, 'optipress_tag' );

		// Get access level
		$access = wp_get_post_terms( $item_id, 'optipress_access', array( 'fields' => 'names' ) );

		// Get file type
		$file_type = wp_get_post_terms( $item_id, 'optipress_file_type', array( 'fields' => 'names' ) );

		// Get metadata
		$metadata = get_post_meta( $item_id, '_optipress_metadata', true );

		// Get display file
		$display_file_id = get_post_meta( $item_id, '_optipress_display_file', true );

		// Get view count
		$view_count = get_post_meta( $item_id, '_optipress_view_count', true );

		// Get file count
		$file_count = $this->get_file_count( $item_id );

		return array(
			'id'              => $item->ID,
			'title'           => $item->post_title,
			'description'     => $item->post_content,
			'status'          => $item->post_status,
			'author_id'       => $item->post_author,
			'date_created'    => $item->post_date,
			'date_modified'   => $item->post_modified,
			'collections'     => $collections,
			'tags'            => $tags,
			'access_level'    => ! empty( $access ) ? $access[0] : 'public',
			'file_type'       => ! empty( $file_type ) ? $file_type[0] : '',
			'metadata'        => $metadata ? $metadata : array(),
			'display_file_id' => $display_file_id,
			'view_count'      => $view_count ? intval( $view_count ) : 0,
			'file_count'      => $file_count,
		);
	}

	/**
	 * Get file count for an item.
	 *
	 * @param int $item_id Item post ID.
	 * @return int File count.
	 */
	private function get_file_count( $item_id ) {
		$args = array(
			'post_type'      => 'optipress_file',
			'post_parent'    => $item_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );

		return $query->found_posts;
	}

	/**
	 * Update an existing item.
	 *
	 * @param int   $item_id Item post ID.
	 * @param array $data Updated data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_item( $item_id, $data ) {
		// Verify item exists
		$item = $this->get_item( $item_id );
		if ( ! $item ) {
			return new WP_Error( 'item_not_found', __( 'Item not found.', 'optipress' ) );
		}

		// Prepare post data for update
		$post_data = array(
			'ID' => $item_id,
		);

		// Update title if provided
		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['title'] );
		}

		// Update description if provided
		if ( isset( $data['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $data['description'] );
		}

		// Update status if provided
		if ( isset( $data['status'] ) ) {
			$post_data['post_status'] = sanitize_key( $data['status'] );
		}

		// Update the post if there are changes
		if ( count( $post_data ) > 1 ) {
			$result = wp_update_post( $post_data, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update collection if specified
		if ( isset( $data['collection_id'] ) ) {
			if ( empty( $data['collection_id'] ) ) {
				// Remove all collections
				wp_set_post_terms( $item_id, array(), 'optipress_collection' );
			} else {
				wp_set_post_terms( $item_id, array( absint( $data['collection_id'] ) ), 'optipress_collection' );
			}
		}

		// Update tags if specified
		if ( isset( $data['tags'] ) ) {
			if ( is_array( $data['tags'] ) ) {
				wp_set_post_terms( $item_id, $data['tags'], 'optipress_tag' );
			}
		}

		// Update access level if specified
		if ( isset( $data['access_level'] ) ) {
			wp_set_post_terms( $item_id, array( sanitize_key( $data['access_level'] ) ), 'optipress_access' );
		}

		// Update file type if specified
		if ( isset( $data['file_type'] ) ) {
			wp_set_post_terms( $item_id, array( sanitize_key( $data['file_type'] ) ), 'optipress_file_type' );
		}

		// Update metadata if specified
		if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
			update_post_meta( $item_id, '_optipress_metadata', $data['metadata'] );
		}

		// Update display file if specified
		if ( isset( $data['display_file_id'] ) ) {
			if ( empty( $data['display_file_id'] ) ) {
				delete_post_meta( $item_id, '_optipress_display_file' );
			} else {
				update_post_meta( $item_id, '_optipress_display_file', absint( $data['display_file_id'] ) );
			}
		}

		// Allow plugins to hook into item update
		do_action( 'optipress_organizer_item_updated', $item_id, $data );

		return true;
	}

	/**
	 * Delete an item.
	 *
	 * @param int  $item_id Item post ID.
	 * @param bool $delete_files Whether to delete associated files.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_item( $item_id, $delete_files = false ) {
		// Verify item exists
		$item = $this->get_item( $item_id );
		if ( ! $item ) {
			return new WP_Error( 'item_not_found', __( 'Item not found.', 'optipress' ) );
		}

		// Allow plugins to hook before deletion
		do_action( 'optipress_organizer_before_delete_item', $item_id, $delete_files );

		// Get all child file posts
		$file_posts = get_children(
			array(
				'post_parent' => $item_id,
				'post_type'   => 'optipress_file',
			)
		);

		// Delete child file posts
		foreach ( $file_posts as $file_post ) {
			// Delete the post
			wp_delete_post( $file_post->ID, true ); // Force delete (skip trash)
		}

		// Delete physical files if requested
		if ( $delete_files ) {
			$file_system = new OptiPress_Organizer_File_System();
			$file_system->delete_item_files( $item_id );
		}

		// Delete the item post
		$result = wp_delete_post( $item_id, true ); // Force delete (skip trash)

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete item.', 'optipress' ) );
		}

		// Allow plugins to hook after deletion
		do_action( 'optipress_organizer_item_deleted', $item_id );

		return true;
	}

	/**
	 * Trash an item (soft delete).
	 *
	 * @param int $item_id Item post ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function trash_item( $item_id ) {
		// Verify item exists
		$item = $this->get_item( $item_id );
		if ( ! $item ) {
			return new WP_Error( 'item_not_found', __( 'Item not found.', 'optipress' ) );
		}

		// Trash the post
		$result = wp_trash_post( $item_id );

		if ( ! $result ) {
			return new WP_Error( 'trash_failed', __( 'Failed to trash item.', 'optipress' ) );
		}

		// Trash child file posts as well
		$file_posts = get_children(
			array(
				'post_parent' => $item_id,
				'post_type'   => 'optipress_file',
			)
		);

		foreach ( $file_posts as $file_post ) {
			wp_trash_post( $file_post->ID );
		}

		return true;
	}

	/**
	 * Restore an item from trash.
	 *
	 * @param int $item_id Item post ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function restore_item( $item_id ) {
		$result = wp_untrash_post( $item_id );

		if ( ! $result ) {
			return new WP_Error( 'restore_failed', __( 'Failed to restore item.', 'optipress' ) );
		}

		// Restore child file posts as well
		$file_posts = get_children(
			array(
				'post_parent'    => $item_id,
				'post_type'      => 'optipress_file',
				'post_status'    => 'trash',
			)
		);

		foreach ( $file_posts as $file_post ) {
			wp_untrash_post( $file_post->ID );
		}

		return true;
	}

	/**
	 * Query items with filters.
	 *
	 * @param array $args Query arguments.
	 * @return WP_Query Query result.
	 */
	public function query_items( $args = array() ) {
		// Default query args
		$defaults = array(
			'post_type'      => 'optipress_item',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Merge with provided args
		$query_args = wp_parse_args( $args, $defaults );

		// Handle collection filter
		if ( ! empty( $args['collection_id'] ) ) {
			$query_args['tax_query'] = isset( $query_args['tax_query'] ) ? $query_args['tax_query'] : array();
			$query_args['tax_query'][] = array(
				'taxonomy' => 'optipress_collection',
				'field'    => 'term_id',
				'terms'    => absint( $args['collection_id'] ),
			);
		}

		// Handle tag filter
		if ( ! empty( $args['tag_id'] ) ) {
			$query_args['tax_query'] = isset( $query_args['tax_query'] ) ? $query_args['tax_query'] : array();
			$query_args['tax_query'][] = array(
				'taxonomy' => 'optipress_tag',
				'field'    => 'term_id',
				'terms'    => absint( $args['tag_id'] ),
			);
		}

		// Handle access level filter
		if ( ! empty( $args['access_level'] ) ) {
			$query_args['tax_query'] = isset( $query_args['tax_query'] ) ? $query_args['tax_query'] : array();
			$query_args['tax_query'][] = array(
				'taxonomy' => 'optipress_access',
				'field'    => 'slug',
				'terms'    => sanitize_key( $args['access_level'] ),
			);
		}

		// Handle file type filter
		if ( ! empty( $args['file_type'] ) ) {
			$query_args['tax_query'] = isset( $query_args['tax_query'] ) ? $query_args['tax_query'] : array();
			$query_args['tax_query'][] = array(
				'taxonomy' => 'optipress_file_type',
				'field'    => 'slug',
				'terms'    => sanitize_key( $args['file_type'] ),
			);
		}

		// Handle search
		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		// Handle author filter
		if ( ! empty( $args['author_id'] ) ) {
			$query_args['author'] = absint( $args['author_id'] );
		}

		// Handle date range filter
		if ( ! empty( $args['date_after'] ) || ! empty( $args['date_before'] ) ) {
			$query_args['date_query'] = array();

			if ( ! empty( $args['date_after'] ) ) {
				$query_args['date_query']['after'] = sanitize_text_field( $args['date_after'] );
			}

			if ( ! empty( $args['date_before'] ) ) {
				$query_args['date_query']['before'] = sanitize_text_field( $args['date_before'] );
			}
		}

		// Handle meta query for custom fields
		if ( ! empty( $args['meta_key'] ) && ! empty( $args['meta_value'] ) ) {
			$query_args['meta_query'] = isset( $query_args['meta_query'] ) ? $query_args['meta_query'] : array();
			$query_args['meta_query'][] = array(
				'key'     => sanitize_key( $args['meta_key'] ),
				'value'   => sanitize_text_field( $args['meta_value'] ),
				'compare' => isset( $args['meta_compare'] ) ? $args['meta_compare'] : '=',
			);
		}

		// Set tax_query relation if multiple taxonomies
		if ( ! empty( $query_args['tax_query'] ) && count( $query_args['tax_query'] ) > 1 ) {
			$query_args['tax_query']['relation'] = 'AND';
		}

		// Allow filtering of query args
		$query_args = apply_filters( 'optipress_organizer_query_items_args', $query_args, $args );

		// Execute query
		$query = new WP_Query( $query_args );

		return $query;
	}

	/**
	 * Get items count with filters.
	 *
	 * @param array $args Query arguments.
	 * @return int Item count.
	 */
	public function count_items( $args = array() ) {
		$args['posts_per_page'] = -1;
		$args['fields'] = 'ids';

		$query = $this->query_items( $args );

		return $query->found_posts;
	}

	/**
	 * Get items in a collection (including subcollections if recursive).
	 *
	 * @param int  $collection_id Collection term ID.
	 * @param bool $recursive Whether to include subcollections.
	 * @param array $args Additional query arguments.
	 * @return WP_Query Query result.
	 */
	public function get_items_by_collection( $collection_id, $recursive = false, $args = array() ) {
		$args['collection_id'] = $collection_id;

		if ( $recursive ) {
			// Get all child collections
			$child_collections = get_term_children( $collection_id, 'optipress_collection' );

			if ( ! empty( $child_collections ) && ! is_wp_error( $child_collections ) ) {
				// Include parent and all children
				$collection_ids = array_merge( array( $collection_id ), $child_collections );

				// Override the tax_query to include all collections
				unset( $args['collection_id'] );
				$args['tax_query'] = array(
					array(
						'taxonomy' => 'optipress_collection',
						'field'    => 'term_id',
						'terms'    => $collection_ids,
					),
				);
			}
		}

		return $this->query_items( $args );
	}

	/**
	 * Move item to a collection.
	 *
	 * @param int $item_id Item post ID.
	 * @param int $collection_id Collection term ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function move_to_collection( $item_id, $collection_id ) {
		// TODO: Implement later
		return new WP_Error( 'not_implemented', 'Method not yet implemented' );
	}

	/**
	 * Set the display file for an item.
	 *
	 * @param int $item_id Item post ID.
	 * @param int $file_id File post ID.
	 * @return bool Success status.
	 */
	public function set_display_file( $item_id, $file_id ) {
		// TODO: Implement later
		return false;
	}
}
