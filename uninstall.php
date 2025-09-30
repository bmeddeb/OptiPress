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

/**
 * Delete all plugin metadata from attachments
 *
 * Removes all _optipress_* meta keys including:
 * - _optipress_converted
 * - _optipress_format
 * - _optipress_engine
 * - _optipress_converted_sizes
 * - _optipress_conversion_date
 * - _optipress_original_size
 * - _optipress_converted_size
 * - _optipress_bytes_saved
 * - _optipress_percent_saved
 * - _optipress_errors
 */
global $wpdb;

// Delete all _optipress_* metadata using wildcard
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	WHERE meta_key LIKE '_optipress_%'"
);

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

// Get all attachments with JPG/PNG mime types
$attachments = $wpdb->get_results(
	"SELECT ID FROM {$wpdb->posts}
	WHERE post_type = 'attachment'
	AND post_mime_type IN ('image/jpeg', 'image/png')"
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
 * Clear any cached data
 */
wp_cache_flush();