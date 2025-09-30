# Screenshot Requirements for WordPress.org

This document outlines the screenshots needed for WordPress.org plugin submission. Screenshots should be clear, professional, and demonstrate the plugin's key features.

## WordPress.org Screenshot Requirements

- **Format**: PNG or JPG
- **Recommended Size**: 1280x720 or larger (16:9 aspect ratio)
- **File Names**: `screenshot-1.png`, `screenshot-2.png`, etc.
- **Location**: Root plugin directory (alongside readme.txt)
- **Descriptions**: Include in readme.txt under `== Screenshots ==` section

## Required Screenshots

### Screenshot 1: Settings - Image Optimization Tab
**Filename**: `screenshot-1.png`

**What to show**:
- Full Settings > OptiPress page with Image Optimization tab active
- Engine selector dropdown showing options (Auto-detect, GD, Imagick)
- Format selector showing WebP and AVIF options
- Quality slider set to 85
- Auto-convert on Upload toggle (enabled)
- Keep Original Images toggle (enabled) with warning message
- Enable Content Filter toggle
- Use Picture Element toggle
- Batch processing section showing statistics (e.g., "3,450 / 10,000 images optimized")
- Start Bulk Optimization button
- Revert All to Originals button

**Tips**:
- Use a clean WordPress installation (Twenty Twenty-Four theme recommended)
- Ensure all UI elements are visible
- Show realistic statistics (not 0/0)

---

### Screenshot 2: Settings - SVG Support Tab
**Filename**: `screenshot-2.png`

**What to show**:
- Settings > OptiPress page with SVG Support tab active
- Enable SVG Uploads toggle
- Security note message clearly visible
- Enable SVG Preview toggle
- Batch sanitizer section showing "Found 50 SVGs in media library"
- Sanitize Existing SVGs button
- Security logging section (if visible)

**Tips**:
- Include the security warning message to highlight security focus
- Show actual SVG count if possible

---

### Screenshot 3: Settings - System Status Tab
**Filename**: `screenshot-3.png`

**What to show**:
- Settings > OptiPress page with System Status tab active
- PHP version information
- GD library status (Available/Not Available)
  - GD WebP support: Yes
  - GD AVIF support: Yes/No (depending on PHP version)
- Imagick extension status
  - Imagick WebP support: Yes/No
  - Imagick AVIF support: Yes/No
- Current plugin settings summary
- Server capability warnings (if any)

**Tips**:
- If possible, show a system with both GD and Imagick available
- Include version numbers for PHP (e.g., PHP 8.1.27)

---

### Screenshot 4: Batch Processing in Action
**Filename**: `screenshot-4.png`

**What to show**:
- Image Optimization tab with batch processing active
- Progress bar showing completion (e.g., 40%)
- Real-time status message (e.g., "Processing... 4,000 / 10,000")
- Stats updating in real-time
- Processing messages in progress

**Tips**:
- Capture during actual batch processing
- Show meaningful progress (not 0% or 100%)
- Include browser console if showing AJAX requests (optional, for developer focus)

---

### Screenshot 5: Media Library - Attachment Meta Box
**Filename**: `screenshot-5.png`

**What to show**:
- Single attachment edit screen in WordPress admin
- OptiPress Image Optimization meta box clearly visible
- Conversion status showing:
  - Status: Converted
  - Format: WebP (or AVIF)
  - Engine Used: Imagick (or GD)
  - Original Size: e.g., 2.5 MB
  - Optimized Size: e.g., 450 KB
  - Savings: 82%
- Re-convert Image button

**Tips**:
- Use a real image with significant file size savings
- Show all meta box details clearly

---

### Screenshot 6: Front-End - Optimized Images in Content
**Filename**: `screenshot-6.png`

**What to show**:
- Browser Developer Tools (F12) open on a page with images
- Network tab showing WebP/AVIF images being loaded
- OR: Elements/Inspector tab showing `<img>` tags with .webp or .avif URLs
- OR: Picture element implementation with multiple sources

