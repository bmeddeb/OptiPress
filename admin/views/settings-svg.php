<?php
/**
 * SVG Support Settings View
 *
 * @package OptiPress
 */

defined( 'ABSPATH' ) || exit;

// Default values
$svg_enabled = isset( $options['svg_enabled'] ) ? $options['svg_enabled'] : false;
$svg_preview_enabled = isset( $options['svg_preview_enabled'] ) ? $options['svg_preview_enabled'] : false;

// Get SVG sanitizer
$svg_sanitizer = \OptiPress\SVG_Sanitizer::get_instance();
$security_log  = $svg_sanitizer->get_security_log( 10 );

?>

<div class="optipress-settings-section">
	<h2><?php esc_html_e( 'SVG Upload Support', 'optipress' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Enable secure SVG file uploads with automatic sanitization.', 'optipress' ); ?>
	</p>

	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'Security Note:', 'optipress' ); ?></strong>
			<?php esc_html_e( 'SVG files can contain executable code and pose security risks. OptiPress uses multi-layer server-side sanitization to remove malicious content before storing files. All uploaded SVGs are automatically sanitized - the original unsanitized file is never stored.', 'optipress' ); ?>
		</p>
	</div>

	<table class="form-table" role="presentation">
		<!-- Enable SVG Support -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Enable SVG Uploads', 'optipress' ); ?>
			</th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="optipress_options[svg_enabled]"
							value="1" <?php checked( $svg_enabled ); ?> />
						<?php esc_html_e( 'Allow SVG file uploads', 'optipress' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, SVG files can be uploaded to the media library and will be automatically sanitized.', 'optipress' ); ?>
					</p>
				</fieldset>
			</td>
		</tr>

		<!-- Enable SVG Preview -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Client-Side Preview', 'optipress' ); ?>
			</th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="optipress_options[svg_preview_enabled]"
							value="1" <?php checked( $svg_preview_enabled ); ?> />
						<?php esc_html_e( 'Enable client-side SVG preview with DOMPurify', 'optipress' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Shows a sanitized preview of SVG files before upload. This is for convenience only - server-side sanitization is always performed and is the authoritative security layer.', 'optipress' ); ?>
					</p>
				</fieldset>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Sanitization Features', 'optipress' ); ?></h3>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Security Feature', 'optipress' ); ?></th>
				<th><?php esc_html_e( 'Status', 'optipress' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Server-side sanitization (enshrined/svg-sanitize)', 'optipress' ); ?></td>
				<td><span class="optipress-status-success">✓ <?php esc_html_e( 'Active', 'optipress' ); ?></span></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'XXE attack prevention (libxml safe flags)', 'optipress' ); ?></td>
				<td><span class="optipress-status-success">✓ <?php esc_html_e( 'Active', 'optipress' ); ?></span></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Remove <script> tags', 'optipress' ); ?></td>
				<td><span class="optipress-status-success">✓ <?php esc_html_e( 'Active', 'optipress' ); ?></span></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Remove <foreignObject> tags', 'optipress' ); ?></td>
				<td><span class="optipress-status-success">✓ <?php esc_html_e( 'Active', 'optipress' ); ?></span></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Remove event handlers (onclick, onload, etc.)', 'optipress' ); ?></td>
				<td><span class="optipress-status-success">✓ <?php esc_html_e( 'Active', 'optipress' ); ?></span></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Block javascript: protocols', 'optipress' ); ?></td>
				<td><span class="optipress-status-success">✓ <?php esc_html_e( 'Active', 'optipress' ); ?></span></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Block remote references', 'optipress' ); ?></td>
				<td><span class="optipress-status-success">✓ <?php esc_html_e( 'Active', 'optipress' ); ?></span></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Block unsafe data: URIs', 'optipress' ); ?></td>
				<td><span class="optipress-status-success">✓ <?php esc_html_e( 'Active', 'optipress' ); ?></span></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'XML validation', 'optipress' ); ?></td>
				<td><span class="optipress-status-success">✓ <?php esc_html_e( 'Active', 'optipress' ); ?></span></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'File size limits (2MB default)', 'optipress' ); ?></td>
				<td><span class="optipress-status-success">✓ <?php esc_html_e( 'Active', 'optipress' ); ?></span></td>
			</tr>
		</tbody>
	</table>

	<?php if ( ! empty( $security_log ) ) : ?>
		<h3><?php esc_html_e( 'Recent Security Log', 'optipress' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Last 10 SVG sanitization events.', 'optipress' ); ?>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'optipress' ); ?></th>
					<th><?php esc_html_e( 'Event', 'optipress' ); ?></th>
					<th><?php esc_html_e( 'File', 'optipress' ); ?></th>
					<th><?php esc_html_e( 'Message', 'optipress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_reverse( $security_log ) as $log_entry ) : ?>
					<tr>
						<td><?php echo esc_html( $log_entry['time'] ); ?></td>
						<td>
							<?php
							$event_class = 'svg_sanitized' === $log_entry['type'] ? 'optipress-status-success' : 'optipress-status-error';
							echo '<span class="' . esc_attr( $event_class ) . '">' . esc_html( $log_entry['type'] ) . '</span>';
							?>
						</td>
						<td><?php echo esc_html( $log_entry['file'] ); ?></td>
						<td><?php echo esc_html( $log_entry['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><em><?php esc_html_e( 'No SVG upload activity recorded yet.', 'optipress' ); ?></em></p>
	<?php endif; ?>

	<!-- Batch Sanitization Section -->
	<h3><?php esc_html_e( 'Batch Sanitization', 'optipress' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Sanitize all existing SVG files in your media library. This will overwrite each file with a sanitized version.', 'optipress' ); ?>
	</p>

	<div class="optipress-batch-section">
		<div class="optipress-batch-stats">
			<p>
				<?php esc_html_e( 'SVG files found:', 'optipress' ); ?>
				<strong id="optipress-total-svgs">-</strong>
			</p>
		</div>

		<div class="optipress-batch-controls" style="margin-top: 15px;">
			<button type="button" id="optipress-sanitize-svg-batch" class="button button-primary" disabled>
				<?php esc_html_e( 'Sanitize Existing SVGs', 'optipress' ); ?>
			</button>
		</div>

		<div class="optipress-batch-progress-container" style="margin-top: 20px;">
			<div id="optipress-svg-batch-progress" class="optipress-progress-bar" style="display: none;">
				<div class="optipress-progress-fill"></div>
			</div>
			<div id="optipress-svg-batch-status" class="optipress-status-text" style="display: none; margin-top: 10px;"></div>
			<div id="optipress-svg-batch-result" class="optipress-result-area" style="display: none; margin-top: 10px;"></div>
		</div>

		<div class="notice notice-info inline" style="margin-top: 20px;">
			<p>
				<strong><?php esc_html_e( 'Note:', 'optipress' ); ?></strong>
				<?php esc_html_e( 'This process will re-sanitize all existing SVG files, even if they were already sanitized. The original unsanitized files cannot be recovered.', 'optipress' ); ?>
			</p>
		</div>
	</div>

	<div class="notice notice-warning inline" style="margin-top: 20px;">
		<p>
			<strong><?php esc_html_e( 'Important:', 'optipress' ); ?></strong>
			<?php esc_html_e( 'While OptiPress uses industry-standard sanitization techniques, SVG files should only be accepted from trusted sources. Never allow untrusted users to upload SVG files.', 'optipress' ); ?>
		</p>
	</div>
</div>