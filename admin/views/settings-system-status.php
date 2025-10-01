<?php
/**
 * System Status View
 *
 * @package OptiPress
 */

defined( 'ABSPATH' ) || exit;

// Get system check
$system_check = \OptiPress\System_Check::get_instance();
$capabilities = $system_check->get_capabilities( true ); // Force refresh

// Get current options
$options = get_option( 'optipress_options', array() );

?>

<div class="optipress-settings-section">
	<h2><?php esc_html_e( 'System Status', 'optipress' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Current system capabilities and plugin configuration.', 'optipress' ); ?>
	</p>

	<!-- PHP Environment -->
	<h3><?php esc_html_e( 'PHP Environment', 'optipress' ); ?></h3>
	<table class="widefat striped">
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'PHP Version', 'optipress' ); ?></strong></td>
				<td>
					<?php echo esc_html( $capabilities['php']['version'] ); ?>
					<?php if ( $capabilities['php']['meets_minimum'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Supported', 'optipress' ); ?></span>
					<?php else : ?>
						<span class="optipress-status-error">✗ <?php esc_html_e( 'Requires 7.4+', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'AVIF Support (PHP 8.1+)', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( $capabilities['php']['supports_avif'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Available', 'optipress' ); ?></span>
					<?php else : ?>
						<span class="optipress-status-warning">⚠ <?php esc_html_e( 'Requires PHP 8.1+', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Memory Limit', 'optipress' ); ?></strong></td>
				<td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Max Upload Size', 'optipress' ); ?></strong></td>
				<td><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></td>
			</tr>
		</tbody>
	</table>

	<!-- GD Library -->
	<h3><?php esc_html_e( 'GD Library', 'optipress' ); ?></h3>
	<table class="widefat striped">
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'Status', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( $capabilities['gd']['available'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Available', 'optipress' ); ?></span>
					<?php else : ?>
						<span class="optipress-status-error">✗ <?php esc_html_e( 'Not Available', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $capabilities['gd']['available'] ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Version', 'optipress' ); ?></strong></td>
					<td><?php echo esc_html( $capabilities['gd']['version'] ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'JPEG Support', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( $capabilities['gd']['supports_jpeg'] ) : ?>
							<span class="optipress-status-success">✓</span>
						<?php else : ?>
							<span class="optipress-status-error">✗</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'PNG Support', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( $capabilities['gd']['supports_png'] ) : ?>
							<span class="optipress-status-success">✓</span>
						<?php else : ?>
							<span class="optipress-status-error">✗</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WebP Support', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( $capabilities['gd']['supports_webp'] ) : ?>
							<span class="optipress-status-success">✓</span>
						<?php else : ?>
							<span class="optipress-status-error">✗</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'AVIF Support', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( $capabilities['gd']['supports_avif'] ) : ?>
							<span class="optipress-status-success">✓</span>
						<?php else : ?>
							<span class="optipress-status-warning">⚠ <?php esc_html_e( 'Requires PHP 8.1+', 'optipress' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Imagick Extension -->
	<h3><?php esc_html_e( 'Imagick Extension', 'optipress' ); ?></h3>
	<table class="widefat striped">
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'Status', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( $capabilities['imagick']['available'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Available', 'optipress' ); ?></span>
					<?php else : ?>
						<span class="optipress-status-warning">⚠ <?php esc_html_e( 'Not Available (Optional)', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $capabilities['imagick']['available'] ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Version', 'optipress' ); ?></strong></td>
					<td><?php echo esc_html( $capabilities['imagick']['version'] ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'JPEG Support', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( $capabilities['imagick']['supports_jpeg'] ) : ?>
							<span class="optipress-status-success">✓</span>
						<?php else : ?>
							<span class="optipress-status-error">✗</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'PNG Support', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( $capabilities['imagick']['supports_png'] ) : ?>
							<span class="optipress-status-success">✓</span>
						<?php else : ?>
							<span class="optipress-status-error">✗</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WebP Support', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( $capabilities['imagick']['supports_webp'] ) : ?>
							<span class="optipress-status-success">✓</span>
						<?php else : ?>
							<span class="optipress-status-error">✗</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'AVIF Support', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( $capabilities['imagick']['supports_avif'] ) : ?>
							<span class="optipress-status-success">✓</span>
						<?php else : ?>
							<span class="optipress-status-warning">⚠ <?php esc_html_e( 'Not Available', 'optipress' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Advanced Format Support (TIFF/PSD/RAW) -->
	<h3><?php esc_html_e( 'Advanced Format Support', 'optipress' ); ?></h3>
	<table class="widefat striped">
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'Status', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( $capabilities['raw']['has_delegates'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'RAW Format Support Available', 'optipress' ); ?></span>
					<?php else : ?>
						<span class="optipress-status-warning">⚠ <?php esc_html_e( 'No RAW Format Delegates', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $capabilities['raw']['available'] && $capabilities['raw']['has_delegates'] ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Supported RAW Formats', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( ! empty( $capabilities['raw']['supported_raw'] ) ) : ?>
							<?php echo esc_html( implode( ', ', $capabilities['raw']['supported_raw'] ) ); ?>
						<?php else : ?>
							<?php esc_html_e( 'None detected', 'optipress' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<td><strong><?php esc_html_e( 'TIFF/PSD Support', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( $capabilities['imagick']['available'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Available via Imagick', 'optipress' ); ?></span>
					<?php else : ?>
						<span class="optipress-status-warning">⚠ <?php esc_html_e( 'Requires Imagick', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Preview Generation', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( isset( $options['advanced_previews'] ) && $options['advanced_previews'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Enabled', 'optipress' ); ?></span>
					<?php else : ?>
						<?php esc_html_e( 'Disabled', 'optipress' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>

	<!-- JPEG 2000 Format Support -->
	<h3><?php esc_html_e( 'JPEG 2000 Format Support', 'optipress' ); ?></h3>
	<table class="widefat striped">
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'OpenJPEG Delegate', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( $capabilities['jp2']['has_openjpeg'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Available', 'optipress' ); ?></span>
					<?php else : ?>
						<span class="optipress-status-warning">⚠ <?php esc_html_e( 'Not Available', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $capabilities['jp2']['available'] && $capabilities['jp2']['has_openjpeg'] ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Supported JP2 Formats', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( ! empty( $capabilities['jp2']['supported_jp2'] ) ) : ?>
							<?php echo esc_html( implode( ', ', $capabilities['jp2']['supported_jp2'] ) ); ?>
						<?php else : ?>
							<?php esc_html_e( 'None detected', 'optipress' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
			<?php if ( ! $capabilities['jp2']['has_openjpeg'] ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Installation Instructions', 'optipress' ); ?></strong></td>
					<td>
						<code><?php echo esc_html( $capabilities['jp2']['install_command'] ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Install OpenJPEG delegate to enable JPEG 2000 format support (JP2, J2K, JPX, JPM).', 'optipress' ); ?>
						</p>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- HEIC/HEIF Format Support -->
	<h3><?php esc_html_e( 'HEIC/HEIF Format Support', 'optipress' ); ?></h3>
	<table class="widefat striped">
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'libheif Delegate', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( $capabilities['heif']['has_libheif'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Available', 'optipress' ); ?></span>
					<?php else : ?>
						<span class="optipress-status-warning">⚠ <?php esc_html_e( 'Not Available', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $capabilities['heif']['available'] && $capabilities['heif']['has_libheif'] ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Supported HEIF Formats', 'optipress' ); ?></strong></td>
					<td>
						<?php if ( ! empty( $capabilities['heif']['supported_heif'] ) ) : ?>
							<?php echo esc_html( implode( ', ', $capabilities['heif']['supported_heif'] ) ); ?>
						<?php else : ?>
							<?php esc_html_e( 'None detected', 'optipress' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
			<?php if ( ! $capabilities['heif']['has_libheif'] ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Installation Instructions', 'optipress' ); ?></strong></td>
					<td>
						<code><?php echo esc_html( $capabilities['heif']['install_command'] ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Install libheif delegate to enable HEIC/HEIF format support (iPhone photos).', 'optipress' ); ?>
						</p>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Format Support Summary -->
	<h3><?php esc_html_e( 'Format Support Summary', 'optipress' ); ?></h3>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Format', 'optipress' ); ?></th>
				<th><?php esc_html_e( 'Supported By', 'optipress' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><strong>WebP</strong></td>
				<td>
					<?php if ( ! empty( $capabilities['formats']['webp'] ) ) : ?>
						<?php echo esc_html( implode( ', ', array_map( 'strtoupper', $capabilities['formats']['webp'] ) ) ); ?>
						<span class="optipress-status-success">✓</span>
					<?php else : ?>
						<span class="optipress-status-error">✗ <?php esc_html_e( 'Not supported', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong>AVIF</strong></td>
				<td>
					<?php if ( ! empty( $capabilities['formats']['avif'] ) ) : ?>
						<?php echo esc_html( implode( ', ', array_map( 'strtoupper', $capabilities['formats']['avif'] ) ) ); ?>
						<span class="optipress-status-success">✓</span>
					<?php else : ?>
						<span class="optipress-status-warning">⚠ <?php esc_html_e( 'Not supported', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>

	<!-- Current Configuration -->
	<h3><?php esc_html_e( 'Current Configuration', 'optipress' ); ?></h3>
	<table class="widefat striped">
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'OptiPress Version', 'optipress' ); ?></strong></td>
				<td><?php echo esc_html( defined( 'OPTIPRESS_VERSION' ) ? OPTIPRESS_VERSION : __( 'Unknown', 'optipress' ) ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Selected Engine', 'optipress' ); ?></strong></td>
				<td><?php echo esc_html( isset( $options['engine'] ) ? strtoupper( $options['engine'] ) : 'AUTO' ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Output Format', 'optipress' ); ?></strong></td>
				<td><?php echo esc_html( isset( $options['format'] ) ? strtoupper( $options['format'] ) : 'WEBP' ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Quality', 'optipress' ); ?></strong></td>
				<td><?php echo esc_html( isset( $options['quality'] ) ? $options['quality'] : 85 ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Auto Convert', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( isset( $options['auto_convert'] ) && $options['auto_convert'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Enabled', 'optipress' ); ?></span>
					<?php else : ?>
						<span class="optipress-status-warning">⚠ <?php esc_html_e( 'Disabled', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Keep Originals', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( isset( $options['keep_originals'] ) && $options['keep_originals'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Enabled', 'optipress' ); ?></span>
					<?php else : ?>
						<span class="optipress-status-warning">⚠ <?php esc_html_e( 'Disabled', 'optipress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'SVG Support', 'optipress' ); ?></strong></td>
				<td>
					<?php if ( isset( $options['svg_enabled'] ) && $options['svg_enabled'] ) : ?>
						<span class="optipress-status-success">✓ <?php esc_html_e( 'Enabled', 'optipress' ); ?></span>
					<?php else : ?>
						<?php esc_html_e( 'Disabled', 'optipress' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>

	<!-- Warnings and Errors -->
	<?php if ( ! empty( $capabilities['errors'] ) || ! empty( $capabilities['warnings'] ) ) : ?>
		<h3><?php esc_html_e( 'System Messages', 'optipress' ); ?></h3>

		<?php if ( ! empty( $capabilities['errors'] ) ) : ?>
			<?php foreach ( $capabilities['errors'] as $error ) : ?>
				<div class="notice notice-error inline">
					<p><strong><?php esc_html_e( 'Error:', 'optipress' ); ?></strong> <?php echo esc_html( $error ); ?></p>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ( ! empty( $capabilities['warnings'] ) ) : ?>
			<?php foreach ( $capabilities['warnings'] as $warning ) : ?>
				<div class="notice notice-warning inline">
					<p><strong><?php esc_html_e( 'Warning:', 'optipress' ); ?></strong> <?php echo esc_html( $warning ); ?></p>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	<?php else : ?>
		<div class="notice notice-success inline">
			<p><strong><?php esc_html_e( 'All System Checks Passed!', 'optipress' ); ?></strong></p>
		</div>
	<?php endif; ?>
</div>