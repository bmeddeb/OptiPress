(() => {
  // src/js/admin-settings.js
  (function($) {
    "use strict";
    $(document).ready(function() {
      initQualitySlider();
      if ($("#optipress_engine").length) {
        initCompatibilityChecker();
      }
      initKeepOriginalsWarning();
    });
    function initQualitySlider() {
      const $slider = $("#optipress_quality");
      const $display = $("#optipress-quality-value");
      if (!$slider.length || !$display.length) {
        return;
      }
      $slider.on("input", function() {
        $display.text($(this).val());
      });
    }
    function initCompatibilityChecker() {
      const $engine = $("#optipress_engine");
      const $format = $("#optipress_format");
      const $status = $("#optipress-compatibility-status");
      if (!$engine.length || !$format.length || !$status.length) {
        return;
      }
      function checkCompatibility() {
        const engine = $engine.val();
        const format = $format.val();
        $status.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');
        $.ajax({
          url: optipressAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "optipress_check_compatibility",
            nonce: optipressAdmin.nonce,
            engine,
            format
          },
          success: function(response) {
            if (response.success) {
              $status.html(
                '<span class="optipress-status-success">\u2713 ' + response.data.message + "</span>"
              );
            } else {
              $status.html(
                '<span class="optipress-status-error">\u2717 ' + response.data.message + "</span>"
              );
            }
          },
          error: function() {
            $status.html(
              '<span class="optipress-status-error">\u2717 Failed to check compatibility</span>'
            );
          }
        });
      }
      $engine.on("change", checkCompatibility);
      $format.on("change", checkCompatibility);
      checkCompatibility();
    }
    function initKeepOriginalsWarning() {
      const $checkbox = $('input[name="optipress_options[keep_originals]"]');
      if (!$checkbox.length) {
        return;
      }
      $checkbox.on("change", function() {
        if (!$(this).is(":checked")) {
          var message = 'Warning: Disabling "Keep Originals" will permanently delete original image files after conversion.\n\nThis cannot be undone, and you will not be able to revert conversions.\n\nAre you sure you want to disable this option?';
          OptipressNotices.createConfirm(message, {
            confirmLabel: "Disable",
            cancelLabel: "Keep Originals"
          }).then(function(confirmed) {
            if (!confirmed) {
              $('input[name="optipress_options[keep_originals]"]').prop("checked", true);
            }
          });
        }
      });
    }
  })(jQuery);
})();
//# sourceMappingURL=admin-settings.bundle.js.map
