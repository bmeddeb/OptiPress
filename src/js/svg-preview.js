/**
 * SVG Preview with DOMPurify
 *
 * Client-side SVG preview with DOMPurify sanitization.
 * NOTE: This is for preview/convenience ONLY - NOT for security.
 * Server-side sanitization is authoritative and required.
 *
 * @package OptiPress
 */

import DOMPurify from 'dompurify';

(function($) {
	'use strict';

	/**
	 * SVG Preview Handler
	 */
	var OptipressSVGPreview = {

		/**
		 * Initialize
		 */
		init: function() {
			// Only initialize if SVG preview is enabled
			if (typeof optipressSvgPreview === 'undefined' || !optipressSvgPreview.enabled) {
				return;
			}

			this.setupDOMPurify();
			this.bindEvents();
		},

		/**
		 * Configure DOMPurify for SVG sanitization
		 */
		setupDOMPurify: function() {
			// Configure DOMPurify for SVG files
			this.purifyConfig = {
				WHOLE_DOCUMENT: true,
				USE_PROFILES: { svg: true },
				FORBID_TAGS: ['foreignObject', 'script'],
				FORBID_ATTR: [/^on/i, 'style', 'xlink:href'],
				ALLOWED_URI_REGEXP: /^(https?:|#)/i
			};
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Hook into WordPress media uploader
			if (typeof wp !== 'undefined' && wp.media) {
				// Hook into media uploader frame
				wp.media.view.Attachment.Details = wp.media.view.Attachment.Details.extend({
					initialize: function() {
						wp.media.view.Attachment.Details.__super__.initialize.apply(this, arguments);
						self.handleMediaAttachment(this.model);
					}
				});
			}

			// Also handle direct file input (non-media library uploads)
			$(document).on('change', 'input[type="file"][accept*="svg"]', function(e) {
				self.handleFileInput(e.target);
			});
		},

		/**
		 * Handle media attachment
		 */
		handleMediaAttachment: function(model) {
			if (!model || !model.get('subtype') || model.get('subtype') !== 'svg+xml') {
				return;
			}

			var url = model.get('url');
			if (url) {
				this.fetchAndPreviewSVG(url, model);
			}
		},

		/**
		 * Handle file input change
		 */
		handleFileInput: function(input) {
			if (!input.files || !input.files[0]) {
				return;
			}

			var file = input.files[0];
			if (file.type !== 'image/svg+xml') {
				return;
			}

			var self = this;
			var reader = new FileReader();

			reader.onload = function(e) {
				var svgContent = e.target.result;
				self.showPreview(svgContent, file.name);
			};

			reader.readAsText(file);
		},

		/**
		 * Fetch SVG content from URL
		 */
		fetchAndPreviewSVG: function(url, model) {
			var self = this;

			$.ajax({
				url: url,
				type: 'GET',
				dataType: 'text',
				success: function(svgContent) {
					self.showPreview(svgContent, model.get('filename'));
				},
				error: function() {
					console.warn('OptiPress: Failed to fetch SVG for preview');
				}
			});
		},

		/**
		 * Show SVG preview with security warning
		 */
		showPreview: function(svgContent, filename) {
			// Sanitize with DOMPurify
			var cleanSVG = DOMPurify.sanitize(svgContent, this.purifyConfig);

			// Check if anything was removed
			var wasModified = cleanSVG !== svgContent;

			// Create preview modal
			var $modal = this.createPreviewModal(cleanSVG, filename, wasModified);

			// Show modal
			$('body').append($modal);
			$modal.fadeIn(200);
		},

		/**
		 * Create preview modal
		 */
		createPreviewModal: function(cleanSVG, filename, wasModified) {
			var warningClass = wasModified ? 'optipress-svg-warning' : 'optipress-svg-info';
			var warningIcon = wasModified ? '⚠️' : 'ℹ️';
			var warningText = wasModified
				? 'Potentially dangerous content was removed from this preview. The file will be sanitized by the server before storage.'
				: 'This is a client-side preview. The file will be sanitized by the server before storage.';

			var $modal = $('<div>', {
				class: 'optipress-svg-preview-modal',
				html: '<div class="optipress-svg-preview-content">' +
					'<div class="optipress-svg-preview-header">' +
					'<h3>SVG Preview: ' + this.escapeHtml(filename) + '</h3>' +
					'<button class="optipress-svg-close">&times;</button>' +
					'</div>' +
					'<div class="' + warningClass + '">' +
					'<span class="optipress-svg-icon">' + warningIcon + '</span>' +
					'<span>' + warningText + '</span>' +
					'</div>' +
					'<div class="optipress-svg-preview-body">' +
					cleanSVG +
					'</div>' +
					'<div class="optipress-svg-preview-footer">' +
					'<p><strong>Security Note:</strong> This preview uses DOMPurify for display only. ' +
					'Server-side sanitization is the authoritative security layer and will process this file upon upload.</p>' +
					'</div>' +
					'</div>'
			});

			// Close button handler
			$modal.on('click', '.optipress-svg-close, .optipress-svg-preview-modal', function(e) {
				if (e.target === this) {
					$modal.fadeOut(200, function() {
						$modal.remove();
					});
				}
			});

			return $modal;
		},

		/**
		 * Escape HTML for safe display
		 */
		escapeHtml: function(text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		OptipressSVGPreview.init();
	});

})(jQuery);