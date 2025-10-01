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
					min="1" max="100" step="5" value="<?php echo esc_attr( $quality ); ?>"
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

<!-- Front-End Delivery Settings -->
<div class="optipress-settings-section">
	<h2><?php esc_html_e( 'Front-End Delivery', 'optipress' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure how optimized images are delivered to website visitors.', 'optipress' ); ?>
	</p>

	<?php
	$enable_content_filter = isset( $options['enable_content_filter'] ) ? $options['enable_content_filter'] : true;
	$use_picture_element = isset( $options['use_picture_element'] ) ? $options['use_picture_element'] : false;
	?>

	<table class="form-table" role="presentation">
		<!-- Enable Content Filter -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Enable Content Filter', 'optipress' ); ?>
			</th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="optipress_options[enable_content_filter]"
							value="1" <?php checked( $enable_content_filter ); ?> />
						<?php esc_html_e( 'Replace images in post content with optimized versions', 'optipress' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Automatically replaces image URLs in post content, widgets, and thumbnails. Works alongside WordPress image filters for complete coverage.', 'optipress' ); ?>
					</p>
				</fieldset>
			</td>
		</tr>

		<!-- Picture Element -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Use Picture Element', 'optipress' ); ?>
			</th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="optipress_options[use_picture_element]"
							value="1" <?php checked( $use_picture_element ); ?> />
						<?php esc_html_e( 'Generate &lt;picture&gt; elements with format-specific sources', 'optipress' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Uses HTML5 picture elements for better browser compatibility. If disabled, simply replaces image URLs (recommended for better caching plugin compatibility).', 'optipress' ); ?>
					</p>
				</fieldset>
			</td>
		</tr>
	</table>

	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'Note:', 'optipress' ); ?></strong>
			<?php esc_html_e( 'OptiPress automatically detects browser support via HTTP Accept headers. Only browsers that support WebP/AVIF will receive optimized images. Caching plugins (W3 Total Cache, WP Rocket, etc.) can further optimize delivery through server-level rewrites.', 'optipress' ); ?>
		</p>
	</div>
</div>

<!-- Batch Processing Section -->
<div class="optipress-settings-section optipress-batch-section">
	<h2><?php esc_html_e( 'Batch Processing', 'optipress' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Convert all existing images in your media library to the selected format.', 'optipress' ); ?>
	</p>

	<div class="optipress-batch-stats">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Metric', 'optipress' ); ?></th>
					<th><?php esc_html_e( 'Count', 'optipress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Total Images', 'optipress' ); ?></td>
					<td><strong id="optipress-total-images">-</strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Converted Images', 'optipress' ); ?></td>
					<td><strong id="optipress-converted-images">-</strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Remaining Images', 'optipress' ); ?></td>
					<td><strong id="optipress-remaining-images">-</strong></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="optipress-batch-controls" style="margin-top: 20px;">
		<button type="button" id="optipress-start-batch" class="button button-primary" disabled>
			<?php esc_html_e( 'Start Bulk Optimization', 'optipress' ); ?>
		</button>

		<?php if ( $keep_originals ) : ?>
		<button type="button" id="optipress-revert-batch" class="button button-secondary" disabled style="margin-left: 10px;">
			<?php esc_html_e( 'Revert All to Originals', 'optipress' ); ?>
		</button>
		<?php else : ?>
		<p class="description" style="margin-top: 10px;">
			<em><?php esc_html_e( 'Note: Enable "Keep Original Files" to enable revert functionality.', 'optipress' ); ?></em>
		</p>
		<?php endif; ?>
	</div>

	<div class="optipress-batch-progress-container" style="margin-top: 20px;">
		<div id="optipress-batch-progress" class="optipress-progress-bar" style="display: none;">
			<div class="optipress-progress-fill"></div>
		</div>
		<div id="optipress-batch-status" class="optipress-status-text" style="display: none; margin-top: 10px;"></div>
		<div id="optipress-batch-result" class="optipress-result-area" style="display: none; margin-top: 10px;"></div>

		<?php if ( $keep_originals ) : ?>
		<div id="optipress-revert-progress" class="optipress-progress-bar" style="display: none; margin-top: 20px;">
			<div class="optipress-progress-fill"></div>
		</div>
		<div id="optipress-revert-status" class="optipress-status-text" style="display: none; margin-top: 10px;"></div>
		<div id="optipress-revert-result" class="optipress-result-area" style="display: none; margin-top: 10px;"></div>
		<?php endif; ?>
	</div>

	<div class="notice notice-info inline" style="margin-top: 20px;">
		<p>
			<strong><?php esc_html_e( 'Performance Note:', 'optipress' ); ?></strong>
			<?php esc_html_e( 'Batch processing may take several minutes for large media libraries. The process is broken into small chunks to prevent server timeouts. You can safely leave this page during processing - the operation will continue in the background.', 'optipress' ); ?>
		</p>
	</div>
</div>

<!-- Thumbnail Regeneration Section -->
<div class="optipress-settings-section optipress-thumbs-section">
	<h2><?php esc_html_e( 'Regenerate Thumbnails', 'optipress' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Regenerate all thumbnail sizes for existing images using your current size profiles. Useful after changing thumbnail sizes or formats.', 'optipress' ); ?>
	</p>

	<div class="optipress-thumbs-stats">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Metric', 'optipress' ); ?></th>
					<th><?php esc_html_e( 'Count', 'optipress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Total Images', 'optipress' ); ?></td>
					<td><strong id="optipress-thumbs-total">-</strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Processed', 'optipress' ); ?></td>
					<td><strong id="optipress-thumbs-processed">0</strong></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="optipress-thumbs-controls" style="margin-top: 20px;">
		<button type="button" id="optipress-regenerate-thumbs" class="button button-primary" disabled>
			<?php esc_html_e( 'Regenerate All Thumbnails', 'optipress' ); ?>
		</button>
	</div>

	<div class="optipress-thumbs-progress-container" style="margin-top: 20px;">
		<div id="optipress-thumbs-progress" class="optipress-progress-bar" style="display: none;">
			<div class="optipress-progress-fill"></div>
		</div>
		<div id="optipress-thumbs-status" class="optipress-status-text" style="display: none; margin-top: 10px;"></div>
		<div id="optipress-thumbs-result" class="optipress-result-area" style="display: none; margin-top: 10px;"></div>
	</div>

	<div class="notice notice-info inline" style="margin-top: 20px;">
		<p>
			<strong><?php esc_html_e( 'Note:', 'optipress' ); ?></strong>
			<?php esc_html_e( 'This will delete and regenerate all thumbnail files for all images. Original files and preview files are not affected.', 'optipress' ); ?>
		</p>
	</div>
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

	// Thumbnail Regeneration
	var thumbsProcessing = false;

	function loadThumbsStats() {
		$.post(ajaxurl, {
			action: 'optipress_get_image_stats',
			nonce: optipressAdmin.nonce
		}, function(response) {
			if (response.success) {
				$('#optipress-thumbs-total').text(response.data.total);
				$('#optipress-regenerate-thumbs').prop('disabled', response.data.total === 0 || thumbsProcessing);
			}
		});
	}

	$('#optipress-regenerate-thumbs').on('click', function() {
		if (thumbsProcessing) return;
		if (!confirm('<?php echo esc_js( __( 'Regenerate all thumbnails? This will delete and recreate all thumbnail files. This may take several minutes.', 'optipress' ) ); ?>')) {
			return;
		}

		thumbsProcessing = true;
		$(this).prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'optipress' ) ); ?>');

		$('#optipress-thumbs-progress').show();
		$('#optipress-thumbs-status').show().text('<?php echo esc_js( __( 'Starting thumbnail regeneration...', 'optipress' ) ); ?>');
		$('#optipress-thumbs-result').hide();

		regenerateThumbsBatch(0);
	});

	function regenerateThumbsBatch(offset) {
		$.post(ajaxurl, {
			action: 'optipress_regenerate_thumbnails_batch',
			nonce: optipressAdmin.nonce,
			offset: offset
		}, function(response) {
			if (response.success) {
				var processed = response.data.processed || 0;
				var total = response.data.total || 1;
				var percent = Math.round((processed / total) * 100);

				$('#optipress-thumbs-processed').text(processed);
				$('#optipress-thumbs-progress .optipress-progress-fill').css('width', percent + '%');
				$('#optipress-thumbs-status').text('<?php echo esc_js( __( 'Processed', 'optipress' ) ); ?> ' + processed + ' / ' + total);

				if (response.data.done) {
					thumbsProcessing = false;
					$('#optipress-regenerate-thumbs').prop('disabled', false).text('<?php echo esc_js( __( 'Regenerate All Thumbnails', 'optipress' ) ); ?>');
					$('#optipress-thumbs-progress .optipress-progress-fill').css('width', '100%');
					$('#optipress-thumbs-status').text('<?php echo esc_js( __( 'Complete!', 'optipress' ) ); ?>');
					$('#optipress-thumbs-result').show().html('<div class="notice notice-success inline"><p>' + '<?php echo esc_js( __( 'Successfully regenerated thumbnails for', 'optipress' ) ); ?>' + ' ' + processed + ' ' + '<?php echo esc_js( __( 'image(s).', 'optipress' ) ); ?>' + '</p></div>');
				} else {
					setTimeout(function() {
						regenerateThumbsBatch(response.data.offset);
					}, 100);
				}
			} else {
				thumbsProcessing = false;
				$('#optipress-regenerate-thumbs').prop('disabled', false).text('<?php echo esc_js( __( 'Regenerate All Thumbnails', 'optipress' ) ); ?>');
				$('#optipress-thumbs-status').text('<?php echo esc_js( __( 'Error', 'optipress' ) ); ?>');
				$('#optipress-thumbs-result').show().html('<div class="notice notice-error inline"><p>' + (response.data && response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'optipress' ) ); ?>') + '</p></div>');
			}
		}).fail(function() {
			thumbsProcessing = false;
			$('#optipress-regenerate-thumbs').prop('disabled', false).text('<?php echo esc_js( __( 'Regenerate All Thumbnails', 'optipress' ) ); ?>');
			$('#optipress-thumbs-status').text('<?php echo esc_js( __( 'Error', 'optipress' ) ); ?>');
			$('#optipress-thumbs-result').show().html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Network error.', 'optipress' ) ); ?></p></div>');
		});
	}

	// Load thumbnail stats on page load
	loadThumbsStats();
});
</script>