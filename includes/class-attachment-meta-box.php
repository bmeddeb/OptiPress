<?php
/**
 * Attachment Meta Box Class
 *
 * Adds OptiPress meta box to attachment edit screen.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * Attachment_Meta_Box class
 *
 * Displays conversion status and controls on attachment edit page.
 */
class Attachment_Meta_Box {

	/**
	 * Singleton instance
	 *
	 * @var Attachment_Meta_Box
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Attachment_Meta_Box
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Add meta box to attachment edit screen
		add_action( 'add_meta_boxes_attachment', array( $this, 'add_meta_box' ) );

		// Enqueue scripts for attachment edit page
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers - Standard format conversion
		add_action( 'wp_ajax_optipress_convert_single_image', array( $this, 'ajax_convert_single_image' ) );
		add_action( 'wp_ajax_optipress_revert_single_image', array( $this, 'ajax_revert_single_image' ) );
		add_action( 'wp_ajax_optipress_switch_format', array( $this, 'ajax_switch_format' ) );

		// AJAX handlers - Advanced format preview
		add_action( 'wp_ajax_optipress_rebuild_preview', array( $this, 'ajax_rebuild_preview' ) );
		add_action( 'wp_ajax_optipress_regenerate_thumbnails', array( $this, 'ajax_regenerate_thumbnails' ) );

		// Row action links in Media Library list
		add_filter( 'media_row_actions', array( $this, 'row_action_rebuild' ), 10, 2 );
	}

	/**
	 * Add meta box to attachment edit screen
	 *
	 * @param \WP_Post $post Attachment post object.
	 */
	public function add_meta_box( $post ) {
		// Only add to image attachments
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return;
		}

