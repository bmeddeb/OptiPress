# OptiPress UX Improvements TODO

This document outlines UX enhancements needed for OptiPress, based on user feedback and testing.

---

## 1. Upload Progress Tracking (media-new.php)

**Issue**: When uploading large files on `wp-admin/media-new.php`, the browser appears to hang with no feedback about what's happening (resizing, optimizing, converting).

**Current Implementation**:

- ✅ Upload progress tracking system exists (`admin/js/upload-progress.js`)
- ✅ AJAX polling for conversion status implemented
- ✅ Spinner and status overlays with session savings counter
- ✅ Script is enqueued on correct pages (upload.php, media-new.php, post.php, post-new.php)
- ✅ AJAX handler `ajax_check_conversion_status` exists in `class-image-converter.php`

**Problem**: Script exists but progress indicators are not displaying during upload.

### Investigation Needed

1. **Check if script is loading correctly**:
   - Verify no JavaScript console errors
   - Confirm `optipressUpload` localized data is available
   - Test if `wp.Uploader` hooks are firing

2. **Debug attachment tracking**:
   - Add console logging to `trackAttachment()` function (line 51)
   - Verify MIME type detection is working (line 54)
   - Check if attachments are being added to `processingAttachments` queue

3. **Debug status display**:
   - Confirm status overlays are being created (line 178-223)
   - Check jQuery selectors for attachment elements (line 160-169)
   - Test if `.optipress-status` elements are being appended

4. **Test AJAX polling**:
   - Verify `pollConversionStatus()` is being called
   - Check network tab for `optipress_check_conversion_status` requests
   - Confirm responses are returning expected data structure

### Required Fixes

- [ ] Add browser console debugging to identify failure point
- [ ] Test with both drag-drop and button upload methods
- [ ] Verify status overlay CSS is not hiding elements
- [ ] Check z-index and positioning of spinner/status elements
- [ ] Test with different image sizes and formats (JPEG, PNG)
- [ ] Ensure session savings counter is visible and updating

### Enhancement Ideas

- [ ] Add more detailed status messages:
  - "Reading image..." (initial upload)
  - "Generating thumbnails..." (WordPress processing)
  - "Converting to WebP/AVIF..." (OptiPress conversion)
  - "Complete! Saved X KB" (final status)
- [ ] Show progress bar for long conversions (based on file size estimate)
- [ ] Add cancellation option for in-progress conversions
- [ ] Improve visual design of status overlays (better contrast, animations)

---

## 2. Edit Media Page Enhancements (post.php?post=X&action=edit)

**Issue**: Individual image edit page lacks OptiPress status information and conversion controls.

**Current State**: No OptiPress information or controls visible on attachment edit page.

**Required Features**:

### A. OptiPress Meta Box in Sidebar

Create a new meta box in the attachment edit sidebar (similar to "Attachment Details") showing:

**When Image Is Converted**:

```
┌─────────────────────────────┐
│ OptiPress Optimization      │
├─────────────────────────────┤
│ Status: ✓ Optimized         │
│ Format: WebP                │
│ Original: 1.2 MB            │
│ Optimized: 850 KB           │
│ Saved: 350 KB (29.2%)       │
│                             │
│ [Convert to AVIF]           │
│ [Revert to Original]        │
└─────────────────────────────┘
```

**When Image Is NOT Converted**:

```
┌─────────────────────────────┐
│ OptiPress Optimization      │
├─────────────────────────────┤
│ Status: Not optimized       │
│                             │
│ Format: ◯ WebP  ◯ AVIF     │
│                             │
│ [Convert Image]             │
└─────────────────────────────┘
```

**When Conversion Fails**:

```
┌─────────────────────────────┐
│ OptiPress Optimization      │
├─────────────────────────────┤
│ Status: ⚠ Conversion failed │
│                             │
│ Error: File size too large  │
│ (max 10 MB, this file is    │
│ 15 MB)                      │
│                             │
│ [Retry Conversion]          │
└─────────────────────────────┘
```

### B. Implementation Requirements

**New Files**:

- `includes/class-attachment-meta-box.php` - Meta box class
- `admin/js/attachment-edit.js` - AJAX handlers for convert/revert buttons
- CSS additions to `admin/css/admin-styles.css`

**Metadata to Display** (already stored):

- `_optipress_converted` (bool) - Whether image is converted
- `_optipress_format` (string) - Current format (webp/avif)
- `_optipress_original_size` (int) - Original file size in bytes
- `_optipress_converted_size` (int) - Converted file size in bytes
- `_optipress_bytes_saved` (int) - Bytes saved
- `_optipress_percent_saved` (float) - Percentage saved
- `_optipress_errors` (array) - Conversion errors if any

**AJAX Actions Needed**:

1. `optipress_convert_single_image`:
   - Input: attachment_id, format (webp/avif)
   - Process: Convert image to specified format
   - Response: Updated stats or error message

2. `optipress_revert_single_image`:
   - Input: attachment_id
   - Process: Revert to original, delete converted files
   - Response: Success/error message

3. `optipress_switch_format`:
   - Input: attachment_id, target_format
   - Process: Convert from current format to target format
   - Response: Updated stats

**WordPress Hooks**:

- `add_meta_boxes_attachment` - Register meta box
- `wp_ajax_optipress_convert_single_image`
- `wp_ajax_optipress_revert_single_image`
- `wp_ajax_optipress_switch_format`

### C. User Interactions

**Convert Button**:

- Disabled if already converted
- Shows spinner during processing
- Updates meta box with results
- Shows toast notification on completion
- Handles errors gracefully (file too large, unsupported format, etc.)

