# Release Checklist for OptiPress 1.0.0

This checklist ensures all requirements are met before releasing OptiPress to the public. Complete each section in order and mark items as done.

---

## Pre-Release Testing

### Functional Testing

#### Image Conversion
- [ ] Test JPG to WebP conversion (GD engine)
- [ ] Test JPG to AVIF conversion (GD engine, PHP 8.1+)
- [ ] Test PNG to WebP conversion (GD engine)
- [ ] Test PNG to AVIF conversion (GD engine, PHP 8.1+)
- [ ] Test JPG to WebP conversion (Imagick engine)
- [ ] Test JPG to AVIF conversion (Imagick engine)
- [ ] Test PNG to WebP conversion (Imagick engine)
- [ ] Test PNG to AVIF conversion (Imagick engine)
- [ ] Test auto-convert on upload (enabled)
- [ ] Test manual conversion from attachment meta box
- [ ] Test all image sizes converted (thumbnail, medium, large, full)
- [ ] Test metadata stored correctly (`_optipress_converted`, `_optipress_format`, `_optipress_engine`)
- [ ] Test with "Keep Originals" enabled
- [ ] Test with "Keep Originals" disabled (originals deleted)
- [ ] Test conversion failure handling (corrupted images)

#### Batch Processing
- [ ] Test batch conversion with 10 images
- [ ] Test batch conversion with 100+ images
- [ ] Test batch conversion with 1000+ images
- [ ] Test progress bar updates correctly
- [ ] Test AJAX chunking (no timeouts)
- [ ] Test pause/resume (browser refresh during processing)
- [ ] Test batch statistics accuracy (Total/Converted/Remaining)
- [ ] Test "Revert All to Originals" functionality
- [ ] Test revert with originals present
- [ ] Test revert fails gracefully when originals missing
- [ ] Test batch processing error handling (permission issues, disk space)

#### SVG Sanitization
- [ ] Test SVG upload with clean SVG file
- [ ] Test SVG upload with `<script>` tag (should be removed)
- [ ] Test SVG upload with event handlers (`onclick`, `onload` - should be removed)
- [ ] Test SVG upload with `<foreignObject>` (should be removed)
- [ ] Test SVG upload with external references (should be blocked)
- [ ] Test SVG upload with `data:` URIs (should be blocked)
- [ ] Test SVG upload with `javascript:` protocol (should be blocked)
- [ ] Test SVG batch sanitization for existing files
- [ ] Test SVG sanitization failure handling
- [ ] Test security logging for SVG events
- [ ] Test client-side preview with DOMPurify (if enabled)
- [ ] Test SVG file size limits

#### Front-End Delivery
- [ ] Test content filter replaces images in post content
- [ ] Test featured images (post thumbnails) are optimized
- [ ] Test images in widgets are optimized
- [ ] Test images in Gutenberg blocks
- [ ] Test images with page builders (Elementor, if available)
- [ ] Test picture element mode (if enabled)
- [ ] Test fallback to originals for unsupported formats
- [ ] Test with caching plugins (W3 Total Cache, WP Super Cache)
- [ ] Test browser compatibility (Chrome, Firefox, Safari, Edge)
- [ ] Test no double-replacement occurs

#### Admin Interface
- [ ] Test Settings > OptiPress page loads correctly
- [ ] Test Image Optimization tab saves settings
- [ ] Test SVG Support tab saves settings
- [ ] Test System Status tab displays accurate information
- [ ] Test engine selector (Auto-detect, GD, Imagick)
- [ ] Test format selector (WebP, AVIF)
- [ ] Test quality slider (1-100)
- [ ] Test all toggle switches save correctly
- [ ] Test nonce validation on form submission
- [ ] Test capability checks (`manage_options`)
- [ ] Test compatibility warnings for unsupported configurations
- [ ] Test attachment meta box displays correct information
- [ ] Test re-convert button in meta box

#### System Checks
- [ ] Test on PHP 7.4
- [ ] Test on PHP 8.0
- [ ] Test on PHP 8.1 (AVIF support in GD)
- [ ] Test on PHP 8.2
- [ ] Test on PHP 8.3
- [ ] Test with GD only (no Imagick)
- [ ] Test with Imagick only (no GD)
- [ ] Test with both GD and Imagick
- [ ] Test with neither GD nor Imagick (warnings shown)
- [ ] Test system status page shows correct PHP version
- [ ] Test system status shows correct library availability
- [ ] Test format support detection accurate

