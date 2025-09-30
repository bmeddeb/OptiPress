/**
 * OptiPress Upload Progress Tracking
 *
 * @package OptiPress
 */

(function ($) {
	'use strict';

	// Track attachments being processed
	var processingAttachments = {};
	var pollInterval = null;
	var sessionSavings = 0;
	var sessionCount = 0;
	var DEBUG = !!window.OPTIPRESS_DEBUG;

	function log() {
		if (DEBUG && window.console && console.log) {
			console.log.apply(console, arguments);
		}
	}

	function deriveMimeFromFilename(name) {
		if (!name) return '';
		var m = name.toLowerCase().match(/\.([a-z0-9]+)$/);
		if (!m) return '';
		switch (m[1]) {
			case 'jpg':
			case 'jpeg':
				return 'image/jpeg';
			case 'png':
				return 'image/png';
			default:
				return '';
		}
	}

	function getAttachmentMime(att) {
		if (!att) return '';
		if (att.mime) return att.mime;
		if (att.type && att.subtype) return (att.type + '/' + att.subtype).toLowerCase();
		return deriveMimeFromFilename(att.filename || att.name || '');
	}

	function isConvertibleMime(mime) {
		if (!mime) return false;
		mime = mime.toLowerCase();
		return mime === 'image/jpeg' || mime === 'image/png';
	}

	/**
	 * Initialize upload progress tracking
	 */
	$(document).ready(function () {
		if (typeof wp === 'undefined' || typeof wp.Uploader === 'undefined') {
			return;
		}

		// Add savings counter and status panel
		addSavingsCounter();
		ensureUploadPanel();

		// Extend the wp.Uploader to show conversion status
		var originalSuccess = wp.Uploader.prototype.success;
		wp.Uploader.prototype.success = function (attachment) {
			originalSuccess.apply(this, arguments);
			if (DEBUG) log('OptiPress uploader success:', attachment);
			// Track this attachment for status polling
			if (attachment && attachment.id) {
				trackAttachment(attachment.id, attachment);
			}
		};

		// Also hook into Backbone uploader for media modal
		if (wp.Uploader.queue) {
			wp.Uploader.queue.on('add', function (attachment) {
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
			maxAttempts: 30, // 30 seconds max polling
			fastPollUntil: Date.now() + 5000, // Fast poll for first 5 seconds
		};

		// Show initial status immediately
		showConversionStatus(attachmentId, 'processing');

		// Start polling immediately (don't wait 1 second)
		pollConversionStatus(attachmentId);

		// Start polling interval if not already running
		if (!pollInterval) {
			startPolling();
		}
	}

	/**
	 * Start polling for conversion status
	 */
	function startPolling() {
		// Use faster polling for first few seconds to catch quick conversions
		var pollCount = 0;

		pollInterval = setInterval(function () {
			pollCount++;
			checkConversionStatuses();

			// Switch to slower polling after 10 fast polls (2 seconds)
			if (pollCount === 10 && pollInterval) {
				clearInterval(pollInterval);
				pollInterval = setInterval(checkConversionStatuses, 1000);
			}
		}, 200); // Poll every 200ms initially
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
		attachmentIds.forEach(function (attachmentId) {
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
				attachment_id: attachmentId,
			},
			success: function (response) {
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
					} else if (response.data.status === 'not_applicable') {
						// Not a convertible image or conversion disabled
						delete processingAttachments[attachmentId];
						// Don't show any status for non-convertible images
					}
				}
			},
			error: function () {
				// Silently handle errors, will retry on next poll
			},
		});
	}

	/**
	 * Show conversion status message
	 */
	function showConversionStatus(attachmentId, status, data) {
		ensureUploadPanel();
		updateUploadPanelItem(attachmentId, status, data);
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
		var $counter = $(
			'<div id="optipress-savings-counter" class="optipress-counter-hidden">' +
				'<div class="optipress-counter-label">OptiPress Session</div>' +
				'<div class="optipress-counter-value">' +
				'<span id="optipress-session-savings">0 KB</span> saved' +
				'</div>' +
				'<div class="optipress-counter-count">' +
				'<span id="optipress-session-count">0</span> images optimized' +
				'</div>' +
				'</div>'
		);

		$('body').append($counter);
	}

	/**
	 * Upload status panel
	 */
	function ensureUploadPanel() {
		if ($('#optipress-upload-panel').length) return;
		var $panel = $(
			'<div id="optipress-upload-panel" class="hidden">' +
				'<div class="optipress-upload-header">' +
				'<span>OptiPress Uploads</span>' +
				'<button type="button" class="optipress-upload-remove" title="Hide" aria-label="Hide">×</button>' +
				'</div>' +
				'<ul class="optipress-upload-list" id="optipress-upload-list"></ul>' +
				'</div>'
		);
		$panel.on('click', '.optipress-upload-remove', function () {
			$('#optipress-upload-panel').toggleClass('hidden');
		});
		$('body').append($panel);
	}

	function updateUploadPanelVisibility() {
		var $list = $('#optipress-upload-list');
		var hasItems = $list.children().length > 0;
		$('#optipress-upload-panel').toggleClass('hidden', !hasItems);
	}

	function updateUploadPanelItem(attachmentId, status, data) {
		var $list = $('#optipress-upload-list');
		var id = String(attachmentId);
		var $item = $list.find('[data-id="' + id + '"]');
		var filename =
			(data && data.filename) ||
			(processingAttachments[id] && processingAttachments[id].filename) ||
			'#' + id;
		var message;
		switch (status) {
			case 'processing':
				message = (data && data.message) || 'Converting image...';
				break;
			case 'completed':
				message = (data && data.message) || 'Conversion complete';
				break;
			case 'timeout':
				message = 'Conversion taking longer than expected...';
				break;
		}

		if (!$item.length) {
			$item = $(
				'<li class="optipress-upload-item processing" data-id="' +
					id +
					'">' +
					'<span class="status-icon"><span class="spinner is-active"></span></span>' +
					'<div class="optipress-upload-filename" title="' +
					filename +
					'">' +
					filename +
					'</div>' +
					'<div class="optipress-upload-message">' +
					message +
					'</div>' +
					'</li>'
			);
			$list.prepend($item);
		} else {
			$item.find('.optipress-upload-message').text(message);
		}

		$item.removeClass('processing completed timeout');
		switch (status) {
			case 'processing':
				$item.addClass('processing');
				$item.find('.status-icon').html('<span class="spinner is-active"></span>');
				break;
			case 'completed':
				$item.addClass('completed');
				$item
					.find('.status-icon')
					.html('<span class="dashicons dashicons-yes-alt"></span>');
				setTimeout(function () {
					$item.fadeOut(300, function () {
						$(this).remove();
						updateUploadPanelVisibility();
					});
				}, 8000);
				break;
			case 'timeout':
				$item.addClass('timeout');
				$item
					.find('.status-icon')
					.html('<span class="dashicons dashicons-warning"></span>');
				setTimeout(function () {
					$item.fadeOut(300, function () {
						$(this).remove();
						updateUploadPanelVisibility();
					});
				}, 5000);
				break;
		}

		updateUploadPanelVisibility();
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
