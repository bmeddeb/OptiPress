/**
 * OptiPress Upload Progress Tracking
 *
 * @package OptiPress
 */

(function($) {
	'use strict';

	// Track attachments being processed
	var processingAttachments = {};
	var pollInterval = null;
	var sessionSavings = 0;
	var sessionCount = 0;
	var DEBUG = !!window.OPTIPRESS_DEBUG;

	function log(){ if (DEBUG && window.console && console.log) { console.log.apply(console, arguments); } }

	function deriveMimeFromFilename(name){
		if (!name) return '';
		var m = name.toLowerCase().match(/\.([a-z0-9]+)$/);
		if (!m) return '';
		switch(m[1]){
			case 'jpg':
			case 'jpeg': return 'image/jpeg';
			case 'png': return 'image/png';
			default: return '';
		}
	}

	function getAttachmentMime(att){
		if (!att) return '';
		if (att.mime) return att.mime;
		if (att.type && att.subtype) return (att.type + '/' + att.subtype).toLowerCase();
		return deriveMimeFromFilename(att.filename || att.name || '');
	}

	function isConvertibleMime(mime){
		if (!mime) return false;
		mime = mime.toLowerCase();
		return mime === 'image/jpeg' || mime === 'image/png';
	}

	/**
	 * Initialize upload progress tracking
	 */
	$(document).ready(function() {
		if (typeof wp === 'undefined' || typeof wp.Uploader === 'undefined') {
			return;
		}

		// Add savings counter
		addSavingsCounter();

		// Extend the wp.Uploader to show conversion status
		var originalSuccess = wp.Uploader.prototype.success;
		wp.Uploader.prototype.success = function(attachment) {
			originalSuccess.apply(this, arguments);
			if (DEBUG) log('OptiPress uploader success:', attachment);
			// Track this attachment for status polling
			if (attachment && attachment.id) {
				trackAttachment(attachment.id, attachment);
			}
		};

		// Also hook into Backbone uploader for media modal
		if (wp.Uploader.queue) {
			wp.Uploader.queue.on('add', function(attachment) {
				if (attachment && attachment.id) {
					trackAttachment(attachment.id, attachment);
				}
			});
		}
	});

	/**
	 * Track attachment for conversion status polling
	 */
	function trackAttachment(attachmentId, attachment) {
		var mimeType = getAttachmentMime(attachment);
		if (DEBUG) log('OptiPress trackAttachment:', attachmentId, mimeType, attachment);
		if (!isConvertibleMime(mimeType)) return;

		// Add to processing queue
		processingAttachments[attachmentId] = {
			id: attachmentId,
			filename: attachment.filename || attachment.name || 'image',
			attempts: 0,
			maxAttempts: 30 // 30 seconds max polling
		};

		// Show initial status
		showConversionStatus(attachmentId, 'processing');

		// Start polling if not already running
		if (!pollInterval) {
			startPolling();
		}
	}

	/**
	 * Start polling for conversion status
	 */
	function startPolling() {
		pollInterval = setInterval(function() {
			checkConversionStatuses();
		}, 1000); // Poll every second
	}

	/**
	 * Stop polling
	 */
	function stopPolling() {
		if (pollInterval) {
			clearInterval(pollInterval);
			pollInterval = null;
		}
	}

	/**
	 * Check conversion status for all processing attachments
	 */
	function checkConversionStatuses() {
		var attachmentIds = Object.keys(processingAttachments);

		if (attachmentIds.length === 0) {
			stopPolling();
			return;
		}

		// Check each attachment
		attachmentIds.forEach(function(attachmentId) {
			var attachment = processingAttachments[attachmentId];
			attachment.attempts++;

			// Check if max attempts reached
			if (attachment.attempts >= attachment.maxAttempts) {
				showConversionStatus(attachmentId, 'timeout');
				delete processingAttachments[attachmentId];
				return;
			}

			// Poll status
			pollConversionStatus(attachmentId);
		});
	}

	/**
	 * Poll conversion status for a single attachment
	 */
	function pollConversionStatus(attachmentId) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'optipress_check_conversion_status',
				nonce: optipressUpload.nonce,
				attachment_id: attachmentId
			},
			success: function(response) {
				if (response.success && response.data) {
					if (response.data.status === 'completed') {
						// Conversion complete
						showConversionStatus(attachmentId, 'completed', response.data);
						delete processingAttachments[attachmentId];

						// Update session counter
						updateSessionStats(response.data);
					} else if (response.data.status === 'processing') {
						// Still processing - status already showing
						showConversionStatus(attachmentId, 'processing', response.data);
					}
				}
			},
			error: function() {
				// Silently handle errors, will retry on next poll
			}
		});
	}

	/**
	 * Show conversion status message
	 */
	function showConversionStatus(attachmentId, status, data, retry) {
		retry = retry || 0;
		// Find attachment in grid view
		var $attachment = $('.attachment[data-id="' + attachmentId + '"]');

		// Also check media modal
		if (!$attachment.length) {
			$attachment = $('.attachment-preview[data-attachment-id="' + attachmentId + '"]').closest('.attachment');
		}

		if (!$attachment.length) {
			if (retry < 5) {
				return setTimeout(function(){ showConversionStatus(attachmentId, status, data, retry + 1); }, 150);
			}
			return;
		}

		// Remove existing status
		$attachment.find('.optipress-status').remove();

		var $status;

		switch (status) {
			case 'processing':
				$status = $('<div class="optipress-status optipress-processing">' +
					'<span class="spinner is-active"></span>' +
					'<span class="optipress-status-text">' + (data && data.message ? data.message : 'Converting image...') + '</span>' +
				'</div>');
				break;

			case 'completed':
				var message = data && data.message ? data.message : 'Conversion complete';
				var percentSaved = data && data.percent_saved ? Math.abs(data.percent_saved).toFixed(1) : 0;

				$status = $('<div class="optipress-status optipress-completed">' +
					'<span class="dashicons dashicons-yes-alt"></span>' +
					'<span class="optipress-status-text">' + message + '</span>' +
				'</div>');

				// Fade out after 8 seconds
				setTimeout(function() {
					$status.fadeOut(500, function() {
						$(this).remove();
					});
				}, 8000);
				break;

			case 'timeout':
				$status = $('<div class="optipress-status optipress-timeout">' +
					'<span class="dashicons dashicons-warning"></span>' +
					'<span class="optipress-status-text">Conversion taking longer than expected...</span>' +
				'</div>');

				// Fade out after 5 seconds
				setTimeout(function() {
					$status.fadeOut(500, function() {
						$(this).remove();
					});
				}, 5000);
				break;
		}

		if ($status) {
			// Try to append to different locations depending on view
			var $target = $attachment.find('.attachment-preview');
			if (!$target.length) {
				$target = $attachment;
			}
			$target.append($status);
		}
	}

	/**
	 * Update session statistics
	 */
	function updateSessionStats(data) {
		if (data.bytes_saved && data.bytes_saved > 0) {
			sessionSavings += data.bytes_saved;
			sessionCount++;
			updateSavingsCounter();
		}
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
		var $counter = $('<div id="optipress-savings-counter" class="optipress-counter-hidden">' +
			'<div class="optipress-counter-label">OptiPress Session</div>' +
			'<div class="optipress-counter-value">' +
				'<span id="optipress-session-savings">0 KB</span> saved' +
			'</div>' +
			'<div class="optipress-counter-count">' +
				'<span id="optipress-session-count">0</span> images optimized' +
			'</div>' +
		'</div>');

		$('body').append($counter);
	}

	/**
	 * Update savings counter display
	 */
	function updateSavingsCounter() {
		var $counter = $('#optipress-savings-counter');

		if (!$counter.length) {
			return;
		}

		// Format savings
		var formatted;
		var savingsKB = sessionSavings / 1024;

		if (savingsKB >= 1024) {
			formatted = (savingsKB / 1024).toFixed(1) + ' MB';
		} else {
			formatted = savingsKB.toFixed(1) + ' KB';
		}

		// Update text
		$('#optipress-session-savings').text(formatted);
		$('#optipress-session-count').text(sessionCount);

		// Show counter
		if (sessionCount > 0) {
			$counter.removeClass('optipress-counter-hidden');
		}
	}

})(jQuery);
