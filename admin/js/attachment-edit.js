/**
 * OptiPress Attachment Edit Page
 *
 * Handles conversion controls on individual attachment edit page.
 *
 * @package OptiPress
 */

(function ($) {
	'use strict';

	var processing = false;

	$(document).ready(function () {
		// Convert Image button
		$('#optipress-meta-box-content').on('click', '.optipress-convert-image', function (e) {
			e.preventDefault();

			if (processing) return;

			var $button = $(this);
			var attachmentId = $('#optipress-meta-box-content').data('attachment-id');
			var format = $('input[name="optipress_format"]:checked').val();

			if (!format) {
				alert(optipressAttachment.i18n.error + ': No format selected.');
				return;
			}

			convertImage(attachmentId, format);
		});

		// Revert Image button
		$('#optipress-meta-box-content').on('click', '.optipress-revert-image', function (e) {
			e.preventDefault();

			if (processing) return;

			if (!confirm(optipressAttachment.i18n.confirmRevert)) {
				return;
			}

			var attachmentId = $('#optipress-meta-box-content').data('attachment-id');
			revertImage(attachmentId);
		});

		// Switch Format button
		$('#optipress-meta-box-content').on('click', '.optipress-switch-format', function (e) {
			e.preventDefault();

			if (processing) return;

			var $button = $(this);
			var attachmentId = $('#optipress-meta-box-content').data('attachment-id');
			var format = $button.data('format');

			switchFormat(attachmentId, format);
		});
	});

	/**
	 * Convert image to specified format
	 */
	function convertImage(attachmentId, format) {
		processing = true;
		showLoading(optipressAttachment.i18n.converting);

		$.ajax({
			url: optipressAttachment.ajaxUrl,
			type: 'POST',
			data: {
				action: 'optipress_convert_single_image',
				nonce: optipressAttachment.nonce,
				attachment_id: attachmentId,
				format: format,
			},
			success: function (response) {
				processing = false;

				if (response.success) {
					// Show success message using WordPress notices
					showNotice(response.data.message, 'success');

					// Update meta box content
					if (response.data.html) {
						$('#optipress-meta-box-content').html(response.data.html);
					}

					// Reload attachment data in media modal if open
					reloadAttachmentData(attachmentId);
				} else {
					var errorMsg =
						response.data && response.data.message
							? response.data.message
							: optipressAttachment.i18n.unknownError;

					showNotice(optipressAttachment.i18n.conversionError + ' ' + errorMsg, 'error');

					hideLoading();
				}
			},
			error: function (xhr, status, error) {
				processing = false;
				showNotice(optipressAttachment.i18n.error + ': ' + error, 'error');
				hideLoading();
			},
		});
	}

	/**
	 * Revert image to original
	 */
	function revertImage(attachmentId) {
		processing = true;
		showLoading(optipressAttachment.i18n.reverting);

		$.ajax({
			url: optipressAttachment.ajaxUrl,
			type: 'POST',
			data: {
				action: 'optipress_revert_single_image',
				nonce: optipressAttachment.nonce,
				attachment_id: attachmentId,
			},
			success: function (response) {
				processing = false;

				if (response.success) {
					// Show success message
					showNotice(response.data.message, 'success');

					// Update meta box content
					if (response.data.html) {
						$('#optipress-meta-box-content').html(response.data.html);
					}

					// Reload attachment data
					reloadAttachmentData(attachmentId);
				} else {
					var errorMsg =
						response.data && response.data.message
							? response.data.message
							: optipressAttachment.i18n.unknownError;

					showNotice(errorMsg, 'error');
					hideLoading();
				}
			},
			error: function (xhr, status, error) {
				processing = false;
				showNotice(optipressAttachment.i18n.error + ': ' + error, 'error');
				hideLoading();
			},
		});
	}

	/**
	 * Switch image to different format
	 */
	function switchFormat(attachmentId, format) {
		processing = true;
		showLoading(optipressAttachment.i18n.switching);

		$.ajax({
			url: optipressAttachment.ajaxUrl,
			type: 'POST',
			data: {
				action: 'optipress_switch_format',
				nonce: optipressAttachment.nonce,
				attachment_id: attachmentId,
				format: format,
			},
			success: function (response) {
				processing = false;

				if (response.success) {
					// Show success message
					showNotice(response.data.message, 'success');

					// Update meta box content
					if (response.data.html) {
						$('#optipress-meta-box-content').html(response.data.html);
					}

					// Reload attachment data
					reloadAttachmentData(attachmentId);
				} else {
					var errorMsg =
						response.data && response.data.message
							? response.data.message
							: optipressAttachment.i18n.unknownError;

					showNotice(optipressAttachment.i18n.conversionError + ' ' + errorMsg, 'error');

					hideLoading();
				}
			},
			error: function (xhr, status, error) {
				processing = false;
				showNotice(optipressAttachment.i18n.error + ': ' + error, 'error');
				hideLoading();
			},
		});
	}

	/**
	 * Show loading state
	 */
	function showLoading(message) {
		var $metaBox = $('#optipress-meta-box-content');
		$metaBox.find('.optipress-status-display').hide();
		$metaBox.find('.optipress-loading').show();

		if (message) {
			$metaBox.find('.optipress-loading-text').text(message);
		}
	}

	/**
	 * Hide loading state
	 */
	function hideLoading() {
		var $metaBox = $('#optipress-meta-box-content');
		$metaBox.find('.optipress-loading').hide();
		$metaBox.find('.optipress-status-display').show();
	}

	/**
	 * Show admin notice
	 */
	function showNotice(message, type) {
		// Use WordPress native admin notices
		var noticeClass = 'notice notice-' + type + ' is-dismissible';
		var $notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');

		// Insert after page title
		$('.wrap h1').first().after($notice);

		// Make dismissible (WordPress core handles this if wp-admin scripts are loaded)
		if (typeof wp !== 'undefined' && wp.notices) {
			$notice.find('.notice-dismiss').on('click', function () {
				$notice.fadeTo(100, 0, function () {
					$notice.slideUp(100, function () {
						$notice.remove();
					});
				});
			});
		}

		// Auto-dismiss success notices after 5 seconds
		if (type === 'success') {
			setTimeout(function () {
				$notice.fadeTo(300, 0, function () {
					$notice.slideUp(200, function () {
						$notice.remove();
					});
				});
			}, 5000);
		}

		// Scroll to top to show notice
		$('html, body').animate({ scrollTop: 0 }, 300);
	}

	/**
	 * Reload attachment data (useful if in media modal)
	 */
	function reloadAttachmentData(attachmentId) {
		// If we're in a media modal context, trigger refresh
		if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
			var attachment = wp.media.attachment(attachmentId);
			if (attachment) {
				attachment.fetch();
			}
		}
	}
})(jQuery);
