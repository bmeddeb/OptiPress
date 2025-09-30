(() => {
  // src/js/attachment-edit.js
  (function($) {
    "use strict";
    var processing = false;
    $(document).ready(function() {
      $("#optipress-meta-box-content").on("click", ".optipress-convert-image", function(e) {
        e.preventDefault();
        if (processing)
          return;
        var $button = $(this);
        var attachmentId = $("#optipress-meta-box-content").data("attachment-id");
        var format = $('input[name="optipress_format"]:checked').val();
        if (!format) {
          alert(optipressAttachment.i18n.error + ": No format selected.");
          return;
        }
        convertImage(attachmentId, format);
      });
      $("#optipress-meta-box-content").on("click", ".optipress-revert-image", function(e) {
        e.preventDefault();
        if (processing)
          return;
        if (!confirm(optipressAttachment.i18n.confirmRevert)) {
          return;
        }
        var attachmentId = $("#optipress-meta-box-content").data("attachment-id");
        revertImage(attachmentId);
      });
      $("#optipress-meta-box-content").on("click", ".optipress-switch-format", function(e) {
        e.preventDefault();
        if (processing)
          return;
        var $button = $(this);
        var attachmentId = $("#optipress-meta-box-content").data("attachment-id");
        var format = $button.data("format");
        switchFormat(attachmentId, format);
      });
    });
    function convertImage(attachmentId, format) {
      processing = true;
      showLoading(optipressAttachment.i18n.converting);
      $.ajax({
        url: optipressAttachment.ajaxUrl,
        type: "POST",
        data: {
          action: "optipress_convert_single_image",
          nonce: optipressAttachment.nonce,
          attachment_id: attachmentId,
          format
        },
        success: function(response) {
          processing = false;
          if (response.success) {
            showNotice(response.data.message, "success");
            if (response.data.html) {
              $("#optipress-meta-box-content").html(response.data.html);
            }
            reloadAttachmentData(attachmentId);
          } else {
            var errorMsg = response.data && response.data.message ? response.data.message : optipressAttachment.i18n.unknownError;
            showNotice(optipressAttachment.i18n.conversionError + " " + errorMsg, "error");
            hideLoading();
          }
        },
        error: function(xhr, status, error) {
          processing = false;
          showNotice(optipressAttachment.i18n.error + ": " + error, "error");
          hideLoading();
        }
      });
    }
    function revertImage(attachmentId) {
      processing = true;
      showLoading(optipressAttachment.i18n.reverting);
      $.ajax({
        url: optipressAttachment.ajaxUrl,
        type: "POST",
        data: {
          action: "optipress_revert_single_image",
          nonce: optipressAttachment.nonce,
          attachment_id: attachmentId
        },
        success: function(response) {
          processing = false;
          if (response.success) {
            showNotice(response.data.message, "success");
            if (response.data.html) {
              $("#optipress-meta-box-content").html(response.data.html);
            }
            reloadAttachmentData(attachmentId);
          } else {
            var errorMsg = response.data && response.data.message ? response.data.message : optipressAttachment.i18n.unknownError;
            showNotice(errorMsg, "error");
            hideLoading();
          }
        },
        error: function(xhr, status, error) {
          processing = false;
          showNotice(optipressAttachment.i18n.error + ": " + error, "error");
          hideLoading();
        }
      });
    }
    function switchFormat(attachmentId, format) {
      processing = true;
      showLoading(optipressAttachment.i18n.switching);
      $.ajax({
        url: optipressAttachment.ajaxUrl,
        type: "POST",
        data: {
          action: "optipress_switch_format",
          nonce: optipressAttachment.nonce,
          attachment_id: attachmentId,
          format
        },
        success: function(response) {
          processing = false;
          if (response.success) {
            showNotice(response.data.message, "success");
            if (response.data.html) {
              $("#optipress-meta-box-content").html(response.data.html);
            }
            reloadAttachmentData(attachmentId);
          } else {
            var errorMsg = response.data && response.data.message ? response.data.message : optipressAttachment.i18n.unknownError;
            showNotice(optipressAttachment.i18n.conversionError + " " + errorMsg, "error");
            hideLoading();
          }
        },
        error: function(xhr, status, error) {
          processing = false;
          showNotice(optipressAttachment.i18n.error + ": " + error, "error");
          hideLoading();
        }
      });
    }
    function showLoading(message) {
      var $metaBox = $("#optipress-meta-box-content");
      $metaBox.find(".optipress-status-display").hide();
      $metaBox.find(".optipress-loading").show();
      if (message) {
        $metaBox.find(".optipress-loading-text").text(message);
      }
    }
    function hideLoading() {
      var $metaBox = $("#optipress-meta-box-content");
      $metaBox.find(".optipress-loading").hide();
      $metaBox.find(".optipress-status-display").show();
    }
    function showNotice(message, type) {
      var noticeClass = "notice notice-" + type + " is-dismissible";
      var $notice = $('<div class="' + noticeClass + '"><p>' + message + "</p></div>");
      $(".wrap h1").first().after($notice);
      if (typeof wp !== "undefined" && wp.notices) {
        $notice.find(".notice-dismiss").on("click", function() {
          $notice.fadeTo(100, 0, function() {
            $notice.slideUp(100, function() {
              $notice.remove();
            });
          });
        });
      }
      if (type === "success") {
        setTimeout(function() {
          $notice.fadeTo(300, 0, function() {
            $notice.slideUp(200, function() {
              $notice.remove();
            });
          });
        }, 5e3);
      }
      $("html, body").animate({ scrollTop: 0 }, 300);
    }
    function reloadAttachmentData(attachmentId) {
      if (typeof wp !== "undefined" && wp.media && wp.media.frame) {
        var attachment = wp.media.attachment(attachmentId);
        if (attachment) {
          attachment.fetch();
        }
      }
    }
  })(jQuery);
})();
//# sourceMappingURL=attachment-edit.bundle.js.map
