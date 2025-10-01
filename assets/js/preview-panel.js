/**
 * OptiPress Preview Panel
 *
 * Handles AJAX rebuild preview functionality.
 */
jQuery(function ($) {
  const $btn = $('#optipress-rebuild-preview');
  if (!$btn.length) return;

  const $status = $('#optipress-rebuild-status');

  $btn.on('click', function (e) {
    e.preventDefault();
    if ($btn.prop('disabled')) return;

    const id = $btn.data('attachment');
    $btn.prop('disabled', true).text('Rebuilding…');
    $status.show().text('Working…');

    $.post(OptiPressPreview.ajax, {
      action: 'optipress_rebuild_preview',
      _ajax_nonce: OptiPressPreview.nonce,
      attachment_id: id
    })
    .done(function (res) {
      if (res && res.success) {
        $status.text(res.data.message + (res.data.preview_file ? (' → ' + res.data.preview_file) : ''));
        $btn.text('Rebuild Preview');
        $btn.prop('disabled', false);
      } else {
        $status.text((res && res.data && res.data.message) || 'Failed.');
        $btn.text('Rebuild Preview').prop('disabled', false);
      }
    })
    .fail(function (xhr) {
      const msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error';
      $status.text(msg);
      $btn.text('Rebuild Preview').prop('disabled', false);
    });
  });
});
