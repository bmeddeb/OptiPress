/**
 * OptiPress Preview Panel
 *
 * Handles AJAX rebuild preview and regenerate thumbnails functionality.
 */
jQuery(function ($) {
  const $previewBtn = $('#optipress-rebuild-preview');
  const $thumbsBtn = $('#optipress-regenerate-thumbnails');
  const $status = $('#optipress-rebuild-status');

  // Rebuild Preview button
  if ($previewBtn.length) {
    $previewBtn.on('click', function (e) {
      e.preventDefault();
      if ($previewBtn.prop('disabled')) return;

      const id = $previewBtn.data('attachment');
      const $container = $('#optipress-meta-box-content');
      $previewBtn.prop('disabled', true).text('Rebuilding…');
      $status.show().text('Working…');

      $.post(OptiPressPreview.ajax, {
        action: 'optipress_rebuild_preview',
        _ajax_nonce: OptiPressPreview.previewNonce,
        attachment_id: id
      })
      .done(function (res) {
        if (res && res.success) {
          $status.text(res.data.message);
          // Refresh the entire meta box content
          if (res.data.html) {
            $container.html(res.data.html);
          }
          setTimeout(function() {
            $status.fadeOut();
          }, 3000);
        } else {
          $status.text((res && res.data && res.data.message) || 'Failed.');
          $previewBtn.text('Rebuild Preview').prop('disabled', false);
        }
      })
      .fail(function (xhr) {
        const msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error';
        $status.text(msg);
        $previewBtn.text('Rebuild Preview').prop('disabled', false);
      });
    });
  }

  // Rebuild Images button
  if ($thumbsBtn.length) {
    $thumbsBtn.on('click', function (e) {
      e.preventDefault();
      if ($thumbsBtn.prop('disabled')) return;

      const id = $thumbsBtn.data('attachment');
      const $container = $('#optipress-meta-box-content');
      $thumbsBtn.prop('disabled', true).text('Rebuilding…');
      $status.show().text('Working…');

      $.post(OptiPressPreview.ajax, {
        action: 'optipress_regenerate_thumbnails',
        _ajax_nonce: OptiPressPreview.thumbsNonce,
        attachment_id: id
      })
      .done(function (res) {
        if (res && res.success) {
          $status.text(res.data.message);
          // Refresh the entire meta box content
          if (res.data.html) {
            $container.html(res.data.html);
          }
          setTimeout(function() {
            $status.fadeOut();
          }, 3000);
        } else {
          $status.text((res && res.data && res.data.message) || 'Failed.');
          $thumbsBtn.text('Rebuild Images').prop('disabled', false);
        }
      })
      .fail(function (xhr) {
        const msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error';
        $status.text(msg);
        $thumbsBtn.text('Rebuild Images').prop('disabled', false);
      });
    });
  }

  // Row action links - Rebuild Preview
  $(document).on('click', '.optipress-rebuild-link', function (e) {
    e.preventDefault();
    const $link = $(this);
    const id = $link.data('id');
    const nonce = $link.data('nonce');

    if (!confirm('Rebuild preview from original file? This will regenerate the preview and all thumbnails.')) {
      return;
    }

    $link.text('Rebuilding...');

    $.post(OptiPressPreview.ajax, {
      action: 'optipress_rebuild_preview',
      _ajax_nonce: nonce,
      attachment_id: id
    })
    .done(function (res) {
      if (res && res.success) {
        $link.text('Rebuild Preview');
        alert(res.data.message);
        location.reload(); // Reload to show updated image
      } else {
        $link.text('Rebuild Preview');
        alert((res && res.data && res.data.message) || 'Failed.');
      }
    })
    .fail(function (xhr) {
      const msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error';
      $link.text('Rebuild Preview');
      alert(msg);
    });
  });

  // Row action links - Rebuild Images
  $(document).on('click', '.optipress-regenerate-link', function (e) {
    e.preventDefault();
    const $link = $(this);
    const id = $link.data('id');
    const nonce = $link.data('nonce');

    if (!confirm('Rebuild all image sizes for this image?')) {
      return;
    }

    $link.text('Rebuilding...');

    $.post(OptiPressPreview.ajax, {
      action: 'optipress_regenerate_thumbnails',
      _ajax_nonce: nonce,
      attachment_id: id
    })
    .done(function (res) {
      if (res && res.success) {
        $link.text('Rebuild Images');
        alert(res.data.message);
        location.reload(); // Reload to show updated images
      } else {
        $link.text('Rebuild Images');
        alert((res && res.data && res.data.message) || 'Failed.');
      }
    })
    .fail(function (xhr) {
      const msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error';
      $link.text('Rebuild Images');
      alert(msg);
    });
  });
});
