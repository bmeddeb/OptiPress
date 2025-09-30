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

		// AJAX handlers
		add_action( 'wp_ajax_optipress_convert_single_image', array( $this, 'ajax_convert_single_image' ) );
		add_action( 'wp_ajax_optipress_revert_single_image', array( $this, 'ajax_revert_single_image' ) );
		add_action( 'wp_ajax_optipress_switch_format', array( $this, 'ajax_switch_format' ) );
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

		// Only add for JPEG/PNG (convertible types)
		$mime_type = get_post_mime_type( $post->ID );
		if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/png' ), true ) ) {
			return;
		}

		add_meta_box(
			'optipress-optimization',
			__( 'OptiPress Optimization', 'optipress' ),
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

		// Get conversion info
		$converter = Image_Converter::get_instance();
		$is_converted = $converter->is_converted( $attachment_id );
		$conversion_info = $converter->get_conversion_info( $attachment_id );

		// Get plugin settings
		$options = get_option( 'optipress_options', array() );
		$auto_convert = isset( $options['auto_convert'] ) ? $options['auto_convert'] : false;

		// Get engine registry for format support check
		$registry = \OptiPress\Engines\Engine_Registry::get_instance();

		wp_nonce_field( 'optipress_attachment_meta_box', 'optipress_meta_box_nonce' );

		?>
		<div id="optipress-meta-box-content" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>">
			<?php if ( $is_converted && $conversion_info ) : ?>
				<?php $this->render_converted_state( $attachment_id, $conversion_info, $registry ); ?>
			<?php elseif ( ! $auto_convert ) : ?>
				<?php $this->render_not_converted_state( $attachment_id, $registry ); ?>
			<?php else : ?>
				<?php $this->render_auto_convert_info(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render converted state
	 *
	 * @param int   $attachment_id   Attachment ID.
	 * @param array $conversion_info Conversion information.
	 * @param mixed $registry        Engine registry instance.
	 */
	private function render_converted_state( $attachment_id, $conversion_info, $registry ) {
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

				<button type="button" class="button button-secondary optipress-revert-image">
					<?php esc_html_e( 'Revert to Original', 'optipress' ); ?>
				</button>
			</div>
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
	 */
	private function render_not_converted_state( $attachment_id, $registry ) {
		$webp_supported = $registry->validate_engine_format( 'auto', 'webp' )['valid'];
		$avif_supported = $registry->validate_engine_format( 'auto', 'avif' )['valid'];

		?>
		<div class="optipress-status-display">
			<p class="optipress-status-label">
				<span class="dashicons dashicons-info" style="color: #999;"></span>
				<strong><?php esc_html_e( 'Status:', 'optipress' ); ?></strong>
				<span class="optipress-status-value"><?php esc_html_e( 'Not optimized', 'optipress' ); ?></span>
			</p>

			<div class="optipress-format-selector">
				<p><strong><?php esc_html_e( 'Convert to:', 'optipress' ); ?></strong></p>

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
					<?php esc_html_e( 'Convert Image', 'optipress' ); ?>
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
	 * Render auto-convert info state
	 */
	private function render_auto_convert_info() {
		?>
		<p class="description">
			<?php esc_html_e( 'Auto-convert is enabled. New uploads are automatically optimized.', 'optipress' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'This image will be converted on next upload or via bulk optimization.', 'optipress' ); ?>
		</p>
		<?php
	}

	/**
	 * Enqueue scripts for attachment edit page
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only on attachment edit page
		if ( 'post.php' !== $hook ) {
			return;
		}

		global $post;
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return;
		}

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
}