**Alternative approach**:
- Side-by-side comparison of page source showing:
  - Left: Original HTML with .jpg URLs
  - Right: Converted HTML with .webp URLs

**Tips**:
- Use a clean, professional website design
- Highlight the optimized file extensions
- Show actual file sizes in network tab if possible

---

## Additional Screenshots (Optional)

### Screenshot 7: SVG Upload Preview Modal
**Filename**: `screenshot-7.png` (optional)

**What to show**:
- Media uploader with SVG file selected
- DOMPurify preview modal showing:
  - Original SVG preview
  - Sanitized SVG preview
  - Warning message about client-side preview
  - Continue Upload button

---

### Screenshot 8: Security Log
**Filename**: `screenshot-8.png` (optional)

**What to show**:
- SVG Support tab with security log section expanded
- Table showing sanitization events:
  - Timestamp
  - Event type
  - File name
  - Status (Success/Failed)
- Clear log button

---

## Capturing Screenshots

### Recommended Tools

**macOS**:
- Cmd + Shift + 4 + Space (window capture)
- Cmd + Shift + 4 (selection capture)
- Use Preview app for any editing/resizing

**Windows**:
- Windows + Shift + S (Snipping Tool)
- Use Paint or Paint 3D for editing

**Linux**:
- Gnome Screenshot
- Shutter
- Flameshot

### Editing Guidelines

1. **Crop** unnecessary browser chrome (address bar, bookmarks, etc.)
2. **Resize** to 1280x720 or maintain 16:9 aspect ratio
3. **Annotate** with arrows or highlights if needed (sparingly)
4. **Compress** to reduce file size (use TinyPNG or similar)
5. **Test** readability - ensure text is legible at smaller sizes

### Quality Checklist

Before submitting screenshots:
- [ ] All screenshots are at least 1280px wide
- [ ] File names are sequential: `screenshot-1.png`, `screenshot-2.png`, etc.
- [ ] Images are clear and crisp (not blurry or pixelated)
- [ ] No sensitive information visible (real user data, API keys, etc.)
- [ ] WordPress admin shows professional theme (avoid ugly/broken themes)
- [ ] Browser chrome is minimal or removed
- [ ] All text is legible
- [ ] File sizes are reasonable (<500 KB per screenshot)
- [ ] Descriptions added to readme.txt match screenshot numbers

---

## Screenshot Descriptions for readme.txt

Add these to the `== Screenshots ==` section in `readme.txt`:

```
== Screenshots ==

1. Settings - Image Optimization: Configure engine, format, quality, and auto-convert options
2. Settings - SVG Support: Enable secure SVG uploads and batch sanitization
3. Settings - System Status: View server capabilities and format support
4. Batch Processing: Progress bar showing bulk optimization of existing images
5. Media Library Meta Box: View conversion status and details for individual images
6. Front-End Delivery: Optimized images served in post content (Developer Tools view)
7. SVG Upload Preview: Client-side preview with DOMPurify before upload (Optional)
8. Security Logging: Track SVG sanitization events (Optional)
```

---

## Testing Screenshots

Before final submission, test screenshots by:

1. Viewing at different zoom levels (50%, 100%, 200%)
2. Checking on different devices (desktop, tablet, mobile)
3. Verifying descriptions match what's shown
4. Getting feedback from team members

---

## Submission

Once all screenshots are ready:

1. Place them in plugin root directory
2. Ensure they're named sequentially
3. Update readme.txt with descriptions
4. Test locally that descriptions match images
5. Include screenshots in WordPress.org SVN commit

---

## Future Updates

Screenshots should be updated when:
- UI changes significantly
- New major features are added
- WordPress admin design changes
- User feedback indicates confusion about features

Keep this document updated with any new screenshot requirements or changes to the WordPress.org guidelines.
