<?php
/**
 * SVG Support Settings View
 *
 * @package OptiPress
 */

defined( 'ABSPATH' ) || exit;

// Default values
$svg_enabled = isset( $options['svg_enabled'] ) ? $options['svg_enabled'] : false;

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

	<div class="notice notice-warning inline" style="margin-top: 20px;">
		<p>
			<strong><?php esc_html_e( 'Important:', 'optipress' ); ?></strong>
			<?php esc_html_e( 'While OptiPress uses industry-standard sanitization techniques, SVG files should only be accepted from trusted sources. Never allow untrusted users to upload SVG files.', 'optipress' ); ?>
		</p>
	</div>
</div>