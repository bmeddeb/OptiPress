<?php
/**
 * Image Optimization Settings View
 *
 * @package OptiPress
 */

defined( 'ABSPATH' ) || exit;

// Get system check
$system_check = \OptiPress\System_Check::get_instance();
$capabilities = $system_check->get_capabilities();

// Get engine registry
$registry = \OptiPress\Engines\Engine_Registry::get_instance();

// Default values
$engine         = isset( $options['engine'] ) ? $options['engine'] : 'auto';
$format         = isset( $options['format'] ) ? $options['format'] : 'webp';
$quality        = isset( $options['quality'] ) ? $options['quality'] : 85;
$auto_convert   = isset( $options['auto_convert'] ) ? $options['auto_convert'] : true;
$keep_originals = isset( $options['keep_originals'] ) ? $options['keep_originals'] : true;

?>

<div class="optipress-settings-section">
	<h2><?php esc_html_e( 'Image Conversion Settings', 'optipress' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure automatic image conversion for uploaded images.', 'optipress' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<!-- Engine Selection -->
		<tr>
			<th scope="row">
				<label for="optipress_engine"><?php esc_html_e( 'Conversion Engine', 'optipress' ); ?></label>
			</th>
			<td>
				<select name="optipress_options[engine]" id="optipress_engine" class="regular-text">
					<option value="auto" <?php selected( $engine, 'auto' ); ?>>
						<?php esc_html_e( 'Auto-detect (Recommended)', 'optipress' ); ?>
					</option>
					<option value="imagick" <?php selected( $engine, 'imagick' ); ?> <?php disabled( ! in_array( 'imagick', $capabilities['available_engines'], true ) ); ?>>
						<?php esc_html_e( 'Imagick', 'optipress' ); ?>
						<?php if ( ! in_array( 'imagick', $capabilities['available_engines'], true ) ) : ?>
							(<?php esc_html_e( 'Not Available', 'optipress' ); ?>)
						<?php endif; ?>
					</option>
					<option value="gd" <?php selected( $engine, 'gd' ); ?> <?php disabled( ! in_array( 'gd', $capabilities['available_engines'], true ) ); ?>>
						<?php esc_html_e( 'GD Library', 'optipress' ); ?>
						<?php if ( ! in_array( 'gd', $capabilities['available_engines'], true ) ) : ?>
							(<?php esc_html_e( 'Not Available', 'optipress' ); ?>)
						<?php endif; ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Select image processing engine. Auto-detect will prefer Imagick for better quality.', 'optipress' ); ?>
				</p>
			</td>
		</tr>

		<!-- Format Selection -->
		<tr>
			<th scope="row">
				<label for="optipress_format"><?php esc_html_e( 'Output Format', 'optipress' ); ?></label>
			</th>
			<td>
				<select name="optipress_options[format]" id="optipress_format" class="regular-text">
					<option value="webp" <?php selected( $format, 'webp' ); ?>>
						<?php esc_html_e( 'WebP', 'optipress' ); ?>
					</option>
					<option value="avif" <?php selected( $format, 'avif' ); ?>>
						<?php esc_html_e( 'AVIF', 'optipress' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'WebP: Excellent browser support. AVIF: Better compression but requires PHP 8.1+ for GD.', 'optipress' ); ?>
				</p>
				<div id="optipress-compatibility-status" style="margin-top: 10px;"></div>
			</td>
		</tr>

		<!-- Quality Slider -->
		<tr>
			<th scope="row">
				<label for="optipress_quality"><?php esc_html_e( 'Compression Quality', 'optipress' ); ?></label>
			</th>
			<td>
				<input type="range" name="optipress_options[quality]" id="optipress_quality"
					min="1" max="100" value="<?php echo esc_attr( $quality ); ?>"
					class="optipress-quality-slider" />
				<span id="optipress-quality-value" class="optipress-quality-display"><?php echo esc_html( $quality ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Higher quality = larger file size. Recommended: 80-90 for photos, 90-100 for graphics.', 'optipress' ); ?>
				</p>
			</td>
		</tr>

		<!-- Auto Convert Toggle -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Automatic Conversion', 'optipress' ); ?>
			</th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="optipress_options[auto_convert]"
							value="1" <?php checked( $auto_convert ); ?> />
						<?php esc_html_e( 'Automatically convert images on upload', 'optipress' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, JPG and PNG images will be converted automatically when uploaded.', 'optipress' ); ?>
					</p>
				</fieldset>
			</td>
		</tr>

		<!-- Keep Originals Toggle -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Keep Original Files', 'optipress' ); ?>
			</th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="optipress_options[keep_originals]"
							value="1" <?php checked( $keep_originals ); ?> />
						<?php esc_html_e( 'Keep original files after conversion', 'optipress' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Recommended: Keep originals to allow reverting conversions. Warning: Disabling this will permanently delete original files.', 'optipress' ); ?>
					</p>
				</fieldset>
			</td>
		</tr>
	</table>
</div>

<script>
jQuery(document).ready(function($) {
	// Update quality display
	$('#optipress_quality').on('input', function() {
		$('#optipress-quality-value').text($(this).val());
	});

	// Check compatibility on change
	function checkCompatibility() {
		var engine = $('#optipress_engine').val();
		var format = $('#optipress_format').val();
		var $status = $('#optipress-compatibility-status');

		$status.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');

		$.post(ajaxurl, {
			action: 'optipress_check_compatibility',
			nonce: optipressAdmin.nonce,
			engine: engine,
			format: format
		}, function(response) {
			if (response.success) {
				$status.html('<span class="optipress-status-success">✓ ' + response.data.message + '</span>');
			} else {
				$status.html('<span class="optipress-status-error">✗ ' + response.data.message + '</span>');
			}
		});
	}

	$('#optipress_engine, #optipress_format').on('change', checkCompatibility);

	// Check on load
	checkCompatibility();
});
</script>