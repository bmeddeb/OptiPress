/**
 * OptiPress Admin Settings JavaScript
 *
 * @package OptiPress
 */

(function($) {
	'use strict';

	/**
	 * Initialize admin settings functionality
	 */
	$(document).ready(function() {
		// Initialize quality slider
		initQualitySlider();

		// Initialize compatibility checker (only if on optimization tab)
		if ($('#optipress_engine').length) {
			initCompatibilityChecker();
		}

		// Confirm before disabling "Keep Originals"
		initKeepOriginalsWarning();
	});

	/**
	 * Initialize quality slider
	 */
	function initQualitySlider() {
		const $slider = $('#optipress_quality');
		const $display = $('#optipress-quality-value');

		if (!$slider.length || !$display.length) {
			return;
		}

		// Update display on slider change
		$slider.on('input', function() {
			$display.text($(this).val());
		});
	}

	/**
	 * Initialize compatibility checker
	 */
	function initCompatibilityChecker() {
		const $engine = $('#optipress_engine');
		const $format = $('#optipress_format');
		const $status = $('#optipress-compatibility-status');

		if (!$engine.length || !$format.length || !$status.length) {
			return;
		}

		/**
		 * Check engine/format compatibility via AJAX
		 */
		function checkCompatibility() {
			const engine = $engine.val();
			const format = $format.val();

			// Show loading spinner
			$status.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');

			// AJAX request
			$.ajax({
				url: optipressAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'optipress_check_compatibility',
					nonce: optipressAdmin.nonce,
					engine: engine,
					format: format
				},
				success: function(response) {
					if (response.success) {
						$status.html('<span class="optipress-status-success">✓ ' + response.data.message + '</span>');
					} else {
						$status.html('<span class="optipress-status-error">✗ ' + response.data.message + '</span>');
					}
				},
				error: function() {
					$status.html('<span class="optipress-status-error">✗ ' + 'Failed to check compatibility' + '</span>');
				}
			});
		}

		// Check on change
		$engine.on('change', checkCompatibility);
		$format.on('change', checkCompatibility);

		// Check on page load
		checkCompatibility();
	}

	/**
	 * Initialize "Keep Originals" warning
	 */
	function initKeepOriginalsWarning() {
		const $checkbox = $('input[name="optipress_options[keep_originals]"]');

		if (!$checkbox.length) {
			return;
		}

		$checkbox.on('change', function() {
			if (!$(this).is(':checked')) {
				var message = 'Warning: Disabling "Keep Originals" will permanently delete original image files after conversion.\n\n' +
					'This cannot be undone, and you will not be able to revert conversions.\n\n' +
					'Are you sure you want to disable this option?';

				// Use OptipressNotices.createConfirm if available (non-blocking), fallback to window.confirm
				if ( typeof OptipressNotices !== 'undefined' && OptipressNotices.createConfirm ) {
					OptipressNotices.createConfirm(message, { confirmLabel: 'Disable', cancelLabel: 'Keep Originals' })
						.then(function(confirmed) {
							if (!confirmed) {
								$("input[name=\"optipress_options[keep_originals]\"]").prop('checked', true);
							}
						});
				} else {
					if (!window.confirm(message)) {
						$("input[name=\"optipress_options[keep_originals]\"]").prop('checked', true);
					}
				}
			}
		});
	}

})(jQuery);