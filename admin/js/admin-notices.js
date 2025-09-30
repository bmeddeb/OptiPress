(function($){
	'use strict';

	// Small helper to create WordPress admin toasts and confirm dialogs using core/notices
	window.OptipressNotices = {
		createNotice: function(type, message, options) {
			options = options || {};
			if ( typeof wp !== 'undefined' && wp.data && wp.data.dispatch ) {
				try {
					wp.data.dispatch('core/notices').createNotice(type, message, options);
					return;
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
					if ( jQuery('.wrap').length ) {
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

				var $notice = jQuery('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
				$container.append($notice);
				// Make the notice dismissible
				$notice.on('click', '.notice-dismiss', function() { $notice.remove(); });
				return;
			} catch (e) {
				// Last-resort fallback
				window.console && window.console.log && window.console.log(message);
			}
		},

		// createConfirm returns a Promise that resolves to true when confirmed, false otherwise
		createConfirm: function(message, opts) {
			opts = opts || {};

			// If notices store is available we can present a notice with action buttons
			if ( typeof wp !== 'undefined' && wp.data && wp.data.dispatch ) {
				return new Promise(function(resolve){
					var noticeOptions = {
						isDismissible: true,
						// buttons is supported by createNotice in WP
						buttons: [
							{
								label: opts.confirmLabel || 'Confirm',
								onClick: function() {
									resolve(true);
									// remove all notices with this message to clean up
									wp.data.dispatch('core/notices').removeNoticeById && wp.data.dispatch('core/notices').removeNoticeById();
								}
							},
							{
								label: opts.cancelLabel || 'Cancel',
								onClick: function() {
									resolve(false);
								}
							}
						]
					};

					try {
						wp.data.dispatch('core/notices').createNotice('warning', message, noticeOptions);
					} catch (e) {
						// Fallback to window.confirm
						resolve( window.confirm(message) );
					}
				});
			}

			// Fallback to window.confirm
			return Promise.resolve( window.confirm(message) );
		}
	};

})(jQuery);
