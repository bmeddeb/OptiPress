<?php
/**
 * Attachment Preview Panel
 *
 * Shows Original file vs Preview file for attachments that OptiPress processed.
 * Lets admins rebuild the preview via AJAX.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * Attachment_Preview_Panel class
 *
 * - Shows Original file vs Preview file for attachments that OptiPress processed
 * - Lets admins rebuild the preview via AJAX
 */
final class Attachment_Preview_Panel {
	/**
	 * Singleton instance
	 *
	 * @var Attachment_Preview_Panel|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Attachment_Preview_Panel
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - registers hooks
	 */
	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_optipress_rebuild_preview', array( $this, 'ajax_rebuild_preview' ) );
		add_action( 'wp_ajax_optipress_regenerate_thumbnails', array( $this, 'ajax_regenerate_thumbnails' ) );

		// Optional: row action link in Media Library list
		add_filter( 'media_row_actions', array( $this, 'row_action_rebuild' ), 10, 2 );
	}

	/**
	 * Add meta box to attachment edit screen
	 */
	public function add_meta_box() {
		add_meta_box(
			'optipress_preview_panel',
			__( 'OptiPress Preview', 'optipress' ),
			array( $this, 'render_meta_box' ),
			'attachment',
			'side',
			'default'
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// On attachment edit screen
		$is_attachment_edit = ( 'post.php' === $hook && 'attachment' === get_post_type( get_the_ID() ?: 0 ) );

		// On media library list screen
		$is_media_library = ( 'upload.php' === $hook );

		if ( ! $is_attachment_edit && ! $is_media_library ) {
			return;
		}

		wp_enqueue_script(
			'optipress-preview-panel',
			plugins_url( 'assets/js/preview-panel.js', OPTIPRESS_PLUGIN_FILE ),
			array( 'jquery' ),
			OPTIPRESS_VERSION,
			true
		);
		wp_localize_script(
			'optipress-preview-panel',
			'OptiPressPreview',
			array(
				'ajax'        => admin_url( 'admin-ajax.php' ),
				'previewNonce' => wp_create_nonce( 'optipress_rebuild_preview' ),
				'thumbsNonce'  => wp_create_nonce( 'optipress_regenerate_thumbnails' ),
			)
		);

		if ( $is_attachment_edit ) {
			wp_enqueue_style(
				'optipress-preview-panel-css',
				plugins_url( 'assets/css/preview-panel.css', OPTIPRESS_PLUGIN_FILE ),
				array(),
				OPTIPRESS_VERSION
			);
		}
	}

	/**
	 * Render meta box content
	 *
	 * @param \WP_Post $post The attachment post object.
	 */
	public function render_meta_box( $post ) {
		if ( 'attachment' !== $post->post_type ) {
			echo '<p>' . esc_html__( 'Not an attachment.', 'optipress' ) . '</p>';
			return;
		}

		$meta       = wp_get_attachment_metadata( $post->ID );
		$upload_dir = wp_get_upload_dir();

		$original_rel = is_array( $meta ) && isset( $meta['original_file'] ) ? $meta['original_file'] : null;
		$current_abs  = get_attached_file( $post->ID, true ); // absolute path to the current file
		$current_rel  = $current_abs ? _wp_relative_upload_path( $current_abs ) : '';

		$original_abs = $original_rel ? trailingslashit( $upload_dir['basedir'] ) . ltrim( $original_rel, '/\\' ) : null;

		echo '<div class="optipress-box">';
		echo '<p><strong>' . esc_html__( 'Preview file (used for thumbnails):', 'optipress' ) . '</strong><br>';
		echo $current_rel ? esc_html( $current_rel ) : '<em>' . esc_html__( 'None', 'optipress' ) . '</em>';
		echo '</p>';

		// Show preview image
		if ( $current_abs && file_exists( $current_abs ) ) {
			$preview_url = $upload_dir['baseurl'] . '/' . ltrim( $current_rel, '/\\' );
			echo '<p style="margin: 10px 0;">';
			echo '<img src="' . esc_url( $preview_url ) . '" alt="' . esc_attr__( 'Preview', 'optipress' ) . '" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;" />';
			echo '</p>';
			echo '<p><a href="' . esc_url( $preview_url ) . '" target="_blank" class="button button-small">' . esc_html__( 'Open Full Size', 'optipress' ) . '</a></p>';
		}
		echo '<hr style="margin: 15px 0;" />';

		echo '<p><strong>' . esc_html__( 'Original file (preserved):', 'optipress' ) . '</strong><br>';
		echo $original_rel ? esc_html( $original_rel ) : '<em>' . esc_html__( 'Not recorded', 'optipress' ) . '</em>';
		if ( $original_abs && file_exists( $original_abs ) ) {
			echo '<br><a href="' . esc_url( $upload_dir['baseurl'] . '/' . ltrim( $original_rel, '/\\' ) ) . '" target="_blank">' . esc_html__( 'Download', 'optipress' ) . '</a>';
		}
		echo '</p>';

		$disabled = $original_abs && file_exists( $original_abs ) ? '' : 'disabled';
		echo '<p>';
		echo '<button id="optipress-rebuild-preview" class="button button-secondary" ' . $disabled . ' data-attachment="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Rebuild Preview', 'optipress' ) . '</button> ';
		echo '<button id="optipress-regenerate-thumbnails" class="button button-secondary" data-attachment="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Regenerate Thumbnails', 'optipress' ) . '</button>';
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'Rebuild Preview: Recreates preview from original. Regenerate Thumbnails: Rebuilds all thumbnail sizes from current file.', 'optipress' ) . '</p>';
		echo '<div id="optipress-rebuild-status" style="display:none"></div>';
		echo '</div>';
	}

	/**
	 * Add row action to Media Library list
	 *
	 * @param array    $actions Existing row actions.
	 * @param \WP_Post $post    The attachment post object.
	 * @return array Modified row actions.
	 */
	public function row_action_rebuild( $actions, $post ) {
		if ( 'attachment' !== $post->post_type ) {
			return $actions;
		}

		// Only show rebuild preview if there's an original file
		$meta = wp_get_attachment_metadata( $post->ID );
		if ( is_array( $meta ) && ! empty( $meta['original_file'] ) ) {
			$actions['optipress_rebuild'] = sprintf(
				'<a href="#" class="optipress-rebuild-link" data-id="%d" data-nonce="%s">%s</a>',
				$post->ID,
				wp_create_nonce( 'optipress_rebuild_preview' ),
				esc_html__( 'Rebuild Preview', 'optipress' )
			);
		}

		// Always show regenerate thumbnails for images
		if ( wp_attachment_is_image( $post->ID ) ) {
			$actions['optipress_regenerate'] = sprintf(
				'<a href="#" class="optipress-regenerate-link" data-id="%d" data-nonce="%s">%s</a>',
				$post->ID,
				wp_create_nonce( 'optipress_regenerate_thumbnails' ),
				esc_html__( 'Regenerate Thumbnails', 'optipress' )
			);
		}

		return $actions;
	}

	/**
	 * AJAX handler for rebuild preview
	 */
	public function ajax_rebuild_preview() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'optipress' ) ), 403 );
		}
		check_ajax_referer( 'optipress_rebuild_preview' );

		$id = isset( $_REQUEST['attachment_id'] ) ? absint( $_REQUEST['attachment_id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'optipress' ) ), 400 );
		}

		$meta       = wp_get_attachment_metadata( $id );
		$upload_dir = wp_get_upload_dir();
		$original_rel = is_array( $meta ) && isset( $meta['original_file'] ) ? $meta['original_file'] : null;

		if ( ! $original_rel ) {
			wp_send_json_error( array( 'message' => __( 'No original file recorded for this attachment.', 'optipress' ) ), 400 );
		}

		$original_abs = trailingslashit( $upload_dir['basedir'] ) . ltrim( $original_rel, '/\\' );
		if ( ! file_exists( $original_abs ) ) {
			wp_send_json_error( array( 'message' => __( 'Original file is missing on disk.', 'optipress' ) ), 404 );
		}

		// If current is a -preview.*, remove it so we start fresh
		$current_abs = get_attached_file( $id, true );
		if ( $current_abs && preg_match( '~\-preview\.(?:jpe?g|webp|avif)$~i', $current_abs ) && file_exists( $current_abs ) ) {
			@unlink( $current_abs );
		}

		// Point attachment back to original, then ask WP to (re)generate metadata.
		update_attached_file( $id, $original_abs );
		$new_meta = wp_generate_attachment_metadata( $id, $original_abs );
		if ( is_wp_error( $new_meta ) || empty( $new_meta ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate metadata.', 'optipress' ) ), 500 );
		}
		wp_update_attachment_metadata( $id, $new_meta );

		// Advanced_Formats hook will have re-pointed to the new preview and built sizes.
		$new_current = get_attached_file( $id, true );
		wp_send_json_success(
			array(
				'message'      => __( 'Preview rebuilt successfully.', 'optipress' ),
				'preview_file' => _wp_relative_upload_path( $new_current ),
				'meta'         => $new_meta,
			)
		);
	}

	/**
	 * AJAX handler for regenerate thumbnails
	 */
	public function ajax_regenerate_thumbnails() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'optipress' ) ), 403 );
		}
		check_ajax_referer( 'optipress_regenerate_thumbnails' );

		$id = isset( $_REQUEST['attachment_id'] ) ? absint( $_REQUEST['attachment_id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'optipress' ) ), 400 );
		}

		if ( ! wp_attachment_is_image( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Attachment is not an image.', 'optipress' ) ), 400 );
		}

		$current_file = get_attached_file( $id, true );
		if ( ! $current_file || ! file_exists( $current_file ) ) {
			wp_send_json_error( array( 'message' => __( 'File not found.', 'optipress' ) ), 404 );
		}

		// Get current metadata
		$meta = wp_get_attachment_metadata( $id );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		// Delete existing thumbnail files
		if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			$upload_dir = wp_get_upload_dir();
			$dirname = dirname( $current_file );
			foreach ( $meta['sizes'] as $size_data ) {
				if ( ! empty( $size_data['file'] ) ) {
					$thumb_path = trailingslashit( $dirname ) . $size_data['file'];
					if ( file_exists( $thumb_path ) ) {
						@unlink( $thumb_path );
					}
				}
			}
		}

		// Clear sizes from metadata
		$meta['sizes'] = array();

		// Trigger thumbnail generation via Thumbnailer
		$meta = apply_filters( 'wp_generate_attachment_metadata', $meta, $id );

		// Update metadata
		wp_update_attachment_metadata( $id, $meta );

		$count = is_array( $meta ) && isset( $meta['sizes'] ) ? count( $meta['sizes'] ) : 0;

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d is the number of thumbnails generated */
					__( 'Successfully regenerated %d thumbnail(s).', 'optipress' ),
					$count
				),
				'count'   => $count,
				'meta'    => $meta,
			)
		);
	}
}
