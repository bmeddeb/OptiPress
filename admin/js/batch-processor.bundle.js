(() => {
  // src/js/batch-processor.js
  (function($) {
    "use strict";
    var OptipressBatchProcessor = {
      // Timer for debouncing stats refresh
      statsRefreshTimer: null,
      /**
       * Initialize
       */
      init: function() {
        this.bindEvents();
        this.debouncedLoadStats();
      },
      /**
       * Bind event handlers
       */
      bindEvents: function() {
        $("#optipress-start-batch").on("click", this.startBatchConversion.bind(this));
        $("#optipress-revert-batch").on("click", this.startRevert.bind(this));
        $("#optipress-sanitize-svg-batch").on("click", this.startSvgSanitization.bind(this));
      },
      /**
       * Load batch statistics
       */
      loadStats: function() {
        var self = this;
        $.ajax({
          url: ajaxurl,
          type: "POST",
          data: {
            action: "optipress_get_batch_stats",
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
       * Load stats with debouncing to prevent excessive AJAX calls
       */
      debouncedLoadStats: function() {
        var self = this;
        if (this.statsRefreshTimer) {
          clearTimeout(this.statsRefreshTimer);
        }
        this.statsRefreshTimer = setTimeout(function() {
          self.loadStats();
        }, 750);
      },
      /**
       * Update statistics display
       */
      updateStats: function(stats) {
        $("#optipress-total-images").text(stats.total || 0);
        $("#optipress-converted-images").text(stats.converted || 0);
        $("#optipress-remaining-images").text(stats.remaining || 0);
        $("#optipress-total-svgs").text(stats.svg_total || 0);
        if (stats.remaining > 0) {
          $("#optipress-start-batch").prop("disabled", false);
        } else {
          $("#optipress-start-batch").prop("disabled", true);
        }
        if (stats.converted > 0) {
          $("#optipress-revert-batch").prop("disabled", false);
        } else {
          $("#optipress-revert-batch").prop("disabled", true);
        }
        if (stats.svg_total > 0) {
          $("#optipress-sanitize-svg-batch").prop("disabled", false);
        } else {
          $("#optipress-sanitize-svg-batch").prop("disabled", true);
        }
      },
      /**
       * Start batch conversion process
       */
      startBatchConversion: function(e) {
        e.preventDefault();
        var proceedWithBatch = function() {
          var remaining = parseInt($("#optipress-remaining-images").text());
          var total = remaining;
          this.processBatch({
            action: "optipress_process_batch",
            resultKey: "processed",
            total,
            processed: 0,
            offset: 0,
            progressBar: "#optipress-batch-progress",
            statusText: "#optipress-batch-status",
            resultArea: "#optipress-batch-result",
            button: "#optipress-start-batch",
            successCallback: this.onBatchComplete.bind(this),
            progressText: optipressAdmin.i18n.processing,
            startTime: Date.now(),
            batchCount: 0
          });
        }.bind(this);
        OptipressNotices.createConfirm(optipressAdmin.i18n.confirmBatch).then(
          function(confirmed) {
            if (confirmed) {
              proceedWithBatch();
            }
          }
        );
      },
      /**
       * Start revert process
       */
      startRevert: function(e) {
        e.preventDefault();
        var proceedWithRevert = function() {
          var converted = parseInt($("#optipress-converted-images").text());
          var total = converted;
          this.processBatch({
            action: "optipress_revert_images",
            resultKey: "reverted",
            total,
            processed: 0,
            offset: 0,
            progressBar: "#optipress-revert-progress",
            statusText: "#optipress-revert-status",
            resultArea: "#optipress-revert-result",
            button: "#optipress-revert-batch",
            successCallback: this.onRevertComplete.bind(this),
            progressText: optipressAdmin.i18n.reverting,
            startTime: Date.now(),
            batchCount: 0
          });
        }.bind(this);
        OptipressNotices.createConfirm(optipressAdmin.i18n.confirmRevert).then(
          function(confirmed) {
            if (confirmed) {
              proceedWithRevert();
            }
          }
        );
      },
      /**
       * Start SVG sanitization process
       */
      startSvgSanitization: function(e) {
        e.preventDefault();
        var proceedWithSvg = function() {
          var total = parseInt($("#optipress-total-svgs").text());
          this.processBatch({
            action: "optipress_sanitize_svg_batch",
            resultKey: "sanitized",
            total,
            processed: 0,
            offset: 0,
            progressBar: "#optipress-svg-batch-progress",
            statusText: "#optipress-svg-batch-status",
            resultArea: "#optipress-svg-batch-result",
            button: "#optipress-sanitize-svg-batch",
            successCallback: this.onSvgBatchComplete.bind(this),
            progressText: optipressAdmin.i18n.sanitizing,
            startTime: Date.now(),
            batchCount: 0
          });
        }.bind(this);
        OptipressNotices.createConfirm(optipressAdmin.i18n.confirmSvgBatch).then(
          function(confirmed) {
            if (confirmed) {
              proceedWithSvg();
            }
          }
        );
      },
      /**
       * Process a batch recursively
       */
      processBatch: function(options) {
        var self = this;
        var $progressBar = $(options.progressBar);
        var $statusText = $(options.statusText);
        var $resultArea = $(options.resultArea);
        var $button = $(options.button);
        $button.prop("disabled", true);
        if (options.processed === 0) {
          $resultArea.hide();
          $progressBar.show().find(".optipress-progress-fill").css("width", "0%");
          $statusText.show().removeClass("optipress-error optipress-success").text(options.progressText + " 0 / " + options.total + " (0%)");
        }
        options.batchCount++;
        this.processChunk(options, function(error, result) {
          if (error) {
            $statusText.text(optipressAdmin.i18n.error + ": " + error).addClass("optipress-error");
            $button.prop("disabled", false);
            return;
          }
          var newlyProcessed = result[options.resultKey] || 0;
          options.processed += newlyProcessed;
          var percentage = Math.min(
            100,
            Math.round(options.processed / options.total * 100)
          );
          var elapsed = (Date.now() - options.startTime) / 1e3;
          var timeText = "";
          if (options.processed > 0) {
            var avgTimePerImage = elapsed / options.processed;
            var remaining = options.total - options.processed;
            var estimatedTimeLeft = Math.ceil(avgTimePerImage * remaining);
            if (estimatedTimeLeft > 60) {
              timeText = " (~" + Math.ceil(estimatedTimeLeft / 60) + " min remaining)";
            } else if (estimatedTimeLeft > 0) {
              timeText = " (~" + estimatedTimeLeft + " sec remaining)";
            }
          }
          $progressBar.find(".optipress-progress-fill").css("width", percentage + "%");
          var statusMessage = options.progressText + " " + options.processed + " / " + options.total + " (" + percentage + "%)" + timeText;
          $statusText.text(statusMessage);
          if (options.processed >= options.total || result.batch_size === 0) {
            var totalTime = Math.ceil(elapsed);
            var timeStr = totalTime > 60 ? Math.ceil(totalTime / 60) + " minutes" : totalTime + " seconds";
            $statusText.text(
              optipressAdmin.i18n.complete + " " + options.processed + " / " + options.total + " (took " + timeStr + ")"
            ).removeClass("optipress-error").addClass("optipress-success");
            $button.prop("disabled", false);
            if (options.successCallback) {
              options.successCallback(options.processed, $resultArea);
            }
            setTimeout(function() {
              $statusText.fadeOut(200, function() {
                $(this).text("").hide().removeClass("optipress-success");
              });
              $progressBar.find(".optipress-progress-fill").css("width", "0%");
              $progressBar.fadeOut(200);
            }, 4e3);
            setTimeout(function() {
              self.debouncedLoadStats();
            }, 500);
          } else {
            options.offset += result.batch_size;
            setTimeout(function() {
              self.processBatch(options);
            }, 500);
          }
        });
      },
      /**
       * Process a single chunk
       */
      processChunk: function(options, callback) {
        $.ajax({
          url: ajaxurl,
          type: "POST",
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
       * Show local success message
       */
      showLocalSuccess: function(message, $container) {
        $container.html(
          '<div class="optipress-local-notice optipress-local-notice-success"><span class="dashicons dashicons-yes-alt"></span><span>' + message + "</span></div>"
        ).show();
        setTimeout(function() {
          var $notice = $container.find(".optipress-local-notice");
          $notice.fadeOut(200, function() {
            $(this).remove();
          });
        }, 4e3);
      },
      /**
       * Batch conversion complete callback
       */
      onBatchComplete: function(processed, $resultArea) {
        var message = optipressAdmin.i18n.batchComplete.replace("%d", processed);
        this.showLocalSuccess(message, $resultArea);
        if (typeof wp !== "undefined" && wp.data && wp.data.dispatch) {
          try {
            wp.data.dispatch("core/notices").createNotice("success", message, { isDismissible: true });
          } catch (e) {
          }
        }
      },
      /**
       * Revert complete callback
       */
      onRevertComplete: function(processed, $resultArea) {
        var message = optipressAdmin.i18n.revertComplete.replace("%d", processed);
        this.showLocalSuccess(message, $resultArea);
        if (typeof wp !== "undefined" && wp.data && wp.data.dispatch) {
          try {
            wp.data.dispatch("core/notices").createNotice("success", message, { isDismissible: true });
          } catch (e) {
          }
        }
      },
      /**
       * SVG batch complete callback
       */
      onSvgBatchComplete: function(processed, $resultArea) {
        var message = optipressAdmin.i18n.svgBatchComplete.replace("%d", processed);
        this.showLocalSuccess(message, $resultArea);
        if (typeof wp !== "undefined" && wp.data && wp.data.dispatch) {
          try {
            wp.data.dispatch("core/notices").createNotice("success", message, { isDismissible: true });
          } catch (e) {
          }
        }
      }
    };
    $(document).ready(function() {
      if ($(".optipress-batch-section").length > 0) {
        OptipressBatchProcessor.init();
      }
    });
  })(jQuery);
})();
//# sourceMappingURL=batch-processor.bundle.js.map