### WordPress Compatibility

- [ ] Test on WordPress 6.7
- [ ] Test with Twenty Twenty-Four theme
- [ ] Test with popular themes (Astra, GeneratePress, etc.)
- [ ] Test with WooCommerce
- [ ] Test with Yoast SEO
- [ ] Test with Elementor
- [ ] Test with Contact Form 7
- [ ] Test multisite compatibility

### Performance Testing

- [ ] Test upload performance with large images (10MB+)
- [ ] Test batch processing doesn't cause timeouts
- [ ] Profile memory usage during conversion
- [ ] Profile execution time for typical operations
- [ ] Test with 10,000+ images in media library
- [ ] Test front-end page load time impact
- [ ] Test database query performance

### Security Testing

- [ ] Test SVG sanitization with known XSS vectors
- [ ] Test nonce validation on all AJAX endpoints
- [ ] Test capability checks on all admin pages
- [ ] Test file upload validation (MIME type, size, extension)
- [ ] Test path traversal prevention
- [ ] Test SQL injection prevention
- [ ] Test XSS prevention in admin output
- [ ] Test CSRF protection
- [ ] Run security scan (e.g., WPScan, Sucuri)

### Error Handling

- [ ] Test with insufficient file permissions
- [ ] Test with missing required libraries (GD/Imagick)
- [ ] Test with corrupted image files
- [ ] Test with disk space full
- [ ] Test with invalid file paths
- [ ] Test with unsupported image formats
- [ ] Verify all errors show user-friendly messages
- [ ] Verify errors are logged appropriately

### Uninstallation

- [ ] Test uninstall.php removes all options
- [ ] Test uninstall.php removes all post meta
- [ ] Test uninstall.php removes security log entries
- [ ] Test uninstall.php removes scheduled events
- [ ] Test optional revert to originals on uninstall
- [ ] Test clean database after uninstall
- [ ] Test converted files handling on uninstall

---

## Code Quality

### PHP Standards

- [ ] Run PHP CodeSniffer: `composer phpcs`
- [ ] Fix all coding standards violations: `composer phpcbf`
- [ ] No errors, warnings, or notices
- [ ] All files follow WordPress Coding Standards
- [ ] No debug code left (var_dump, print_r, etc.)
- [ ] No TODO comments for critical issues

### JavaScript Standards

- [ ] Run ESLint: `npm run lint`
- [ ] Fix all linting errors: `npm run lint:fix`
- [ ] All JS files follow WordPress JS standards
- [ ] No console.log statements in production code
- [ ] All event handlers properly bound/unbound

### PHPDoc Comments

- [ ] All classes have PHPDoc blocks
- [ ] All public methods have PHPDoc blocks
- [ ] All parameters documented with `@param`
- [ ] All return values documented with `@return`
- [ ] All exceptions documented with `@throws`
- [ ] All hooks documented with `@hook`

### JSDoc Comments

- [ ] All JavaScript functions have JSDoc blocks
- [ ] All parameters documented
- [ ] All return values documented
- [ ] Event handlers documented

### Code Review

- [ ] No hardcoded credentials or API keys
- [ ] No sensitive information in code
- [ ] No unused functions or classes
- [ ] No duplicate code (DRY principle)
- [ ] Proper error handling throughout
- [ ] Proper input sanitization throughout
- [ ] Proper output escaping throughout

---

## Internationalization (i18n)

### Text Domains

- [ ] All strings wrapped in `__()`, `_e()`, `esc_html__()`, etc.
- [ ] Text domain is `'optipress'` everywhere
- [ ] No hardcoded English strings
- [ ] Proper translator comments for ambiguous strings
- [ ] Plural forms use `_n()` or `_nx()`
- [ ] Context provided with `_x()` where needed

### Translation Files

- [ ] Generate .pot file: `wp i18n make-pot . languages/optipress.pot`
- [ ] .pot file contains all translatable strings
- [ ] .pot file has correct plugin metadata
- [ ] .pot file references correct text domain
- [ ] Test loading textdomain on `init` hook

### Translation Testing

