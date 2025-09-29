/**
 * OptiPress Upload Progress Tracking
 *
 * @package OptiPress
 */

(function($) {
	'use strict';

	/**
	 * Initialize upload progress tracking
	 */
	$(document).ready(function() {
		if (typeof wp === 'undefined' || typeof wp.Uploader === 'undefined') {
			return;
		}

		// Extend the wp.Uploader to show conversion status
		wp.Uploader.prototype.init = (function(originalInit) {
			return function() {
				originalInit.apply(this, arguments);

				// Hook into upload success
				this.uploader.bind('FileUploaded', function(up, file, response) {
					handleUploadComplete(file, response);
				});

				// Hook into upload progress
				this.uploader.bind('UploadProgress', function(up, file) {
					showUploadProgress(file);
				});
			};
		})(wp.Uploader.prototype.init);

		/**
		 * Handle upload complete and show conversion status
		 */
		function handleUploadComplete(file, response) {
			try {
				var responseData = JSON.parse(response.response);

				if (!responseData.success || !responseData.data) {
					return;
				}

				var attachment = responseData.data;

				// Show conversion notification
				if (attachment.id) {
					showConversionStatus(file.id, attachment);
				}
			} catch (e) {
				console.error('OptiPress: Error parsing upload response', e);
			}
		}

		/**
		 * Show upload progress
		 */
		function showUploadProgress(file) {
			var $attachment = $('[data-id="' + file.id + '"]');

			if ($attachment.length) {
				// Add conversion notice
				if (!$attachment.find('.optipress-converting').length) {
					$attachment.find('.filename').after(
						'<div class="optipress-converting" style="color: #999; font-size: 11px; margin-top: 4px;">' +
						'<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>' +
						'Converting to WebP...' +
						'</div>'
					);
				}
			}
		}

		/**
		 * Show conversion status after upload
		 */
		function showConversionStatus(fileId, attachment) {
			var $attachment = $('[data-id="' + fileId + '"]');

			// Remove converting notice
			$attachment.find('.optipress-converting').remove();

			// Check if conversion data is available
			if (attachment.filesizeHumanReadable && attachment.filesizeHumanReadable.indexOf('saved') !== -1) {
				// Extract savings info from the filesizeHumanReadable field
				var savingsMatch = attachment.filesizeHumanReadable.match(/\(([^)]+saved[^)]+)\)/);

				if (savingsMatch) {
					var savingsText = savingsMatch[1];

					// Add success notice
					$attachment.find('.filename').after(
						'<div class="optipress-converted" style="color: #46b450; font-size: 11px; font-weight: 600; margin-top: 4px;">' +
						'✓ Converted • ' + savingsText +
						'</div>'
					);

					// Fade out after 5 seconds
					setTimeout(function() {
						$attachment.find('.optipress-converted').fadeOut(500, function() {
							$(this).remove();
						});
					}, 5000);
				}
			}
		}

		/**
		 * Add total savings counter to upload page
		 */
		if ($('body').hasClass('upload-php') || $('body').hasClass('post-new-php') || $('body').hasClass('post-php')) {
			addSavingsCounter();
		}

		/**
		 * Add savings counter to the page
		 */
		function addSavingsCounter() {
			// Check if counter already exists
			if ($('#optipress-savings-counter').length) {
				return;
			}

			// Add counter to the page
			var $counter = $('<div id="optipress-savings-counter" style="' +
				'position: fixed; ' +
				'bottom: 20px; ' +
				'right: 20px; ' +
				'background: #fff; ' +
				'border: 1px solid #c3c4c7; ' +
				'border-radius: 4px; ' +
				'padding: 15px 20px; ' +
				'box-shadow: 0 2px 5px rgba(0,0,0,0.1); ' +
				'z-index: 9999; ' +
				'display: none;' +
			'">' +
				'<div style="font-size: 12px; color: #666; margin-bottom: 5px;">OptiPress Session</div>' +
				'<div style="font-size: 18px; font-weight: 600; color: #46b450;">' +
					'<span id="optipress-session-savings">0 KB</span> saved' +
				'</div>' +
				'<div style="font-size: 11px; color: #999; margin-top: 5px;">' +
					'<span id="optipress-session-count">0</span> images converted' +
				'</div>' +
			'</div>');

			$('body').append($counter);

			// Update counter on new uploads
			var sessionSavings = 0;
			var sessionCount = 0;

			$(document).on('DOMNodeInserted', '.optipress-converted', function() {
				var $notice = $(this);
				var text = $notice.text();

				// Extract savings amount (e.g., "123.4 KB saved")
				var match = text.match(/([\d.]+)\s*(KB|MB|GB)\s*saved/i);

				if (match) {
					var amount = parseFloat(match[1]);
					var unit = match[2].toUpperCase();

					// Convert to KB
					if (unit === 'MB') {
						amount *= 1024;
					} else if (unit === 'GB') {
						amount *= 1024 * 1024;
					}

					sessionSavings += amount;
					sessionCount++;

					// Update display
					updateSavingsCounter(sessionSavings, sessionCount);
				}
			});
		}

		/**
		 * Update savings counter display
		 */
		function updateSavingsCounter(savings, count) {
			var $counter = $('#optipress-savings-counter');

			if (!$counter.length) {
				return;
			}

			// Format savings
			var formatted;
			if (savings >= 1024) {
				formatted = (savings / 1024).toFixed(1) + ' MB';
			} else {
				formatted = savings.toFixed(1) + ' KB';
			}

			// Update text
			$('#optipress-session-savings').text(formatted);
			$('#optipress-session-count').text(count);

			// Show counter
			if (count > 0) {
				$counter.fadeIn(300);
			}
		}
	});

})(jQuery);