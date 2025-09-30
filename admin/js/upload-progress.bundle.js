(() => {
  // src/js/upload-progress.js
  (function($) {
    "use strict";
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
      if (!name)
        return "";
      var m = name.toLowerCase().match(/\.([a-z0-9]+)$/);
      if (!m)
        return "";
      switch (m[1]) {
        case "jpg":
        case "jpeg":
          return "image/jpeg";
        case "png":
          return "image/png";
        default:
          return "";
      }
    }
    function getAttachmentMime(att) {
      if (!att)
        return "";
      if (att.mime)
        return att.mime;
      if (att.type && att.subtype)
        return (att.type + "/" + att.subtype).toLowerCase();
      return deriveMimeFromFilename(att.filename || att.name || "");
    }
    function isConvertibleMime(mime) {
      if (!mime)
        return false;
      mime = mime.toLowerCase();
      return mime === "image/jpeg" || mime === "image/png";
    }
    $(document).ready(function() {
      if (typeof wp === "undefined" || typeof wp.Uploader === "undefined") {
        return;
      }
      addSavingsCounter();
      ensureUploadPanel();
      var originalSuccess = wp.Uploader.prototype.success;
      wp.Uploader.prototype.success = function(attachment) {
        originalSuccess.apply(this, arguments);
        if (DEBUG)
          log("OptiPress uploader success:", attachment);
        if (attachment && attachment.id) {
          trackAttachment(attachment.id, attachment);
        }
      };
      if (wp.Uploader.queue) {
        wp.Uploader.queue.on("add", function(attachment) {
          if (attachment && attachment.id) {
            trackAttachment(attachment.id, attachment);
          }
        });
      }
    });
    function trackAttachment(attachmentId, attachment) {
      var mimeType = getAttachmentMime(attachment);
      if (DEBUG)
        log("OptiPress trackAttachment:", attachmentId, mimeType, attachment);
      if (!isConvertibleMime(mimeType))
        return;
      processingAttachments[attachmentId] = {
        id: attachmentId,
        filename: attachment.filename || attachment.name || "image",
        attempts: 0,
        maxAttempts: 30,
        // 30 seconds max polling
        fastPollUntil: Date.now() + 5e3
        // Fast poll for first 5 seconds
      };
      showConversionStatus(attachmentId, "processing");
      pollConversionStatus(attachmentId);
      if (!pollInterval) {
        startPolling();
      }
    }
    function startPolling() {
      var pollCount = 0;
      pollInterval = setInterval(function() {
        pollCount++;
        checkConversionStatuses();
        if (pollCount === 10 && pollInterval) {
          clearInterval(pollInterval);
          pollInterval = setInterval(checkConversionStatuses, 1e3);
        }
      }, 200);
    }
    function stopPolling() {
      if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
      }
    }
    function checkConversionStatuses() {
      var attachmentIds = Object.keys(processingAttachments);
      if (attachmentIds.length === 0) {
        stopPolling();
        return;
      }
      attachmentIds.forEach(function(attachmentId) {
        var attachment = processingAttachments[attachmentId];
        attachment.attempts++;
        if (attachment.attempts >= attachment.maxAttempts) {
          showConversionStatus(attachmentId, "timeout");
          delete processingAttachments[attachmentId];
          return;
        }
        pollConversionStatus(attachmentId);
      });
    }
    function pollConversionStatus(attachmentId) {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "optipress_check_conversion_status",
          nonce: optipressUpload.nonce,
          attachment_id: attachmentId
        },
        success: function(response) {
          if (response.success && response.data) {
            if (response.data.status === "completed") {
              showConversionStatus(attachmentId, "completed", response.data);
              delete processingAttachments[attachmentId];
              updateSessionStats(response.data);
            } else if (response.data.status === "processing") {
              showConversionStatus(attachmentId, "processing", response.data);
            } else if (response.data.status === "not_applicable") {
              delete processingAttachments[attachmentId];
            }
          }
        },
        error: function() {
        }
      });
    }
    function showConversionStatus(attachmentId, status, data) {
      ensureUploadPanel();
      updateUploadPanelItem(attachmentId, status, data);
    }
    function updateSessionStats(data) {
      if (data.bytes_saved && data.bytes_saved > 0) {
        sessionSavings += data.bytes_saved;
        sessionCount++;
        updateSavingsCounter();
      }
    }
    function addSavingsCounter() {
      if ($("#optipress-savings-counter").length) {
        return;
      }
      var $counter = $(
        '<div id="optipress-savings-counter" class="optipress-counter-hidden"><div class="optipress-counter-label">OptiPress Session</div><div class="optipress-counter-value"><span id="optipress-session-savings">0 KB</span> saved</div><div class="optipress-counter-count"><span id="optipress-session-count">0</span> images optimized</div></div>'
      );
      $("body").append($counter);
    }
    function ensureUploadPanel() {
      if ($("#optipress-upload-panel").length)
        return;
      var $panel = $(
        '<div id="optipress-upload-panel" class="hidden"><div class="optipress-upload-header"><span>OptiPress Uploads</span><button type="button" class="optipress-upload-remove" title="Hide" aria-label="Hide">\xD7</button></div><ul class="optipress-upload-list" id="optipress-upload-list"></ul></div>'
      );
      $panel.on("click", ".optipress-upload-remove", function() {
        $("#optipress-upload-panel").toggleClass("hidden");
      });
      $("body").append($panel);
    }
    function updateUploadPanelVisibility() {
      var $list = $("#optipress-upload-list");
      var hasItems = $list.children().length > 0;
      $("#optipress-upload-panel").toggleClass("hidden", !hasItems);
    }
    function updateUploadPanelItem(attachmentId, status, data) {
      var $list = $("#optipress-upload-list");
      var id = String(attachmentId);
      var $item = $list.find('[data-id="' + id + '"]');
      var filename = data && data.filename || processingAttachments[id] && processingAttachments[id].filename || "#" + id;
      var message;
      switch (status) {
        case "processing":
          message = data && data.message || "Converting image...";
          break;
        case "completed":
          message = data && data.message || "Conversion complete";
          break;
        case "timeout":
          message = "Conversion taking longer than expected...";
          break;
      }
      if (!$item.length) {
        $item = $(
          '<li class="optipress-upload-item processing" data-id="' + id + '"><span class="status-icon"><span class="spinner is-active"></span></span><div class="optipress-upload-filename" title="' + filename + '">' + filename + '</div><div class="optipress-upload-message">' + message + "</div></li>"
        );
        $list.prepend($item);
      } else {
        $item.find(".optipress-upload-message").text(message);
      }
      $item.removeClass("processing completed timeout");
      switch (status) {
        case "processing":
          $item.addClass("processing");
          $item.find(".status-icon").html('<span class="spinner is-active"></span>');
          break;
        case "completed":
          $item.addClass("completed");
          $item.find(".status-icon").html('<span class="dashicons dashicons-yes-alt"></span>');
          setTimeout(function() {
            $item.fadeOut(300, function() {
              $(this).remove();
              updateUploadPanelVisibility();
            });
          }, 8e3);
          break;
        case "timeout":
          $item.addClass("timeout");
          $item.find(".status-icon").html('<span class="dashicons dashicons-warning"></span>');
          setTimeout(function() {
            $item.fadeOut(300, function() {
              $(this).remove();
              updateUploadPanelVisibility();
            });
          }, 5e3);
          break;
      }
      updateUploadPanelVisibility();
    }
    function updateSavingsCounter() {
      var $counter = $("#optipress-savings-counter");
      if (!$counter.length) {
        return;
      }
      var formatted;
      var savingsKB = sessionSavings / 1024;
      if (savingsKB >= 1024) {
        formatted = (savingsKB / 1024).toFixed(1) + " MB";
      } else {
        formatted = savingsKB.toFixed(1) + " KB";
      }
      $("#optipress-session-savings").text(formatted);
      $("#optipress-session-count").text(sessionCount);
      if (sessionCount > 0) {
        $counter.removeClass("optipress-counter-hidden");
      }
    }
  })(jQuery);
})();
//# sourceMappingURL=upload-progress.bundle.js.map