- [ ] Test with a translation plugin (e.g., Loco Translate)
- [ ] Test with right-to-left (RTL) language
- [ ] Verify all admin strings are translatable
- [ ] Verify all front-end strings are translatable
- [ ] Verify JavaScript strings are translatable (if applicable)

---

## Documentation

### README Files

- [ ] `readme.txt` (WordPress.org format) complete and accurate
- [ ] `README.md` (GitHub format) complete and accurate
- [ ] Feature list is comprehensive
- [ ] Installation instructions are clear
- [ ] FAQ section answers common questions
- [ ] Changelog is up to date
- [ ] Screenshots section describes all images
- [ ] License information is correct (MIT)
- [ ] Author information is correct (Ben Meddeb)
- [ ] Links are correct (GitHub, homepage, author site)

### Inline Documentation

- [ ] All complex logic has explanatory comments
- [ ] All hooks and filters documented
- [ ] All security-critical code has security comments
- [ ] All performance-critical code has performance comments

### User Documentation

- [ ] Installation guide clear for beginners
- [ ] Configuration guide covers all settings
- [ ] Troubleshooting section helpful
- [ ] FAQ addresses common issues
- [ ] Links to support resources provided

### Developer Documentation

- [ ] Engine architecture explained
- [ ] Custom engine creation documented
- [ ] Hooks and filters listed
- [ ] AJAX endpoints documented
- [ ] File structure explained

---

## Assets & Media

### Screenshots

- [ ] All required screenshots captured (6 minimum)
- [ ] Screenshots are 1280x720 or larger
- [ ] Screenshots are clear and professional
- [ ] Screenshots named correctly (`screenshot-1.png`, etc.)
- [ ] Screenshot descriptions in readme.txt match images
- [ ] No sensitive information in screenshots
- [ ] Screenshots compressed for web

### Plugin Banner (WordPress.org)

- [ ] Banner created (772x250 for main, 1544x500 for retina)
- [ ] Banner follows WordPress.org guidelines
- [ ] Banner includes plugin name and tagline
- [ ] Banner is professional and on-brand

### Plugin Icon (WordPress.org)

- [ ] Icon created (128x128 and 256x256)
- [ ] Icon is recognizable at small sizes
- [ ] Icon matches banner design
- [ ] Icon has transparent background (if applicable)

---

## Files & Package

### File Structure

- [ ] All necessary files present
- [ ] No development files in distribution
- [ ] `.distignore` properly configured
- [ ] `LICENSE` file included (MIT)
- [ ] `readme.txt` in root
- [ ] `README.md` in root
- [ ] All PHP files have proper headers
- [ ] All admin assets compiled and minified

### Composer Dependencies

- [ ] Run `composer install --no-dev` for production
- [ ] `vendor/` directory included in distribution
- [ ] `composer.json` and `composer.lock` excluded from distribution
- [ ] All dependencies are compatible with WordPress

### Node Dependencies

- [ ] Run `npm run build` for production assets
- [ ] Bundled JS files included in distribution
- [ ] Source maps excluded from distribution
- [ ] `node_modules/` excluded from distribution
- [ ] `package.json` and `package-lock.json` excluded from distribution

### Version Numbers

- [ ] Version number updated in `optipress.php` header
- [ ] Version constant updated (`OPTIPRESS_VERSION`)
- [ ] Version in `readme.txt` matches
- [ ] Stable tag in `readme.txt` matches
- [ ] All version numbers consistent throughout

### Plugin Header

- [ ] Plugin Name: OptiPress
- [ ] Plugin URI: https://optipress.meddeb.me
- [ ] Description accurate and concise
- [ ] Version: 1.0.0
- [ ] Requires at least: 6.7
- [ ] Requires PHP: 7.4
- [ ] Author: Ben Meddeb
- [ ] Author URI: https://meddeb.me
- [ ] License: MIT
- [ ] License URI: https://opensource.org/licenses/MIT
- [ ] Text Domain: optipress
- [ ] Domain Path: /languages

---

## Legal & Licensing

### License Files

- [ ] `LICENSE` file present in root
- [ ] LICENSE contains full MIT license text
- [ ] Copyright year is current (2025)
- [ ] Copyright holder is Ben Meddeb
- [ ] License properly referenced in plugin header
- [ ] License properly referenced in readme.txt
- [ ] License properly referenced in README.md

