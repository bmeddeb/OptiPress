/**
 * OptiPress Size Profiles Editor
 *
 * Handles add/delete row functionality and live hints for thumbnail size profiles.
 */
jQuery(function ($) {
  const $tbody = $('#optipress-size-profiles-body');
  const $add   = $('#optipress-add-size');

  function nextIndex() {
    let max = -1;
    $tbody.find('tr.optipress-size-row').each(function () {
      const $name = $(this).find('input[name*="[name]"]');
      const m = $name.attr('name') && $name.attr('name').match(/\[(\d+)\]\[name\]$/);
      if (m) max = Math.max(max, parseInt(m[1], 10));
    });
    return max + 1;
  }

  function fmtLabel(fmt) {
    const map = { inherit: 'Inherit', avif: 'AVIF', webp: 'WebP', jpeg: 'JPEG', png: 'PNG' };
    fmt = (fmt || 'inherit').toLowerCase();
    return map[fmt] || 'Inherit';
  }

  function buildHint(w, h, crop, fmt) {
    const f = fmtLabel(fmt);
    w = parseInt(w || 0, 10) || 0;
    h = parseInt(h || 0, 10) || 0;
    crop = !!crop;
    if (w <= 0 && h <= 0) return `No-op (set width or height). Output: ${f}.`;
    if (w > 0 && h === 0)  return `Resize to ${w}px wide (height auto). Output: ${f}.`;
    if (w === 0 && h > 0)  return `Resize to ${h}px tall (width auto). Output: ${f}.`;
    if (crop)              return `Cover crop to ${w}×${h} (center). Output: ${f}.`;
    return `Fit inside ${w}×${h} (keep aspect). Output: ${f}.`;
  }

  function refreshRowHint($row) {
    const w   = $row.find('.optipress-w').val();
    const h   = $row.find('.optipress-h').val();
    const crop= $row.find('.optipress-crop').is(':checked');
    const fmt = $row.find('.optipress-fmt').val();
    $row.find('.optipress-size-hint').text(buildHint(w, h, crop, fmt));
  }

  function wireRow($row) {
    $row.on('input change', '.optipress-w, .optipress-h, .optipress-crop, .optipress-fmt', function () {
      refreshRowHint($row);
    });
    refreshRowHint($row);
  }

  // Wire existing rows
  $tbody.find('tr.optipress-size-row').each(function () { wireRow($(this)); });

  // Add new row
  $add.on('click', function () {
    const i = nextIndex();
    const tpl = (window.OptiPressSizes && window.OptiPressSizes.rowTemplate) || '';
    if (!tpl) return;
    const html = tpl.replaceAll('{i}', i);
    const $row = $(html);
    $tbody.append($row);
    wireRow($row);
  });

  // Delete row
  $tbody.on('click', '.delete-size', function () {
    $(this).closest('tr').remove();
  });
});
