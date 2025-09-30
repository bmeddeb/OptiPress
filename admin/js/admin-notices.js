(function ($) {
	'use strict';

	// Small helper to create WordPress admin toasts and confirm dialogs using core/notices
	window.OptipressNotices = {
		createNotice: function (type, message, options) {
			options = options || {};
			if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
				try {
					var _noticesDispatcher = wp.data.dispatch('core/notices');
					if (
						_noticesDispatcher &&
						typeof _noticesDispatcher.createNotice === 'function'
					) {
						_noticesDispatcher.createNotice(type, message, options);
						return;
					}
				} catch (e) {
					// Fall through to alert fallback
				}
			}

			// DOM-based fallback: create a temporary admin notice inside the settings page
			try {
				var $container = jQuery('#optipress-notice-area');
				if (!$container.length) {
					$container = jQuery('<div id="optipress-notice-area"></div>');
					// Prepend to the main wrap if available
					if (jQuery('.wrap').length) {
						jQuery('.wrap').first().prepend($container);
					} else {
						jQuery('body').prepend($container);
					}
				}

				var noticeClass = 'notice';
				switch (type) {
					case 'success':
						noticeClass += ' notice-success';
						break;
					case 'warning':
						noticeClass += ' notice-warning';
						break;
					case 'error':
						noticeClass += ' notice-error';
						break;
					default:
						noticeClass += ' notice-info';
				}

				var $notice = jQuery(
					'<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>'
				);
				$container.append($notice);
				// Make the notice dismissible
				$notice.on('click', '.notice-dismiss', function () {
					$notice.remove();
				});
				return;
			} catch (e) {
				// Last-resort fallback
				window.console && window.console.log && window.console.log(message);
			}
		},

		// createConfirm returns a Promise that resolves to true when confirmed, false otherwise
		createConfirm: function (message, opts) {
			opts = opts || {};

			// Try to use core/notices with buttons if available
			if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
				try {
					var _noticesDispatcher = wp.data.dispatch('core/notices');
					if (
						_noticesDispatcher &&
						typeof _noticesDispatcher.createNotice === 'function'
					) {
						return new Promise(function (resolve) {
							var noticeOptions = {
								isDismissible: true,
								buttons: [
									{
										label: opts.confirmLabel || 'Confirm',
										onClick: function () {
											resolve(true);
										},
									},
									{
										label: opts.cancelLabel || 'Cancel',
										onClick: function () {
											resolve(false);
										},
									},
								],
							};

							_noticesDispatcher.createNotice('warning', message, noticeOptions);
						});
					}
				} catch (e) {
					// Fall through to DOM modal fallback
				}
			}

			// DOM modal fallback (no native confirm)
			return new Promise(function (resolve) {
				var $modal = jQuery(
					'<div class="optipress-modal-overlay" role="dialog" aria-modal="true"><div class="optipress-modal"><div class="optipress-modal-body"><p>' +
						message +
						'</p></div><div class="optipress-modal-actions"><button class="optipress-modal-confirm button button-primary">' +
						(opts.confirmLabel || 'Confirm') +
						'</button> <button class="optipress-modal-cancel button">' +
						(opts.cancelLabel || 'Cancel') +
						'</button></div></div></div>'
				);

				// Basic styling to make it visible (kept small and unobtrusive)
				var style =
					'\n.optipress-modal-overlay{position:fixed;left:0;top:0;right:0;bottom:0;z-index:99999;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center}\n.optipress-modal{background:#fff;border-radius:4px;padding:20px;max-width:520px;box-shadow:0 2px 10px rgba(0,0,0,0.2)}\n.optipress-modal .optipress-modal-actions{margin-top:16px;text-align:right}';
				if (!document.getElementById('optipress-modal-style')) {
					var s = document.createElement('style');
					s.id = 'optipress-modal-style';
					s.appendChild(document.createTextNode(style));
					document.head.appendChild(s);
				}

				jQuery('body').append($modal);

				$modal.find('.optipress-modal-confirm').on('click', function () {
					$modal.remove();
					resolve(true);
				});

				$modal.find('.optipress-modal-cancel').on('click', function () {
					$modal.remove();
					resolve(false);
				});

				// Close on ESC
				jQuery(document).on('keydown.optipressModal', function (e) {
					if (e.key === 'Escape') {
						jQuery(document).off('keydown.optipressModal');
						$modal.remove();
						resolve(false);
					}
				});
			});
		},
	};
})(jQuery);
