# OptiPress Code Review Checklist

## 🔴 Critical - URL Handling & Conversion Issues

### URL Rewriting Robustness
- [x] Fix URL rewriting to handle query strings (e.g., `image.jpg?ver=123`)
  - [x] Replace regex-based extension matching with `wp_parse_url` approach
  - [x] Split scheme/host/path/query/fragment properly
  - [x] Replace extension only in path component
  - [x] Preserve query strings and fragments after conversion
- [x] Handle uppercase extensions (JPG, PNG, JPEG)
- [x] Handle edge filenames with multiple dots
- [ ] Update both PHP and JS URL handling flows
- [x] Fix `Content_Filter::get_optimized_url` to use `wp_parse_url` instead of `pathinfo`

### Animated Image Handling
- [x] Implement detection for animated GIF files
- [x] Implement detection for animated WebP files
- [x] Add logic to skip conversion of animated images OR
- [ ] Implement animation-preserving conversion pipeline
- [x] Add animation support status to System Status display
- [ ] Document animation handling behavior

## 🟡 Performance Optimizations

### Caching Improvements
- [x] Add static cache in `Image_Converter` for file existence checks
  - [x] Mirror implementation from `Content_Filter::$file_cache`
  - [x] Apply to `src` and `srcset` filter methods
- [ ] Optimize hot paths with repeated `file_exists()` calls
- [ ] Add early exits in filters for non-image posts
- [ ] Add early exits for contexts that don't need processing
- [ ] Consider transient caching for frequently accessed converted URLs

## 🔒 Security Enhancements

### Filesystem Operations
- [x] Add error handling for Organizer `.htaccess` writes
  - [x] Check return status of `file_put_contents`
  - [x] Add logging for write failures
  - [x] Consider adding `index.php` deny file for non-Apache servers
- [ ] Add capability checks for organizer-only admin features
  - [x] Gate organizer admin pages by filtered capability
  - [x] Provide filter `optipress_organizer_capability` for customization
- [ ] Review and test XXE mitigations in SVG handling

### Additional SVG Hardening
- [ ] Consider implementing optional denylist for dangerous SVG filters
- [ ] Review obscure SVG attributes for potential security issues
- [ ] Ensure all SVG error cases are properly logged

## 🧪 Testing & QA

### Test Coverage Expansion
- [ ] Add test cases for URLs with query strings in:
  - [ ] Theme `<img>` tags
  - [ ] Content blocks
  - [ ] Srcset attributes
- [ ] Test animated GIF upload and presentation
- [ ] Test large TIFF/PSD files for preview pipeline
- [ ] Test Thumbnailer integration with advanced formats
- [ ] Performance test posts with many images
- [ ] Verify `srcset` generation with various configurations
- [ ] Test conversion behavior when result is larger than original

### Documentation
- [ ] Document URL handling edge cases
- [ ] Document animated image behavior
- [ ] Add POT generation instructions
- [ ] Document organizer filesystem requirements
- [ ] Update testing guide with new test scenarios

## 💡 Feature Enhancements

### Conversion Logic
- [ ] When `keep_originals` is false:
  - [ ] Consider preserving original if conversion yields larger file
  - [ ] Add threshold setting for negative savings prevention
  - [ ] Add user notification for skipped conversions
- [ ] Add support for animated WebP generation where feasible
- [ ] Improve batch processor feedback for skipped files

### Admin UI/UX
- [ ] Add visual indicators for animated images in media library
- [ ] Display animation preservation status in attachment details
- [ ] Add conversion size comparison in media column
- [ ] Improve error messaging for failed conversions

## ✅ Code Quality

### Internationalization
- [ ] Set up automated POT file generation
- [ ] Review all user-facing strings for proper i18n
- [ ] Ensure translation comments are comprehensive

### Build Process
- [ ] Verify ESLint is running on all JS files
- [ ] Ensure PHPCS is checking all PHP files
- [ ] Add pre-commit hooks for code standards
- [ ] Document build process for contributors

### Error Handling
- [ ] Add comprehensive error logging for conversion failures
- [ ] Improve error recovery in batch processing
- [ ] Add admin notices for critical failures
- [ ] Implement retry mechanism for transient failures

## 📊 Monitoring & Reporting

### System Status Enhancements
- [ ] Add animation support status display
- [ ] Show memory usage statistics
- [ ] Display conversion success rates
- [ ] Add filesystem write permission checks
- [ ] Show active engine capabilities

### Analytics
- [ ] Track conversion statistics
- [ ] Monitor performance impact
- [ ] Log common failure patterns
- [ ] Generate periodic optimization reports

## 🔄 Continuous Improvement

### Future Considerations
- [ ] Investigate CDN integration improvements
- [ ] Research new image format support (JPEG XL, etc.)
- [ ] Consider implementing lazy conversion option
- [ ] Explore WebAssembly for client-side fallbacks
- [ ] Plan for WordPress 6.x compatibility updates

---

## Priority Legend
- 🔴 **Critical**: Address immediately - affects functionality or security
- 🟡 **Important**: Address soon - impacts performance or user experience  
- 🔒 **Security**: Security-related improvements
- 🧪 **Testing**: QA and testing improvements
- 💡 **Enhancement**: Feature additions and improvements
- ✅ **Quality**: Code quality and maintenance tasks
- 📊 **Monitoring**: Observability and reporting
- 🔄 **Future**: Long-term considerations

## Completion Tracking
- Total Items: ~60
- Completed: 14
- In Progress: 1
- Remaining: ~45

Last Updated: 2025-10-02
Reviewer: Code Review Bot
Version: OptiPress 0.6.2