**Revert Button**:

- Only enabled if image is converted
- Confirmation dialog: "Revert to original? This will delete the optimized version."
- Shows spinner during processing
- Updates meta box to show "Not optimized" state
- Toast notification on success

**Format Switcher**:

- Radio buttons or toggle switch
- Only active when converting (not for already-converted images)
- For already-converted: "Convert to AVIF" button changes to "Convert to WebP" if currently WebP (and vice versa)
- Seamless format switching without re-uploading

**Smart Behavior**:

- If user is on slow connection, show estimated time
- If conversion fails, show specific error (not just "failed")
- If format not supported, disable button and show reason
- Auto-refresh attachment page data after conversion (no manual reload needed)

### D. Additional Enhancements

1. **Bulk Edit Support**:
   - Add OptiPress options to Media Library bulk actions
   - "Convert selected to WebP/AVIF"
   - "Revert selected to original"

2. **Media Library Grid View**:
   - Add visual indicator badge for optimized images
   - Show format and savings on hover
   - Example: Small "WebP -30%" badge in corner

3. **Comparison Tool** (Future):
   - Side-by-side original vs optimized preview
   - File size comparison
   - Visual quality comparison slider

---

## 3. General UX Improvements

### A. Toast Notifications Position

**Issue**: WordPress toasts on settings page appear at very top of page, easy to miss.

**Solutions**:

1. **Option 1**: Position toasts inside "Batch Processing" box
   - More contextual
   - User's eyes are already focused on that area
   - Requires custom toast implementation

2. **Option 2**: Use WordPress admin notices API
   - Native WordPress behavior
   - Appears below admin bar
   - More consistent with WP patterns
   - Add `is-dismissible` class for close button

3. **Option 3**: Floating toast in bottom-right
   - Non-intrusive
   - Matches modern app patterns
   - Can stack multiple notifications
   - Requires custom CSS/JS

**Recommendation**: Start with Option 2 (WordPress admin notices), add Option 3 for AJAX operations.

### B. Batch Processing Progress Bar

**Status**: ✅ Fixed in recent updates

Issues resolved:

- Progress no longer jumps from 0→100%
- Revert progress displays correctly
- "Infinity min remaining" bug fixed
- Button state properly resets after operations

### C. Settings Page UX

**Minor Improvements**:

- [ ] Add "Test Conversion" button to try converting a sample image
- [ ] Show real-time format support detection (not just on page load)
- [ ] Add "Recommended Settings" button to auto-configure
- [ ] Inline help text for complex options
- [ ] Warning when selecting AVIF without PHP 8.1+ (for GD engine)

---

## 4. Error Handling & User Feedback

### Current Gaps

1. **Silent Failures**: User may not know conversion failed
2. **Vague Errors**: "Conversion failed" without explanation
3. **No Recovery Options**: User can't easily retry after failure

### Improvements

**Detailed Error Messages**:

```
❌ Conversion failed: File size too large
   This image is 15 MB, but the maximum allowed size is 10 MB.
   You can increase the limit with the 'optipress_max_filesize_bytes' filter.

   [View Documentation] [Retry with Smaller Image]
```

**Error Types to Handle**:

- File size exceeds limit
- Image dimensions too large (megapixels)
- Insufficient memory
- Format not supported by server
- File permissions issues
- Corrupted image file
- Timeout during conversion

**Recovery Actions**:

- Retry button with exponential backoff
- Skip and continue with next image
- Adjust quality and retry
- View detailed error log

---

## 5. Implementation Priority

### High Priority (Do First)

1. ✅ Debug and fix upload progress display (media-new.php)
2. ✅ Create attachment edit meta box with conversion stats
3. ✅ Add convert/revert buttons to attachment edit page
4. ✅ Implement format switcher (WebP ↔ AVIF)

### Medium Priority

5. ⬜ Improve toast notification positioning
6. ⬜ Add detailed error messages and recovery options
7. ⬜ Media library grid view badges
8. ⬜ Bulk edit support for conversions

### Low Priority (Nice to Have)

9. ⬜ Settings page enhancements (test button, recommended settings)
10. ⬜ Comparison tool for before/after
11. ⬜ Advanced: Background processing for very large images
12. ⬜ Advanced: Integration with WordPress REST API

---

## 6. Testing Checklist

After implementing changes, test:

- [ ] Upload single small image (< 1 MB) - progress displays correctly
- [ ] Upload single large image (5-10 MB) - progress updates throughout
- [ ] Upload multiple images simultaneously - each shows individual progress
- [ ] Drag-and-drop upload vs button upload - both work
- [ ] Media library grid view - converted images have indicator
- [ ] Attachment edit page - meta box displays correct stats
- [ ] Convert button - successfully converts image, updates stats
- [ ] Revert button - successfully reverts, deletes converted files
- [ ] Format switcher - changes WebP → AVIF and AVIF → WebP
- [ ] Error handling - appropriate messages for various failure scenarios
- [ ] Mobile responsive - all new UI elements work on mobile screens
- [ ] Browser compatibility - Chrome, Firefox, Safari, Edge
- [ ] Network conditions - slow connection, offline handling

---

## Notes

- All metadata fields (`_optipress_*`) are already being stored correctly
- AJAX infrastructure is in place and working
- Main work is UI/UX layer on top of existing functionality
- Focus on user feedback, error handling, and discoverability
- Keep design consistent with WordPress admin UI patterns

---

**Last Updated**: 2025-09-30
**Status**: Ready for implementation
