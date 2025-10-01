# OptiPress Download Shortcode Requirements

**Status**: Deferred until Library Organizer implementation
**Priority**: Medium
**Target Users**: Photographers, Scientific researchers, Content creators

## Background

OptiPress preserves original files (RAW, TIFF, PSD) while creating optimized web previews. Users need a way for visitors to download these original files on the front-end.

## User Stories

1. **As a photographer**, I want visitors to view optimized images but also download the original RAW files
2. **As a researcher**, I want to share TIFF files for analysis while displaying web-friendly previews
3. **As a content creator**, I want to offer high-quality downloads from galleries without manual linking

## Current State

- ✅ Admin can download originals from attachment edit page
- ✅ Original files preserved with metadata (`original_file` in attachment meta)
- ❌ No front-end download mechanism
- ❌ No shortcode system

## Deferred Design Considerations

### Context Detection
```php
// Auto-detect from attachment page
[optipress_download]

// Specify attachment ID
[optipress_download id="123"]

// Multiple images in post - how to handle?
[optipress_download id="123,456,789"]
```

### Integration with Future Library Organizer

**Questions to answer when designing organizer:**
1. How will galleries be structured?
2. Should download buttons be per-image or batch?
3. Do we need collection/album shortcodes?
4. Should there be access control (logged-in only)?
5. Download tracking/analytics?

### Potential Shortcode Syntax (Future)

```php
// Single download button
[optipress_download id="123" text="Download RAW" class="btn-primary"]

// Gallery with download options
[optipress_gallery ids="123,456,789" show_download="true"]

// Collection from library organizer (future)
[optipress_collection slug="landscape-2025" show_originals="true"]

// Auto-inject on attachment pages
// (via settings toggle, no shortcode needed)
```

### Technical Considerations

1. **Security**
   - Should originals be publicly accessible?
   - Rate limiting for large file downloads?
   - Hotlink protection?

2. **UX**
   - File size display
   - Format badge (CR2, TIFF, NEF)
   - Loading/progress indicators for large files
   - Mobile-friendly download experience

3. **Performance**
   - CDN compatibility
   - Direct download vs streaming
   - Bandwidth considerations

4. **Styling**
   - Default button styles
   - Theme compatibility
   - Dashicons vs custom icons
   - Responsive design

## Implementation Phases

### Phase 1: Library Organizer (Current Priority)
- Design collection/gallery system
- Define metadata structure
- User permissions and roles

### Phase 2: Shortcode System (Depends on Phase 1)
- Basic `[optipress_download]` shortcode
- Gallery integration
- Settings page options

### Phase 3: Advanced Features (Future)
- Download analytics
- Access control
- Watermarking options (for photographers)
- Bulk download (ZIP archives)

## Related Features to Consider

- **Front-end image manager**: Allow users to organize their own galleries
- **Download notifications**: Email alerts when originals are downloaded
- **License management**: Attach license info to downloads (Creative Commons, etc.)
- **Portfolio templates**: Pre-built layouts for photographers

## Notes

- Wait for library organizer design before implementing
- Consider WooCommerce integration for selling original files
- Check WordPress.org plugin guidelines for download functionality
- Research similar plugins: WP Download Manager, Download Monitor

---

**Last Updated**: 2025-10-01
**Next Review**: When library organizer design begins