		add_meta_box(
			'optipress-optimization',
			__( 'OptiPress', 'optipress' ),
			array( $this, 'render_meta_box' ),
			'attachment',
			'side',
			'default'
		);
	}

	/**
	 * Render meta box content
	 *
	 * @param \WP_Post $post Attachment post object.
	 */
	public function render_meta_box( $post ) {
		$attachment_id = $post->ID;

		// Get file information
		$meta = wp_get_attachment_metadata( $attachment_id );
		$current_file = get_attached_file( $attachment_id, true );
		$upload_dir = wp_get_upload_dir();

		// Determine whether the original is preserved
		$original_rel = is_array( $meta ) && isset( $meta['original_file'] ) ? $meta['original_file'] : null;
		$original_abs = $original_rel ? trailingslashit( $upload_dir['basedir'] ) . ltrim( $original_rel, '/\\' ) : null;

		// For advanced formats, Advanced_Formats stores original_file; for standard images, the attached file is the original
		$has_original = false;
		if ( $original_abs ) {
			$has_original = file_exists( $original_abs );
		} else {
			$has_original = $current_file && file_exists( $current_file );
		}

		// Detect if this is an advanced format
		$is_advanced = $this->is_advanced_format( $current_file );

		// Get conversion info for standard formats
		$converter = Image_Converter::get_instance();
		$is_converted = $converter->is_converted( $attachment_id );
		$conversion_info = $converter->get_conversion_info( $attachment_id );

		// Get engine registry
		$registry = \OptiPress\Engines\Engine_Registry::get_instance();

		wp_nonce_field( 'optipress_attachment_meta_box', 'optipress_meta_box_nonce' );

		?>
		<div id="optipress-meta-box-content" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>">
			<?php
			if ( $is_advanced ) {
				// Advanced format scenario
				$this->render_advanced_format( $attachment_id, $has_original, $original_rel, $original_abs, $current_file, $upload_dir, $registry );
			} elseif ( $is_converted && $conversion_info ) {
				// Standard format optimized
				$this->render_converted_state( $attachment_id, $conversion_info, $registry, $has_original );
			} else {
				// Standard format not optimized
				$options = get_option( 'optipress_options', array() );
				$auto_convert = isset( $options['auto_convert'] ) ? $options['auto_convert'] : false;
				$this->render_not_converted_state( $attachment_id, $registry, $auto_convert );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Check if file is an advanced format
	 *
	 * @param string $file_path File path.
	 * @return bool True if advanced format.
	 */
	private function is_advanced_format( $file_path ) {
		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$advanced_exts = array(
			'tif', 'tiff', 'psd',
			'dng', 'arw', 'cr2', 'cr3', 'nef', 'orf', 'rw2', 'raf', 'erf', 'mrw', 'pef', 'sr2', 'x3f', '3fr', 'fff', 'iiq', 'nrw', 'srw', 'rwl',
			'jp2', 'j2k', 'jpf', 'jpx', 'jpm',
			'heic', 'heif',
		);
		return in_array( $ext, $advanced_exts, true );
	}

	/**
	 * Render advanced format view
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param bool   $has_original  Whether original file exists.
	 * @param string $original_rel  Original file relative path.
	 * @param string $original_abs  Original file absolute path.
	 * @param string $current_file  Current file absolute path.
	 * @param array  $upload_dir    Upload directory info.
	 * @param mixed  $registry      Engine registry instance.
	 */
	private function render_advanced_format( $attachment_id, $has_original, $original_rel, $original_abs, $current_file, $upload_dir, $registry ) {
		$current_rel = $current_file ? _wp_relative_upload_path( $current_file ) : '';
		$current_size = $current_file && file_exists( $current_file ) ? filesize( $current_file ) : 0;
		$original_size = $original_abs && file_exists( $original_abs ) ? filesize( $original_abs ) : 0;
		$preview_format = strtoupper( pathinfo( $current_file, PATHINFO_EXTENSION ) );
		$original_format = $original_abs ? strtoupper( pathinfo( $original_abs, PATHINFO_EXTENSION ) ) : '';

		// Calculate savings if both exist
		$savings_percent = 0;
		if ( $original_size > 0 && $current_size > 0 ) {
			$savings_percent = ( ( $original_size - $current_size ) / $original_size ) * 100;
		}

		$webp_supported = $registry->validate_engine_format( 'auto', 'webp' )['valid'];
		$avif_supported = $registry->validate_engine_format( 'auto', 'avif' )['valid'];

		?>
		<div class="optipress-advanced-format">
			<?php if ( $has_original ) : ?>
				<div class="optipress-section optipress-original-section">
					<p class="optipress-section-icon">
						<span class="dashicons dashicons-media-document" style="color: #2271b1;"></span>
						<strong><?php esc_html_e( 'Original File (Preserved)', 'optipress' ); ?></strong>
					</p>
					<p class="optipress-file-info">
						<?php echo esc_html( basename( $original_rel ) ); ?><br>
						<span class="optipress-file-meta">
							<?php echo esc_html( $original_format ); ?> • <?php echo esc_html( size_format( $original_size ) ); ?>
						</span>
					</p>
					<p>
						<a href="<?php echo esc_url( $upload_dir['baseurl'] . '/' . ltrim( $original_rel, '/\\' ) ); ?>"
						   class="button button-small"
						   download>
							<span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Download Original', 'optipress' ); ?>
						</a>
					</p>
				</div>
				<hr style="margin: 15px 0;">
			<?php endif; ?>

			<div class="optipress-section optipress-preview-section">
				<p class="optipress-section-icon">
					<span class="dashicons dashicons-format-image" style="color: #46b450;"></span>
					<strong><?php esc_html_e( 'Web Preview', 'optipress' ); ?></strong>
				</p>
				<p class="optipress-file-info">
					<?php echo esc_html( basename( $current_rel ) ); ?><br>
					<span class="optipress-file-meta">
						<?php echo esc_html( $preview_format ); ?> • <?php echo esc_html( size_format( $current_size ) ); ?>
						<?php if ( $savings_percent > 0 ) : ?>
							<br><span class="optipress-savings-badge"><?php echo esc_html( number_format( $savings_percent, 1 ) ); ?>% smaller</span>
						<?php endif; ?>
					</span>
				</p>
			</div>

			<div class="optipress-actions" style="margin-top: 15px;">
				<?php if ( $has_original ) : ?>
					<button type="button"
							id="optipress-rebuild-preview"
							class="button button-secondary"
							data-attachment="<?php echo esc_attr( $attachment_id ); ?>">
						<?php esc_html_e( 'Rebuild Preview', 'optipress' ); ?>
					</button>
				<?php endif; ?>

				<button type="button"
						id="optipress-regenerate-thumbnails"
						class="button button-secondary"
						data-attachment="<?php echo esc_attr( $attachment_id ); ?>">
					<?php esc_html_e( 'Rebuild Images', 'optipress' ); ?>
				</button>

				<?php if ( ! $has_original ) : ?>
					<p class="description" style="margin-top: 10px; color: #d63638;">
						<?php esc_html_e( 'Note: Original file not preserved.', 'optipress' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div id="optipress-rebuild-status" style="display:none; margin-top: 10px;"></div>
		</div>

		<div class="optipress-loading" style="display: none;">
			<span class="spinner is-active"></span>
			<p class="optipress-loading-text"><?php esc_html_e( 'Processing...', 'optipress' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render converted state
	 *
	 * @param int   $attachment_id   Attachment ID.
	 * @param array $conversion_info Conversion information.
	 * @param mixed $registry        Engine registry instance.
	 * @param bool  $has_original    Whether original file exists.
	 */
	private function render_converted_state( $attachment_id, $conversion_info, $registry, $has_original = true ) {
		$format = $conversion_info['format'];
		$bytes_saved = get_post_meta( $attachment_id, '_optipress_bytes_saved', true );
		$percent_saved = get_post_meta( $attachment_id, '_optipress_percent_saved', true );
		$original_size = get_post_meta( $attachment_id, '_optipress_original_size', true );
		$converted_size = get_post_meta( $attachment_id, '_optipress_converted_size', true );
		$engine = get_post_meta( $attachment_id, '_optipress_engine', true );

		// Determine alternate format
		$alternate_format = ( 'webp' === $format ) ? 'avif' : 'webp';
		$alternate_supported = $registry->validate_engine_format( 'auto', $alternate_format )['valid'];

		?>
		<div class="optipress-status-display">
			<p class="optipress-status-label">
				<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
				<strong><?php esc_html_e( 'Status:', 'optipress' ); ?></strong>
				<span class="optipress-status-value"><?php esc_html_e( 'Optimized', 'optipress' ); ?></span>
			</p>

			<div class="optipress-stats">
				<p>
					<strong><?php esc_html_e( 'Format:', 'optipress' ); ?></strong>
					<span class="optipress-format-badge"><?php echo esc_html( strtoupper( $format ) ); ?></span>
				</p>

				<?php if ( $engine ) : ?>
				<p>
					<strong><?php esc_html_e( 'Engine:', 'optipress' ); ?></strong>
					<?php echo esc_html( ucfirst( $engine ) ); ?>
				</p>
				<?php endif; ?>

				<?php if ( $original_size ) : ?>
				<p>
					<strong><?php esc_html_e( 'Original:', 'optipress' ); ?></strong>
					<?php echo esc_html( size_format( $original_size ) ); ?>
				</p>
				<?php endif; ?>

				<?php if ( $converted_size ) : ?>
				<p>
					<strong><?php esc_html_e( 'Optimized:', 'optipress' ); ?></strong>
					<?php echo esc_html( size_format( $converted_size ) ); ?>
				</p>
				<?php endif; ?>

				<?php if ( $bytes_saved ) : ?>
				<p class="optipress-savings">
					<strong><?php esc_html_e( 'Saved:', 'optipress' ); ?></strong>
					<span class="optipress-savings-value">
						<?php echo esc_html( size_format( abs( $bytes_saved ) ) ); ?>
						<?php if ( $percent_saved ) : ?>
							(<?php echo esc_html( number_format( abs( $percent_saved ), 1 ) ); ?>%)
						<?php endif; ?>
					</span>
				</p>
				<?php endif; ?>
			</div>

			<div class="optipress-actions">
				<?php if ( $alternate_supported ) : ?>
				<button type="button" class="button button-secondary optipress-switch-format" data-format="<?php echo esc_attr( $alternate_format ); ?>">
					<?php
					/* translators: %s: Format name (WEBP or AVIF) */
					echo esc_html( sprintf( __( 'Convert to %s', 'optipress' ), strtoupper( $alternate_format ) ) );
					?>
				</button>
				<?php endif; ?>

				<?php if ( $has_original ) : ?>
					<button type="button" class="button button-secondary optipress-revert-image">
						<?php esc_html_e( 'Revert to Original', 'optipress' ); ?>
					</button>
				<?php endif; ?>

				<button type="button"
						id="optipress-regenerate-thumbnails"
						class="button button-secondary"
						data-attachment="<?php echo esc_attr( $attachment_id ); ?>">
					<?php esc_html_e( 'Rebuild Images', 'optipress' ); ?>
				</button>

				<?php if ( ! $has_original ) : ?>
					<p class="description" style="margin-top: 10px; color: #d63638;">
						<span class="dashicons dashicons-warning" style="vertical-align: middle;"></span>
						<?php esc_html_e( 'Original file was not found on disk.', 'optipress' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div id="optipress-rebuild-status" style="display:none; margin-top: 10px;"></div>
		</div>

		<div class="optipress-loading" style="display: none;">
			<span class="spinner is-active"></span>
			<p class="optipress-loading-text"><?php esc_html_e( 'Processing...', 'optipress' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render not converted state
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param mixed $registry      Engine registry instance.
	 * @param bool  $auto_convert  Whether auto-convert is enabled.
	 */
	private function render_not_converted_state( $attachment_id, $registry, $auto_convert = false ) {
		$webp_supported = $registry->validate_engine_format( 'auto', 'webp' )['valid'];
		$avif_supported = $registry->validate_engine_format( 'auto', 'avif' )['valid'];

		?>
		<div class="optipress-status-display">
			<p class="optipress-status-label">
				<span class="dashicons dashicons-info" style="color: #999;"></span>
				<strong><?php esc_html_e( 'Status:', 'optipress' ); ?></strong>
				<span class="optipress-status-value"><?php esc_html_e( 'Not optimized', 'optipress' ); ?></span>
			</p>

			<?php if ( $auto_convert ) : ?>
			<p class="description" style="margin: 10px 0; padding: 8px; background: #f0f6fc; border-left: 3px solid #2271b1;">
				<?php esc_html_e( 'Auto-convert is enabled for new uploads. This image can be manually optimized below.', 'optipress' ); ?>
			</p>
			<?php endif; ?>

			<div class="optipress-format-selector">
				<p><strong><?php esc_html_e( 'Optimize to:', 'optipress' ); ?></strong></p>

				<?php if ( $webp_supported || $avif_supported ) : ?>
				<div class="optipress-format-options">
					<?php if ( $webp_supported ) : ?>
					<label>
						<input type="radio" name="optipress_format" value="webp" checked>
						<span>WebP</span>
					</label>
					<?php endif; ?>

					<?php if ( $avif_supported ) : ?>
					<label>
						<input type="radio" name="optipress_format" value="avif" <?php checked( ! $webp_supported ); ?>>
						<span>AVIF</span>
					</label>
					<?php endif; ?>
				</div>

				<button type="button" class="button button-primary optipress-convert-image">
					<?php esc_html_e( 'Optimize Image', 'optipress' ); ?>
				</button>
				<?php else : ?>
				<p class="description" style="color: #d63638;">
					<?php esc_html_e( 'No conversion engines available. Please check System Status.', 'optipress' ); ?>
				</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="optipress-loading" style="display: none;">
			<span class="spinner is-active"></span>
			<p class="optipress-loading-text"><?php esc_html_e( 'Converting...', 'optipress' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts for attachment edit page
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// On attachment edit screen
		$is_attachment_edit = ( 'post.php' === $hook && 'attachment' === get_post_type( get_the_ID() ?: 0 ) );

		// On media library list screen
		$is_media_library = ( 'upload.php' === $hook );

		if ( ! $is_attachment_edit && ! $is_media_library ) {
			return;
		}

		// Enqueue attachment edit script (for standard format conversion)
		if ( $is_attachment_edit ) {
			wp_enqueue_script(
				'optipress-attachment-edit',
				OPTIPRESS_PLUGIN_URL . 'admin/js/attachment-edit.bundle.js',
				array( 'jquery' ),
				OPTIPRESS_VERSION,
				true
			);

			wp_localize_script(
				'optipress-attachment-edit',
				'optipressAttachment',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'optipress_attachment_edit' ),
					'i18n'    => array(
						'confirmRevert'   => __( 'Revert to original? This will delete the optimized version.', 'optipress' ),
						'converting'      => __( 'Converting...', 'optipress' ),
						'reverting'       => __( 'Reverting...', 'optipress' ),
						'switching'       => __( 'Switching format...', 'optipress' ),
						'success'         => __( 'Success!', 'optipress' ),
						'error'           => __( 'Error', 'optipress' ),
						'unknownError'    => __( 'An unknown error occurred.', 'optipress' ),
						'conversionError' => __( 'Conversion failed:', 'optipress' ),
					),
				)
			);
		}

		// Enqueue preview panel script (for advanced formats and rebuild actions)
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
				'ajax'         => admin_url( 'admin-ajax.php' ),
				'previewNonce' => wp_create_nonce( 'optipress_rebuild_preview' ),
				'thumbsNonce'  => wp_create_nonce( 'optipress_regenerate_thumbnails' ),
			)
		);

		// Enqueue styles for attachment edit page
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
	 * AJAX handler: Convert single image
	 */
	public function ajax_convert_single_image() {
		// Verify nonce
		check_ajax_referer( 'optipress_attachment_edit', 'nonce' );

		// Verify permissions
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'optipress' ) ) );
		}

		// Get parameters
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$format = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : 'webp';

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'optipress' ) ) );
		}

		// Validate format
		if ( ! in_array( $format, array( 'webp', 'avif' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid format.', 'optipress' ) ) );
		}

		// Get converter instance
		$converter = Image_Converter::get_instance();

		// Convert the image
		$result = $converter->convert_single_attachment( $attachment_id, $format );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Get updated conversion info
		$conversion_info = $converter->get_conversion_info( $attachment_id );
		$bytes_saved = get_post_meta( $attachment_id, '_optipress_bytes_saved', true );
		$percent_saved = get_post_meta( $attachment_id, '_optipress_percent_saved', true );

		wp_send_json_success( array(
			'message'       => sprintf(
				/* translators: 1: Format name, 2: Percentage saved */
				__( 'Successfully converted to %1$s. Saved %2$s%%!', 'optipress' ),
				strtoupper( $format ),
				number_format( abs( $percent_saved ), 1 )
			),
			'format'        => $format,
			'bytes_saved'   => $bytes_saved,
			'percent_saved' => $percent_saved,
			'html'          => $this->get_meta_box_html( $attachment_id ),
		) );
	}

	/**
	 * AJAX handler: Revert single image
	 */
	public function ajax_revert_single_image() {
		// Verify nonce
		check_ajax_referer( 'optipress_attachment_edit', 'nonce' );

		// Verify permissions
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'optipress' ) ) );
		}

		// Get parameters
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'optipress' ) ) );
		}

		// Get converter instance
		$converter = Image_Converter::get_instance();

		// Revert the image
		$result = $converter->revert_single_attachment( $attachment_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Successfully reverted to original.', 'optipress' ),
			'html'    => $this->get_meta_box_html( $attachment_id ),
		) );
	}

	/**
	 * AJAX handler: Switch format
	 */
	public function ajax_switch_format() {
		// Verify nonce
		check_ajax_referer( 'optipress_attachment_edit', 'nonce' );

		// Verify permissions
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'optipress' ) ) );
		}

		// Get parameters
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$format = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : '';

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'optipress' ) ) );
		}

		// Validate format
		if ( ! in_array( $format, array( 'webp', 'avif' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid format.', 'optipress' ) ) );
		}

		// Get converter instance
		$converter = Image_Converter::get_instance();

		// First revert, then convert to new format
		$revert_result = $converter->revert_single_attachment( $attachment_id );
		if ( is_wp_error( $revert_result ) ) {
			wp_send_json_error( array( 'message' => $revert_result->get_error_message() ) );
		}

		$convert_result = $converter->convert_single_attachment( $attachment_id, $format );
		if ( is_wp_error( $convert_result ) ) {
			wp_send_json_error( array( 'message' => $convert_result->get_error_message() ) );
		}

		// Get updated stats
		$bytes_saved = get_post_meta( $attachment_id, '_optipress_bytes_saved', true );
		$percent_saved = get_post_meta( $attachment_id, '_optipress_percent_saved', true );

		wp_send_json_success( array(
			'message'       => sprintf(
				/* translators: 1: Format name, 2: Percentage saved */
				__( 'Successfully switched to %1$s. Saved %2$s%%!', 'optipress' ),
				strtoupper( $format ),
				number_format( abs( $percent_saved ), 1 )
			),
			'format'        => $format,
			'bytes_saved'   => $bytes_saved,
			'percent_saved' => $percent_saved,
			'html'          => $this->get_meta_box_html( $attachment_id ),
		) );
	}

	/**
	 * Get meta box HTML for AJAX refresh
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string HTML content.
	 */
	private function get_meta_box_html( $attachment_id ) {
		ob_start();

		$post = get_post( $attachment_id );
		if ( $post ) {
			$this->render_meta_box( $post );
		}

		return ob_get_clean();
	}

	/**
	 * AJAX handler for rebuild preview (advanced formats)
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
				'html'         => $this->get_meta_box_html( $id ),
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
					/* translators: %d is the number of image sizes generated */
					__( 'Successfully rebuilt %d image size(s).', 'optipress' ),
					$count
				),
				'count'   => $count,
				'html'    => $this->get_meta_box_html( $id ),
			)
		);
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

		// Always show rebuild images for images
		if ( wp_attachment_is_image( $post->ID ) ) {
			$actions['optipress_regenerate'] = sprintf(
				'<a href="#" class="optipress-regenerate-link" data-id="%d" data-nonce="%s">%s</a>',
				$post->ID,
				wp_create_nonce( 'optipress_regenerate_thumbnails' ),
				esc_html__( 'Rebuild Images', 'optipress' )
			);
		}

		return $actions;
	}
}