### Third-Party Dependencies

- [ ] All dependencies' licenses reviewed
- [ ] All dependencies compatible with MIT license
- [ ] Dependencies properly attributed:
  - [ ] `enshrined/svg-sanitize` (LGPL-2.0-only)
  - [ ] `DOMPurify` (Apache-2.0 or MPL-2.0)
- [ ] Dependency licenses included if required

### Attribution

- [ ] Author information correct in all files
- [ ] Homepage link correct (https://optipress.meddeb.me)
- [ ] GitHub repo link correct (https://github.com/bmeddeb/OptiPress)
- [ ] Credits section in README.md complete

---

## Distribution

### WordPress.org Submission

- [ ] SVN repository set up
- [ ] Trunk contains latest code
- [ ] Tags directory created
- [ ] Tag 1.0.0 created
- [ ] Assets directory contains:
  - [ ] Banner (772x250 and 1544x500)
  - [ ] Icon (128x128 and 256x256)
  - [ ] Screenshots (all 6+ images)
- [ ] Plugin review guidelines followed
- [ ] Submission form filled out
- [ ] Plugin submitted for review

### GitHub Release

- [ ] Repository clean (no uncommitted changes)
- [ ] All development branches merged
- [ ] Git tag `v1.0.0` created
- [ ] GitHub release created
- [ ] Release notes written
- [ ] Zip file attached to release
- [ ] Release marked as "Latest"

### Package Creation

- [ ] Create distribution zip: `zip -r optipress-1.0.0.zip optipress/`
- [ ] Verify zip contains all required files
- [ ] Verify zip excludes development files
- [ ] Test zip installation on clean WordPress
- [ ] Verify plugin activates without errors
- [ ] Verify all features work from zip installation

---

## Communication

### Announcement

- [ ] Plugin homepage updated (https://optipress.meddeb.me)
- [ ] Blog post written announcing release
- [ ] Social media posts prepared
- [ ] WordPress.org plugin page updated
- [ ] GitHub README updated
- [ ] Email announcement to subscribers (if applicable)

### Support Channels

- [ ] GitHub Issues enabled
- [ ] GitHub Discussions enabled
- [ ] Support email configured (if applicable)
- [ ] WordPress.org support forum monitored
- [ ] Documentation links active

---

## Post-Release

### Monitoring

- [ ] Monitor WordPress.org reviews
- [ ] Monitor GitHub issues
- [ ] Monitor error logs for new issues
- [ ] Track download statistics
- [ ] Track active installations

### User Feedback

- [ ] Set up feedback collection system
- [ ] Respond to user questions promptly
- [ ] Track feature requests
- [ ] Document bug reports

### Next Steps

- [ ] Plan for version 1.1.0 features
- [ ] Update PROJECT-PHASES.md with Phase 11
- [ ] Review and prioritize feature requests
- [ ] Schedule regular maintenance releases

---

## Final Verification

Before clicking "Submit" on WordPress.org:

- [ ] Test installation one more time on clean WP install
- [ ] Verify all links in readme.txt work
- [ ] Verify license information is correct
- [ ] Verify author information is correct
- [ ] Verify version numbers all match
- [ ] Verify no development code included
- [ ] Take a deep breath and click Submit!

---

## Rollback Plan

In case critical issues are found after release:

1. **Immediate Actions**:
   - [ ] Document the issue
   - [ ] Assess severity (security vs. functionality)
   - [ ] Determine if rollback or hotfix is needed

2. **Hotfix Process**:
   - [ ] Create hotfix branch
   - [ ] Fix critical issue
   - [ ] Test fix thoroughly
   - [ ] Release version 1.0.1 immediately
   - [ ] Update WordPress.org

3. **Communication**:
   - [ ] Notify users of issue
   - [ ] Explain fix
   - [ ] Encourage update
   - [ ] Update documentation

---

## Notes

- This checklist should be completed over several days/weeks, not rushed
- Each testing section should be done by multiple people if possible
- Document any issues found during testing
- Update this checklist for future releases based on lessons learned

---

**Release Date**: TBD
**Released By**: Ben Meddeb
**Version**: 1.0.0

_Last updated: [Current Date]_
