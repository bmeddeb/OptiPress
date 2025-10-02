<?php
/**
 * File System Management for OptiPress Library Organizer
 *
 * Handles file organization and directory structure in uploads folder.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer_File_System
 *
 * Manages file system operations for organized file storage.
 */
class OptiPress_Organizer_File_System {

	/**
	 * Base directory for OptiPress files.
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$upload_dir     = wp_upload_dir();
		$this->base_dir = trailingslashit( $upload_dir['basedir'] ) . 'optipress/';
	}

	/**
	 * Get or create directory for an item.
	 *
	 * @param int  $item_id Item post ID.
	 * @param bool $create Whether to create if doesn't exist.
	 * @return string|false Directory path or false on failure.
	 */
	public function get_item_directory( $item_id, $create = true ) {
		if ( ! $item_id ) {
			return false;
		}

		// Get collections for this item to organize by collection
		$collections = wp_get_post_terms( $item_id, 'optipress_collection', array( 'fields' => 'slugs' ) );

		// Use first collection or 'uncategorized'
		$collection_slug = ! empty( $collections ) && ! is_wp_error( $collections ) ? $collections[0] : 'uncategorized';

		// Build path: uploads/optipress/collections/{collection-slug}/{item-id}/
		$item_dir = $this->base_dir . 'collections/' . $collection_slug . '/' . $item_id . '/';

		// Create directory if it doesn't exist and $create is true
		if ( $create && ! file_exists( $item_dir ) ) {
			if ( ! wp_mkdir_p( $item_dir ) ) {
				return false;
			}

			// Create subdirectories
			$subdirs = array( 'original', 'preview', 'sizes' );
			foreach ( $subdirs as $subdir ) {
				wp_mkdir_p( $item_dir . $subdir );
			}

			// Add .htaccess for protection (will be served via PHP handler)
			$htaccess_content  = "# OptiPress Library Organizer\n";
			$htaccess_content .= "# Files are served via secure download handler\n";
			$htaccess_content .= "Order deny,allow\n";
			$htaccess_content .= "Deny from all\n";

			$htaccess_written = @file_put_contents( $item_dir . '.htaccess', $htaccess_content );
			if ( false === $htaccess_written ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( '[OptiPress Organizer] Failed to write .htaccess in ' . $item_dir );
				}
			}

			// Add index.php to prevent directory listing on non-Apache environments
			$index_path = $item_dir . 'index.php';
			if ( ! file_exists( $index_path ) ) {
				$index_written = @file_put_contents( $index_path, "<?php\n// Silence is golden.\n" );
				if ( false === $index_written ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log( '[OptiPress Organizer] Failed to write index.php in ' . $item_dir );
					}
				}
			}
		}

		return $item_dir;
	}

	/**
	 * Get file path for a specific variant.
	 *
	 * @param int    $item_id Item post ID.
	 * @param string $variant_type Variant type (original, preview, thumbnail, etc.).
	 * @param string $filename File name.
	 * @return string Full file path.
	 */
	public function get_file_path( $item_id, $variant_type, $filename ) {
		$item_dir = $this->get_item_directory( $item_id, false );

		if ( ! $item_dir ) {
			return '';
		}

		// Determine subdirectory based on variant type
		$subdir = $this->get_variant_subdirectory( $variant_type );

		return $item_dir . $subdir . '/' . basename( $filename );
	}

	/**
	 * Get subdirectory for a variant type.
	 *
	 * @param string $variant_type Variant type.
	 * @return string Subdirectory name.
	 */
	private function get_variant_subdirectory( $variant_type ) {
		switch ( $variant_type ) {
			case 'original':
				return 'original';
			case 'preview':
				return 'preview';
			default:
				// All other sizes go in 'sizes' directory
				return 'sizes';
		}
	}

	/**
	 * Move/copy file to organized structure.
	 *
	 * @param string $source_path Source file path.
	 * @param int    $item_id Item post ID.
	 * @param string $variant_type Variant type.
	 * @return string|false New file path or false on failure.
	 */
	public function organize_file( $source_path, $item_id, $variant_type ) {
		if ( ! file_exists( $source_path ) ) {
			return false;
		}

		// Get destination directory
		$item_dir = $this->get_item_directory( $item_id, true );
		if ( ! $item_dir ) {
			return false;
		}

		// Get destination path
		$filename = basename( $source_path );
		$dest_path = $this->get_file_path( $item_id, $variant_type, $filename );

		// Copy file to new location
		if ( ! copy( $source_path, $dest_path ) ) {
			return false;
		}

		// Set proper permissions
		chmod( $dest_path, 0644 );

		return $dest_path;
	}

	/**
	 * Delete all files for an item.
	 *
	 * @param int $item_id Item post ID.
	 * @return bool Success status.
	 */
	public function delete_item_files( $item_id ) {
		$item_dir = $this->get_item_directory( $item_id, false );

		if ( ! $item_dir || ! file_exists( $item_dir ) ) {
			return true; // Nothing to delete
		}

		// Recursively delete directory
		return $this->delete_directory( $item_dir );
	}

	/**
	 * Recursively delete a directory and its contents.
	 *
	 * @param string $dir Directory path.
	 * @return bool Success status.
	 */
	private function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;

			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
			} else {
				unlink( $path );
			}
		}

		return rmdir( $dir );
	}

	/**
	 * Get base directory for OptiPress files.
	 *
	 * @return string Base directory path.
	 */
	public function get_base_directory() {
		return $this->base_dir;
	}

	/**
	 * Check if base directory is writable.
	 *
	 * @return bool True if writable.
	 */
	public function is_writable() {
		// Create base directory if it doesn't exist
		if ( ! file_exists( $this->base_dir ) ) {
			if ( ! wp_mkdir_p( $this->base_dir ) ) {
				return false;
			}
		}

		return is_writable( $this->base_dir );
	}
}
