/**
 * Batch Processing JavaScript
 *
 * Handles AJAX-driven batch processing for image conversion, revert, and SVG sanitization.
 *
 * @package OptiPress
 */

(function ($) {
	'use strict';

	/**
	 * Batch Processor Object
	 */
	var OptipressBatchProcessor = {
		// Timer for debouncing stats refresh
		statsRefreshTimer: null,

		/**
		 * Initialize
		 */
		init: function () {
			this.bindEvents();
			this.debouncedLoadStats();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			// Image conversion
			$('#optipress-start-batch').on('click', this.startBatchConversion.bind(this));

			// Revert images
			$('#optipress-revert-batch').on('click', this.startRevert.bind(this));

			// SVG sanitization
			$('#optipress-sanitize-svg-batch').on('click', this.startSvgSanitization.bind(this));
		},

		/**
		 * Load batch statistics
		 */
		loadStats: function () {
			var self = this;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'optipress_get_batch_stats',
					nonce: optipressAdmin.nonce,
				},
				success: function (response) {
					if (response.success) {
						self.updateStats(response.data);
					}
				},
			});
		},

		/**
		 * Load stats with debouncing to prevent excessive AJAX calls
		 */
		debouncedLoadStats: function () {
			var self = this;

			// Clear existing timer
			if (this.statsRefreshTimer) {
				clearTimeout(this.statsRefreshTimer);
			}

			// Set new timer to load stats after 750ms of inactivity
			this.statsRefreshTimer = setTimeout(function () {
				self.loadStats();
			}, 750);
		},

		/**
		 * Update statistics display
		 */
		updateStats: function (stats) {
			// Update image conversion stats
			$('#optipress-total-images').text(stats.total || 0);
			$('#optipress-converted-images').text(stats.converted || 0);
			$('#optipress-remaining-images').text(stats.remaining || 0);

			// Update SVG stats
			$('#optipress-total-svgs').text(stats.svg_total || 0);

			// Enable/disable buttons
			if (stats.remaining > 0) {
				$('#optipress-start-batch').prop('disabled', false);
			} else {
				$('#optipress-start-batch').prop('disabled', true);
			}

			if (stats.converted > 0) {
				$('#optipress-revert-batch').prop('disabled', false);
			} else {
				$('#optipress-revert-batch').prop('disabled', true);
			}

			if (stats.svg_total > 0) {
				$('#optipress-sanitize-svg-batch').prop('disabled', false);
			} else {
				$('#optipress-sanitize-svg-batch').prop('disabled', true);
			}
		},

		/**
		 * Start batch conversion process
		 */
		startBatchConversion: function (e) {
			e.preventDefault();

			// Ask for confirmation using non-blocking confirmation helper when available
			var proceedWithBatch = function () {
				var remaining = parseInt($('#optipress-remaining-images').text());
				var total = remaining;

				this.processBatch({
					action: 'optipress_process_batch',
					resultKey: 'processed',
					total: total,
					processed: 0,
					offset: 0,
					progressBar: '#optipress-batch-progress',
					statusText: '#optipress-batch-status',
					resultArea: '#optipress-batch-result',
					button: '#optipress-start-batch',
					successCallback: this.onBatchComplete.bind(this),
					progressText: optipressAdmin.i18n.processing,
					startTime: Date.now(),
					batchCount: 0,
				});
			}.bind(this);

			// Use non-blocking confirmation helper (helper is enqueued as a dependency)
			OptipressNotices.createConfirm(optipressAdmin.i18n.confirmBatch).then(
				function (confirmed) {
					if (confirmed) {
						proceedWithBatch();
					}
				}
			);
		},

		/**
		 * Start revert process
		 */
		startRevert: function (e) {
			e.preventDefault();

			var proceedWithRevert = function () {
				var converted = parseInt($('#optipress-converted-images').text());
				var total = converted;

				this.processBatch({
					action: 'optipress_revert_images',
					resultKey: 'reverted',
					total: total,
					processed: 0,
					offset: 0,
					progressBar: '#optipress-revert-progress',
					statusText: '#optipress-revert-status',
					resultArea: '#optipress-revert-result',
					button: '#optipress-revert-batch',
					successCallback: this.onRevertComplete.bind(this),
					progressText: optipressAdmin.i18n.reverting,
					startTime: Date.now(),
					batchCount: 0,
				});
			}.bind(this);

			OptipressNotices.createConfirm(optipressAdmin.i18n.confirmRevert).then(
				function (confirmed) {
					if (confirmed) {
						proceedWithRevert();
					}
				}
			);
		},

		/**
		 * Start SVG sanitization process
		 */
		startSvgSanitization: function (e) {
			e.preventDefault();

			var proceedWithSvg = function () {
				var total = parseInt($('#optipress-total-svgs').text());

				this.processBatch({
					action: 'optipress_sanitize_svg_batch',
					resultKey: 'sanitized',
					total: total,
					processed: 0,
					offset: 0,
					progressBar: '#optipress-svg-batch-progress',
					statusText: '#optipress-svg-batch-status',
					resultArea: '#optipress-svg-batch-result',
					button: '#optipress-sanitize-svg-batch',
					successCallback: this.onSvgBatchComplete.bind(this),
					progressText: optipressAdmin.i18n.sanitizing,
					startTime: Date.now(),
					batchCount: 0,
				});
			}.bind(this);

			OptipressNotices.createConfirm(optipressAdmin.i18n.confirmSvgBatch).then(
				function (confirmed) {
					if (confirmed) {
						proceedWithSvg();
					}
				}
			);
		},

		/**
		 * Process a batch recursively
		 */
		processBatch: function (options) {
			var self = this;
			var $progressBar = $(options.progressBar);
			var $statusText = $(options.statusText);
			var $resultArea = $(options.resultArea);
			var $button = $(options.button);

			// Disable button
			$button.prop('disabled', true);

			// Hide result area and show progress on first run
			if (options.processed === 0) {
				$resultArea.hide();
				$progressBar.show().find('.optipress-progress-fill').css('width', '0%');
				$statusText
					.show()
					.removeClass('optipress-error optipress-success')
					.text(options.progressText + ' 0 / ' + options.total + ' (0%)');
			}

			// Increment batch counter
			options.batchCount++;

			// Process chunk
			this.processChunk(options, function (error, result) {
				if (error) {
					$statusText
						.text(optipressAdmin.i18n.error + ': ' + error)
						.addClass('optipress-error');
					$button.prop('disabled', false);
					return;
				}

				// Update processed count - use the correct result key based on operation type
				var newlyProcessed = result[options.resultKey] || 0;
				options.processed += newlyProcessed;

				// Calculate progress
				var percentage = Math.min(
					100,
					Math.round((options.processed / options.total) * 100)
				);

				// Calculate time estimates (avoid division by zero)
				var elapsed = (Date.now() - options.startTime) / 1000; // seconds
				var timeText = '';

				if (options.processed > 0) {
					var avgTimePerImage = elapsed / options.processed;
					var remaining = options.total - options.processed;
					var estimatedTimeLeft = Math.ceil(avgTimePerImage * remaining);

					// Format time remaining
					if (estimatedTimeLeft > 60) {
						timeText = ' (~' + Math.ceil(estimatedTimeLeft / 60) + ' min remaining)';
					} else if (estimatedTimeLeft > 0) {
						timeText = ' (~' + estimatedTimeLeft + ' sec remaining)';
					}
				}

				// Update progress bar
				$progressBar.find('.optipress-progress-fill').css('width', percentage + '%');

				// Update status with detailed info
				var statusMessage =
					options.progressText +
					' ' +
					options.processed +
					' / ' +
					options.total +
					' (' +
					percentage +
					'%)' +
					timeText;
				$statusText.text(statusMessage);

				// Check if complete
				if (options.processed >= options.total || result.batch_size === 0) {
					var totalTime = Math.ceil(elapsed);
					var timeStr =
						totalTime > 60
							? Math.ceil(totalTime / 60) + ' minutes'
							: totalTime + ' seconds';

					$statusText
						.text(
							optipressAdmin.i18n.complete +
								' ' +
								options.processed +
								' / ' +
								options.total +
								' (took ' +
								timeStr +
								')'
						)
						.removeClass('optipress-error')
						.addClass('optipress-success');
					$button.prop('disabled', false);

					if (options.successCallback) {
						options.successCallback(options.processed, $resultArea);
					}

					// Auto-dismiss status text and progress bar after 4 seconds
					setTimeout(function () {
						$statusText.fadeOut(200, function () {
							$(this).text('').hide().removeClass('optipress-success');
						});
						$progressBar.find('.optipress-progress-fill').css('width', '0%');
						$progressBar.fadeOut(200);
					}, 4000);

					// Reload stats after a short delay to ensure DB updates are complete
					setTimeout(function () {
						self.debouncedLoadStats();
					}, 500);
				} else {
					// Continue processing next batch
					options.offset += result.batch_size; // Move offset by batch size (IDs fetched)
					setTimeout(function () {
						self.processBatch(options);
					}, 500); // Small delay to prevent server overload
				}
			});
		},

		/**
		 * Process a single chunk
		 */
		processChunk: function (options, callback) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: options.action,
					nonce: optipressAdmin.nonce,
					offset: options.offset,
				},
				success: function (response) {
					if (response.success) {
						callback(null, response.data);
					} else {
						callback(response.data.message || optipressAdmin.i18n.unknownError);
					}
				},
				error: function (xhr, status, error) {
					callback(error || optipressAdmin.i18n.unknownError);
				},
			});
		},

		/**
		 * Show local success message
		 */
		showLocalSuccess: function (message, $container) {
			$container
				.html(
					'<div class="optipress-local-notice optipress-local-notice-success">' +
						'<span class="dashicons dashicons-yes-alt"></span>' +
						'<span>' +
						message +
						'</span>' +
						'</div>'
				)
				.show();

			// Auto-hide after 4 seconds
			setTimeout(function () {
				var $notice = $container.find('.optipress-local-notice');
				$notice.fadeOut(200, function () {
					$(this).remove();
				});
			}, 4000);
		},

		/**
		 * Batch conversion complete callback
		 */
		onBatchComplete: function (processed, $resultArea) {
			var message = optipressAdmin.i18n.batchComplete.replace('%d', processed);

			// Show local message in result area
			this.showLocalSuccess(message, $resultArea);

			// Also show at top for visibility (optional - can be removed if not desired)
			if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
				try {
					wp.data
						.dispatch('core/notices')
						.createNotice('success', message, { isDismissible: true });
				} catch (e) {
					// Fallback handled by local notice
				}
			}
		},

		/**
		 * Revert complete callback
		 */
		onRevertComplete: function (processed, $resultArea) {
			var message = optipressAdmin.i18n.revertComplete.replace('%d', processed);

			// Show local message in result area
			this.showLocalSuccess(message, $resultArea);

			// Also show at top for visibility (optional)
			if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
				try {
					wp.data
						.dispatch('core/notices')
						.createNotice('success', message, { isDismissible: true });
				} catch (e) {
					// Fallback handled by local notice
				}
			}
		},

		/**
		 * SVG batch complete callback
		 */
		onSvgBatchComplete: function (processed, $resultArea) {
			var message = optipressAdmin.i18n.svgBatchComplete.replace('%d', processed);

			// Show local message in result area
			this.showLocalSuccess(message, $resultArea);

			// Also show at top for visibility (optional)
			if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
				try {
					wp.data
						.dispatch('core/notices')
						.createNotice('success', message, { isDismissible: true });
				} catch (e) {
					// Fallback handled by local notice
				}
			}
		},
	};

	// Initialize on document ready
	$(document).ready(function () {
		if ($('.optipress-batch-section').length > 0) {
			OptipressBatchProcessor.init();
		}
	});
})(jQuery);
