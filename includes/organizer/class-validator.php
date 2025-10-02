<?php
/**
 * Validator for OptiPress Library Organizer
 *
 * Provides validation and sanitization helpers.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer_Validator
 *
 * Handles input validation and sanitization.
 */
class OptiPress_Organizer_Validator {

	/**
	 * Validate item data.
	 *
	 * @param array $data Item data to validate.
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_item_data( $data ) {
		// Required: title
		if ( empty( $data['title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Item title is required.', 'optipress' ) );
		}

		// Validate title length
		if ( strlen( $data['title'] ) > 255 ) {
			return new WP_Error( 'title_too_long', __( 'Item title must be 255 characters or less.', 'optipress' ) );
		}

		// Validate status if provided
		if ( isset( $data['status'] ) ) {
			$valid_statuses = array( 'publish', 'draft', 'private', 'pending' );
			if ( ! in_array( $data['status'], $valid_statuses, true ) ) {
				return new WP_Error( 'invalid_status', __( 'Invalid post status.', 'optipress' ) );
			}
		}

		// Validate collection_id if provided
		if ( ! empty( $data['collection_id'] ) ) {
			$collection = get_term( $data['collection_id'], 'optipress_collection' );
			if ( ! $collection || is_wp_error( $collection ) ) {
				return new WP_Error( 'invalid_collection', __( 'Collection does not exist.', 'optipress' ) );
			}
		}

		return true;
	}

	/**
	 * Validate file data.
	 *
	 * @param int    $item_id Item post ID.
	 * @param string $file_path File path.
	 * @param string $variant_type Variant type.
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_file_data( $item_id, $file_path, $variant_type ) {
		// Validate item ID
		if ( ! $item_id ) {
			return new WP_Error( 'missing_item_id', __( 'Item ID is required.', 'optipress' ) );
		}

		$item = get_post( $item_id );
		if ( ! $item || $item->post_type !== 'optipress_item' ) {
			return new WP_Error( 'invalid_item', __( 'Item does not exist.', 'optipress' ) );
		}

		// Validate file path
		if ( empty( $file_path ) ) {
			return new WP_Error( 'missing_file_path', __( 'File path is required.', 'optipress' ) );
		}

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File does not exist at the specified path.', 'optipress' ) );
		}

		if ( ! is_readable( $file_path ) ) {
			return new WP_Error( 'file_not_readable', __( 'File is not readable.', 'optipress' ) );
		}

		// Validate variant type
		if ( empty( $variant_type ) ) {
			return new WP_Error( 'missing_variant_type', __( 'Variant type is required.', 'optipress' ) );
		}

		$valid_variants = array( 'original', 'preview', 'thumbnail', 'medium', 'large', 'full' );
		if ( ! in_array( $variant_type, $valid_variants, true ) ) {
			// Allow custom variant types, just sanitize
			$variant_type = sanitize_key( $variant_type );
		}

		return true;
	}

	/**
	 * Validate file size.
	 *
	 * @param string $file_path File path.
	 * @param int    $max_size Maximum size in bytes (default: 100MB).
	 * @return true|WP_Error True if valid, WP_Error if too large.
	 */
	public static function validate_file_size( $file_path, $max_size = 104857600 ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File does not exist.', 'optipress' ) );
		}

		$file_size = filesize( $file_path );

		if ( $file_size > $max_size ) {
			$max_mb = $max_size / 1048576;
			return new WP_Error(
				'file_too_large',
				sprintf( __( 'File size exceeds the maximum allowed size of %d MB.', 'optipress' ), $max_mb )
			);
		}

		return true;
	}

	/**
	 * Sanitize item data.
	 *
	 * @param array $data Item data to sanitize.
	 * @return array Sanitized data.
	 */
	public static function sanitize_item_data( $data ) {
		$sanitized = array();

		if ( isset( $data['title'] ) ) {
			$sanitized['title'] = sanitize_text_field( $data['title'] );
		}

		if ( isset( $data['description'] ) ) {
			$sanitized['description'] = wp_kses_post( $data['description'] );
		}

		if ( isset( $data['status'] ) ) {
			$sanitized['status'] = sanitize_key( $data['status'] );
		}

		if ( isset( $data['author_id'] ) ) {
			$sanitized['author_id'] = absint( $data['author_id'] );
		}

		if ( isset( $data['collection_id'] ) ) {
			$sanitized['collection_id'] = absint( $data['collection_id'] );
		}

		if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
			$sanitized['tags'] = array_map( 'sanitize_text_field', $data['tags'] );
		}

		if ( isset( $data['access_level'] ) ) {
			$sanitized['access_level'] = sanitize_key( $data['access_level'] );
		}

		if ( isset( $data['file_type'] ) ) {
			$sanitized['file_type'] = sanitize_key( $data['file_type'] );
		}

		if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
			$sanitized['metadata'] = self::sanitize_metadata( $data['metadata'] );
		}

		if ( isset( $data['display_file_id'] ) ) {
			$sanitized['display_file_id'] = absint( $data['display_file_id'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize metadata array.
	 *
	 * @param array $metadata Metadata to sanitize.
	 * @return array Sanitized metadata.
	 */
	private static function sanitize_metadata( $metadata ) {
		if ( ! is_array( $metadata ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $metadata as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_metadata( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $key ] = $value;
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Validate collection data.
	 *
	 * @param string $name Collection name.
	 * @param int    $parent_id Parent collection ID.
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_collection_data( $name, $parent_id = 0 ) {
		// Validate name
		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', __( 'Collection name is required.', 'optipress' ) );
		}

		if ( strlen( $name ) > 200 ) {
			return new WP_Error( 'name_too_long', __( 'Collection name must be 200 characters or less.', 'optipress' ) );
		}

		// Validate parent if provided
		if ( $parent_id ) {
			$parent = get_term( $parent_id, 'optipress_collection' );
			if ( ! $parent || is_wp_error( $parent ) ) {
				return new WP_Error( 'invalid_parent', __( 'Parent collection does not exist.', 'optipress' ) );
			}
		}

		return true;
	}

	/**
	 * Check if user has permission for operation.
	 *
	 * @param string $capability Required capability.
	 * @param int    $user_id User ID (0 for current user).
	 * @return true|WP_Error True if has permission, WP_Error if not.
	 */
	public static function check_permission( $capability, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! user_can( $user_id, $capability ) ) {
			return new WP_Error( 'permission_denied', __( 'You do not have permission to perform this action.', 'optipress' ) );
		}

		return true;
	}

	/**
	 * Validate nonce.
	 *
	 * @param string $nonce Nonce value.
	 * @param string $action Nonce action.
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_nonce( $nonce, $action ) {
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'optipress' ) );
		}

		return true;
	}
}
