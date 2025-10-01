<?php
/**
 * WP-CLI Commands
 *
 * Provides WP-CLI commands for batch operations.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * OptiPress regenerate command
	 *
	 * Regenerates attachment metadata, running Advanced_Formats and Thumbnailer pipelines.
	 *
	 * ## OPTIONS
	 *
	 * [--id=<id>]
	 * : Comma-separated attachment IDs to regenerate. If omitted, processes all attachments.
	 *
	 * ## EXAMPLES
	 *
	 *     # Regenerate all attachments
	 *     $ wp optipress regen
	 *
	 *     # Regenerate specific attachments
	 *     $ wp optipress regen --id=123
	 *     $ wp optipress regen --id=123,456,789
	 */
	\WP_CLI::add_command(
		'optipress regen',
		function( $args, $assoc ) {
			$ids = array();
			if ( ! empty( $assoc['id'] ) ) {
				$ids = array_map( 'absint', explode( ',', $assoc['id'] ) );
			} else {
				global $wpdb;
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment'" );
			}

			if ( empty( $ids ) ) {
				\WP_CLI::warning( 'No attachments found.' );
				return;
			}

			\WP_CLI::log( sprintf( 'Processing %d attachment(s)...', count( $ids ) ) );

			$success = 0;
			$failed  = 0;

			foreach ( $ids as $id ) {
				$file = get_attached_file( $id, true );
				if ( ! $file || ! file_exists( $file ) ) {
					\WP_CLI::warning( "Attachment #{$id}: File not found, skipping." );
					$failed++;
					continue;
				}

				$meta = wp_generate_attachment_metadata( $id, $file );
				if ( $meta ) {
					wp_update_attachment_metadata( $id, $meta );
					\WP_CLI::log( "Regenerated #{$id}" );
					$success++;
				} else {
					\WP_CLI::warning( "Attachment #{$id}: Failed to generate metadata." );
					$failed++;
				}
			}

			\WP_CLI::success( sprintf( 'Done. %d succeeded, %d failed.', $success, $failed ) );
		}
	);
}
