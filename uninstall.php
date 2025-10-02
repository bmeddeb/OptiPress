<?php
/**
 * OptiPress Uninstall
 *
 * Cleanup plugin data when plugin is deleted (not deactivated).
 *
 * @package OptiPress
 */

// Exit if accessed directly or not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options
 */
delete_option( 'optipress_options' );
delete_option( 'optipress_security_log' );
delete_option( 'optipress_organizer_db_version' );

/**
 * Delete all plugin metadata from posts
 *
 * Removes all _optipress_* meta keys including:
 *
 * Attachment meta (from Image Converter):
 * - _optipress_converted, _optipress_format, _optipress_engine
 * - _optipress_converted_sizes, _optipress_conversion_date
 * - _optipress_original_size, _optipress_converted_size
 * - _optipress_bytes_saved, _optipress_percent_saved, _optipress_errors
 *
 * Attachment meta (from Organizer):
 * - _optipress_is_advanced_format, _optipress_organizer_item_id
 *
 * Item meta (optipress_item):
 * - _optipress_metadata, _optipress_display_file
 * - _optipress_view_count, _optipress_size_files
 *
 * File meta (optipress_file):
 * - _optipress_file_path, _optipress_file_size, _optipress_file_format
 * - _optipress_variant_type, _optipress_dimensions
 * - _optipress_conversion_settings, _optipress_exif_data
 * - _optipress_iptc_data, _optipress_download_count
 * - _optipress_file_info, _optipress_complete_metadata, _optipress_size_name
 */
global $wpdb;

// Delete all _optipress_* metadata using wildcard
$deleted_meta = $wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	WHERE meta_key LIKE '_optipress_%'"
);

// Note: Metadata is deleted BEFORE posts to avoid orphaned meta

/**
 * Optional: Delete converted image files
 *
 * Uncomment this section if you want to delete all WebP/AVIF files
 * created by OptiPress when the plugin is uninstalled.
 *
 * Note: This will only delete converted files if the originals are still present.
 * If "Keep Originals" was disabled, the original files may no longer exist.
 */

/*
$upload_dir = wp_upload_dir();
$base_dir = $upload_dir['basedir'];

// Get all convertible image attachments
// Note: Since we're in uninstall, we can't easily access the engine registry,
// so we query for all images and check if converted versions exist
$attachments = $wpdb->get_results(
	"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
	INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
	WHERE p.post_type = 'attachment'
	AND pm.meta_key = '_optipress_converted'
	AND pm.meta_value = '1'"
);

foreach ( $attachments as $attachment ) {
	$file_path = get_attached_file( $attachment->ID );

	if ( $file_path && file_exists( $file_path ) ) {
		// Delete WebP version
		$webp_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );
		if ( file_exists( $webp_path ) ) {
			@unlink( $webp_path );
		}

		// Delete AVIF version
		$avif_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.avif', $file_path );
		if ( file_exists( $avif_path ) ) {
			@unlink( $avif_path );
		}

		// Handle image sizes (thumbnails, medium, large, etc.)
		$metadata = wp_get_attachment_metadata( $attachment->ID );
		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$upload_dir = dirname( $file_path );

			foreach ( $metadata['sizes'] as $size ) {
				if ( isset( $size['file'] ) ) {
					// Delete WebP version of this size
					$size_webp = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $size['file'] );
					$size_webp_path = $upload_dir . '/' . $size_webp;
					if ( file_exists( $size_webp_path ) ) {
						@unlink( $size_webp_path );
					}

					// Delete AVIF version of this size
					$size_avif = preg_replace( '/\.(jpg|jpeg|png)$/i', '.avif', $size['file'] );
					$size_avif_path = $upload_dir . '/' . $size_avif;
					if ( file_exists( $size_avif_path ) ) {
						@unlink( $size_avif_path );
					}
				}
			}
		}
	}
}
*/

/**
 * Delete transients
 *
 * Clean up any temporary data stored in transients.
 */
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	WHERE option_name LIKE '_transient_optipress_%'
	OR option_name LIKE '_transient_timeout_optipress_%'"
);

/**
 * Delete Library Organizer data
 *
 * Removes all organizer CPTs, taxonomies, database tables, and files.
 *
 * Cleanup order:
 * 1. Posts (items & files) - Deletes CPT posts and their relationships
 * 2. Taxonomies - Removes custom taxonomies and all terms
 * 3. Database tables - Drops custom tables (downloads tracking)
 * 4. Physical files - Recursively deletes uploads/optipress/ directory
 */

// Load required classes for cleanup
require_once plugin_dir_path( __FILE__ ) . 'includes/organizer/class-database.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/organizer/class-file-system.php';

// Delete all optipress_item posts
$item_ids = $wpdb->get_col(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'optipress_item'"
);

foreach ( $item_ids as $item_id ) {
	wp_delete_post( $item_id, true ); // Force delete, skip trash
}

// Delete all optipress_file posts
$file_ids = $wpdb->get_col(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'optipress_file'"
);

foreach ( $file_ids as $file_id ) {
	wp_delete_post( $file_id, true ); // Force delete, skip trash
}

// Delete organizer taxonomies
$taxonomies = array( 'optipress_collection', 'optipress_tag', 'optipress_access', 'optipress_file_type' );

foreach ( $taxonomies as $taxonomy ) {
	$terms = $wpdb->get_results( $wpdb->prepare(
		"SELECT t.term_id FROM {$wpdb->terms} t
		INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
		WHERE tt.taxonomy = %s",
		$taxonomy
	) );

	foreach ( $terms as $term ) {
		wp_delete_term( $term->term_id, $taxonomy );
	}

	// Delete taxonomy itself
	$wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => $taxonomy ) );
}

// Drop organizer custom tables
$database = new OptiPress_Organizer_Database();
$database->drop_tables();

// Delete organizer files and directories
$file_system = new OptiPress_Organizer_File_System();
$base_dir = $file_system->get_base_directory();

// Safety check: Verify we're deleting the correct directory
// Base dir should be: {uploads}/optipress/
$upload_dir = wp_upload_dir();
$expected_base = trailingslashit( $upload_dir['basedir'] ) . 'optipress/';

if ( $base_dir === $expected_base && is_dir( $base_dir ) ) {
	// Recursively delete organizer directory
	try {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				@rmdir( $file->getPathname() );
			} else {
				@unlink( $file->getPathname() );
			}
		}

		// Delete the base directory
		@rmdir( $base_dir );
	} catch ( Exception $e ) {
		// Silently fail - files may be locked or permissions issue
		// This prevents fatal errors during uninstall
	}
}

/**
 * Clear any cached data
 */
wp_cache_flush();