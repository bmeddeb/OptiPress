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
      $previewBtn.prop('disabled', true).text('Rebuilding…');
      $status.show().text('Working…');

      $.post(OptiPressPreview.ajax, {
        action: 'optipress_rebuild_preview',
        _ajax_nonce: OptiPressPreview.previewNonce,
        attachment_id: id
      })
      .done(function (res) {
        if (res && res.success) {
          $status.text(res.data.message + (res.data.preview_file ? (' → ' + res.data.preview_file) : ''));
          $previewBtn.text('Rebuild Preview').prop('disabled', false);
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

  // Regenerate Thumbnails button
  if ($thumbsBtn.length) {
    $thumbsBtn.on('click', function (e) {
      e.preventDefault();
      if ($thumbsBtn.prop('disabled')) return;

      const id = $thumbsBtn.data('attachment');
      $thumbsBtn.prop('disabled', true).text('Regenerating…');
      $status.show().text('Working…');

      $.post(OptiPressPreview.ajax, {
        action: 'optipress_regenerate_thumbnails',
        _ajax_nonce: OptiPressPreview.thumbsNonce,
        attachment_id: id
      })
      .done(function (res) {
        if (res && res.success) {
          $status.text(res.data.message);
          $thumbsBtn.text('Regenerate Thumbnails').prop('disabled', false);
          // Optionally reload the page to show new thumbnails
          if (res.data.count > 0) {
            setTimeout(function() { location.reload(); }, 1500);
          }
        } else {
          $status.text((res && res.data && res.data.message) || 'Failed.');
          $thumbsBtn.text('Regenerate Thumbnails').prop('disabled', false);
        }
      })
      .fail(function (xhr) {
        const msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error';
        $status.text(msg);
        $thumbsBtn.text('Regenerate Thumbnails').prop('disabled', false);
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

  // Row action links - Regenerate Thumbnails
  $(document).on('click', '.optipress-regenerate-link', function (e) {
    e.preventDefault();
    const $link = $(this);
    const id = $link.data('id');
    const nonce = $link.data('nonce');

    if (!confirm('Regenerate all thumbnails for this image?')) {
      return;
    }

    $link.text('Regenerating...');

    $.post(OptiPressPreview.ajax, {
      action: 'optipress_regenerate_thumbnails',
      _ajax_nonce: nonce,
      attachment_id: id
    })
    .done(function (res) {
      if (res && res.success) {
        $link.text('Regenerate Thumbnails');
        alert(res.data.message);
        location.reload(); // Reload to show updated thumbnails
      } else {
        $link.text('Regenerate Thumbnails');
        alert((res && res.data && res.data.message) || 'Failed.');
      }
    })
    .fail(function (xhr) {
      const msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error';
      $link.text('Regenerate Thumbnails');
      alert(msg);
    });
  });
});
