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

/**
 * Delete plugin metadata from all attachments
 */
global $wpdb;

// Delete image conversion metadata
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	WHERE meta_key IN (
		'_optipress_converted',
		'_optipress_format',
		'_optipress_engine'
	)"
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

// Get all attachments that were converted by OptiPress
$converted_attachments = $wpdb->get_results(
	"SELECT post_id FROM {$wpdb->postmeta}
	WHERE meta_key = '_optipress_converted' AND meta_value = '1'"
);

foreach ( $converted_attachments as $attachment ) {
	$file_path = get_attached_file( $attachment->post_id );

	if ( $file_path ) {
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
		$metadata = wp_get_attachment_metadata( $attachment->post_id );
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