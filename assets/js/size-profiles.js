/**
 * OptiPress Size Profiles Editor
 *
 * Handles add/delete row functionality for thumbnail size profiles.
 */
jQuery(function ($) {
  const $table = $('#optipress-size-profiles-table');
  const $tbody = $('#optipress-size-profiles-body');
  const $add   = $('#optipress-add-size');

  function nextIndex() {
    let max = -1;
    $tbody.find('tr').each(function () {
      const $name = $(this).find('input[name*="[name]"]');
      const m = $name.attr('name').match(/\[(\d+)\]\[name\]$/);
      if (m) max = Math.max(max, parseInt(m[1], 10));
    });
    return max + 1;
  }

  $add.on('click', function () {
    const i = nextIndex();
    const tpl = (OptiPressSizes && OptiPressSizes.rowTemplate) || '';
    if (!tpl) return;
    const html = tpl.replaceAll('{i}', i);
    $tbody.append(html);
  });

  $tbody.on('click', '.delete-size', function () {
    $(this).closest('tr').remove();
  });
});
