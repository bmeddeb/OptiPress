/**
 * Batch Processing JavaScript
 *
 * Handles AJAX-driven batch processing for image conversion, revert, and SVG sanitization.
 *
 * @package OptiPress
 */

(function($) {
	'use strict';

	/**
	 * Batch Processor Object
	 */
	var OptipressBatchProcessor = {

		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.loadStats();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
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
		loadStats: function() {
			var self = this;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'optipress_get_batch_stats',
					nonce: optipressAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						self.updateStats(response.data);
					}
				}
			});
		},

		/**
		 * Update statistics display
		 */
		updateStats: function(stats) {
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
		startBatchConversion: function(e) {
			e.preventDefault();

			// Ask for confirmation using non-blocking confirmation helper when available
			var proceedWithBatch = function() {
				var remaining = parseInt($('#optipress-remaining-images').text());
				var total = remaining;

				this.processBatch({
				action: 'optipress_process_batch',
				total: total,
				processed: 0,
				offset: 0,
				progressBar: '#optipress-batch-progress',
				statusText: '#optipress-batch-status',
				button: '#optipress-start-batch',
				successCallback: this.onBatchComplete.bind(this),
				progressText: optipressAdmin.i18n.processing
			});
			}.bind(this);

			if ( typeof OptipressNotices !== 'undefined' && OptipressNotices.createConfirm ) {
				OptipressNotices.createConfirm(optipressAdmin.i18n.confirmBatch)
					.then(function(confirmed){
						if ( confirmed ) {
							proceedWithBatch();
						}
					});
			} else {
				if ( confirm(optipressAdmin.i18n.confirmBatch) ) {
					proceedWithBatch();
				}
			}
		},

		/**
		 * Start revert process
		 */
		startRevert: function(e) {
			e.preventDefault();

			var proceedWithRevert = function() {
				var converted = parseInt($('#optipress-converted-images').text());
				var total = converted;

				this.processBatch({
				action: 'optipress_revert_images',
				total: total,
				processed: 0,
				offset: 0,
				progressBar: '#optipress-revert-progress',
				statusText: '#optipress-revert-status',
				button: '#optipress-revert-batch',
				successCallback: this.onRevertComplete.bind(this),
				progressText: optipressAdmin.i18n.reverting
			});
			}.bind(this);

			if ( typeof OptipressNotices !== 'undefined' && OptipressNotices.createConfirm ) {
				OptipressNotices.createConfirm(optipressAdmin.i18n.confirmRevert)
					.then(function(confirmed){
						if ( confirmed ) {
							proceedWithRevert();
						}
					});
			} else {
				if ( confirm(optipressAdmin.i18n.confirmRevert) ) {
					proceedWithRevert();
				}
			}
		},

		/**
		 * Start SVG sanitization process
		 */
		startSvgSanitization: function(e) {
			e.preventDefault();

			var proceedWithSvg = function() {
				var total = parseInt($('#optipress-total-svgs').text());

				this.processBatch({
				action: 'optipress_sanitize_svg_batch',
				total: total,
				processed: 0,
				offset: 0,
				progressBar: '#optipress-svg-batch-progress',
				statusText: '#optipress-svg-batch-status',
				button: '#optipress-sanitize-svg-batch',
				successCallback: this.onSvgBatchComplete.bind(this),
				progressText: optipressAdmin.i18n.sanitizing
			});
			}.bind(this);

			if ( typeof OptipressNotices !== 'undefined' && OptipressNotices.createConfirm ) {
				OptipressNotices.createConfirm(optipressAdmin.i18n.confirmSvgBatch)
					.then(function(confirmed){
						if ( confirmed ) {
							proceedWithSvg();
						}
					});
			} else {
				if ( confirm(optipressAdmin.i18n.confirmSvgBatch) ) {
					proceedWithSvg();
				}
			}
		},

		/**
		 * Process a batch recursively
		 */
		processBatch: function(options) {
			var self = this;
			var $progressBar = $(options.progressBar);
			var $statusText = $(options.statusText);
			var $button = $(options.button);

			// Disable button
			$button.prop('disabled', true);

			// Show progress
			$progressBar.show().find('.optipress-progress-fill').css('width', '0%');
			$statusText.show().text(options.progressText + ' 0 / ' + options.total);

			// Process chunk
			this.processChunk(options, function(error, result) {
				if (error) {
					$statusText.text(optipressAdmin.i18n.error + ': ' + error).addClass('optipress-error');
					$button.prop('disabled', false);
					return;
				}

				// Update progress
				var processed = options.processed + result.batch_size;
				var percentage = Math.min(100, Math.round((processed / options.total) * 100));

				$progressBar.find('.optipress-progress-fill').css('width', percentage + '%');
				$statusText.text(options.progressText + ' ' + processed + ' / ' + options.total);

				// Check if complete
				if (processed >= options.total || result.batch_size === 0) {
					$statusText.text(optipressAdmin.i18n.complete + ' ' + processed + ' / ' + options.total).removeClass('optipress-error');
					$button.prop('disabled', false);

					if (options.successCallback) {
						options.successCallback(processed);
					}

					// Reload stats
					self.loadStats();
				} else {
					// Continue processing
					options.processed = processed;
					options.offset = processed;
					setTimeout(function() {
						self.processBatch(options);
					}, 500); // Small delay to prevent server overload
				}
			});
		},

		/**
		 * Process a single chunk
		 */
		processChunk: function(options, callback) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: options.action,
					nonce: optipressAdmin.nonce,
					offset: options.offset
				},
				success: function(response) {
					if (response.success) {
						callback(null, response.data);
					} else {
						callback(response.data.message || optipressAdmin.i18n.unknownError);
					}
				},
				error: function(xhr, status, error) {
					callback(error || optipressAdmin.i18n.unknownError);
				}
			});
		},

		/**
		 * Batch conversion complete callback
		 */
		onBatchComplete: function(processed) {
			var message = optipressAdmin.i18n.batchComplete.replace('%d', processed);
			if ( typeof wp !== 'undefined' && wp.data && wp.data.dispatch ) {
				try {
					wp.data.dispatch('core/notices').createNotice( 'success', message, { isDismissible: true } );
				} catch (e) {
					OptipressNotices.createNotice('success', message, { isDismissible: true });
				}
			} else {
				OptipressNotices.createNotice('success', message, { isDismissible: true });
			}
		},

		/**
		 * Revert complete callback
		 */
		onRevertComplete: function(processed) {
			var message = optipressAdmin.i18n.revertComplete.replace('%d', processed);
			if ( typeof wp !== 'undefined' && wp.data && wp.data.dispatch ) {
				try {
					wp.data.dispatch('core/notices').createNotice( 'success', message, { isDismissible: true } );
				} catch (e) {
					OptipressNotices.createNotice('success', message, { isDismissible: true });
				}
			} else {
				OptipressNotices.createNotice('success', message, { isDismissible: true });
			}
		},

		/**
		 * SVG batch complete callback
		 */
		onSvgBatchComplete: function(processed) {
			var message = optipressAdmin.i18n.svgBatchComplete.replace('%d', processed);
			if ( typeof wp !== 'undefined' && wp.data && wp.data.dispatch ) {
				try {
					wp.data.dispatch('core/notices').createNotice( 'success', message, { isDismissible: true } );
				} catch (e) {
					OptipressNotices.createNotice('success', message, { isDismissible: true });
				}
			} else {
				OptipressNotices.createNotice('success', message, { isDismissible: true });
			}
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		if ($('.optipress-batch-section').length > 0) {
			OptipressBatchProcessor.init();
		}
	});

})(jQuery);