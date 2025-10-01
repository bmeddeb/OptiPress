=== OptiPress - Image Optimization & Safe SVG Handling ===
Contributors: bmeddeb
Tags: image optimization, webp, avif, svg, performance
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.6.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Boost WordPress performance with automatic WebP/AVIF conversion and secure SVG uploads. No CDN required.

== Description ==

**OptiPress** is a comprehensive image optimization plugin for WordPress that automatically converts your images to modern formats (WebP or AVIF) and enables secure SVG uploads with robust sanitization.

= Key Features =

**Image Optimization**
* Automatic conversion of JPG and PNG images to WebP or AVIF
* Support for both GD and Imagick libraries
* Auto-detect best available image processing engine
* Configurable quality settings (1-100)
* Batch processing for existing media library images
* Optional original file retention for easy reversion
* All image sizes converted (thumbnail, medium, large, full)

**SVG Security**
* Secure SVG upload support with rigorous sanitization
* Uses industry-standard enshrined/svg-sanitize library
* Removes malicious code: scripts, event handlers, external references
* Client-side preview with DOMPurify (optional)
* Batch sanitization for existing SVG files
* Comprehensive security logging

**Front-End Delivery**
* Automatic delivery of optimized images to compatible browsers
* Content filter replaces images in post content, widgets, and thumbnails
* Optional picture element generation for better browser compatibility
* Graceful fallback to original images for older browsers
* Works with all page builders and themes

**System Monitoring**
* Real-time capability detection (PHP version, GD, Imagick)
* Format support validation for selected engine
* Clear warnings for unsupported configurations
* Comprehensive system status dashboard

= Who Is This For? =

* Website owners looking to improve page load speeds
* Developers building performance-optimized WordPress sites
* Anyone who needs to safely upload SVG files to WordPress
* Sites targeting high Google PageSpeed scores

= Technical Details =

OptiPress uses a modular engine architecture that supports:
* **GD Engine**: WebP (all versions), AVIF (PHP 8.1+)
* **Imagick Engine**: WebP and AVIF (if supported by ImageMagick)

The plugin integrates seamlessly with WordPress:
* Hooks into `wp_generate_attachment_metadata` for automatic conversion
* Processes images in chunks to prevent server timeouts
* Stores metadata for tracking converted images
* Clean uninstallation removes all plugin data

= Requirements =

* WordPress 6.7 or higher
* PHP 7.4 or higher (PHP 8.1+ recommended for AVIF support)
* GD library or Imagick extension
* Write permissions in WordPress uploads directory

== Installation ==

1. Upload the `optipress` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > OptiPress to configure options
4. Select your preferred format (WebP or AVIF)
5. Enable auto-convert for new uploads
6. Optionally run batch processing on existing images

= From WordPress.org =

1. Search for "OptiPress" in the WordPress plugin directory
2. Click "Install Now" and then "Activate"
3. Configure via Settings > OptiPress

== Frequently Asked Questions ==

= What formats are supported for conversion? =

OptiPress converts JPG and PNG images to either WebP or AVIF format. You can choose your preferred output format in the settings.

= Do I need special server configuration? =

No special configuration is required. OptiPress automatically detects your server's capabilities (GD or Imagick) and uses the best available engine.

= Will this work with AVIF images? =

Yes, if your server supports it. AVIF requires:
- PHP 8.1+ when using GD library
- Imagick with AVIF support compiled in

The System Status page will show if AVIF is available on your server.

= What happens to my original images? =

By default, OptiPress keeps your original images. You can change this in settings, but it's recommended to keep originals for backup purposes. The "Revert All" feature only works if originals are kept.

= Are SVG uploads safe? =

Yes. OptiPress uses industry-standard sanitization (enshrined/svg-sanitize) to remove all potentially malicious code from SVG files before they're stored. This includes:
- Script tags
- Event handlers (onclick, onload, etc.)
- External entity references
- Data URIs and javascript: protocols

Every SVG is sanitized server-side, making uploads secure.

= Will this slow down my site? =

No. Image conversion happens once during upload (or during batch processing). Front-end delivery uses simple file checks with no performance impact. The optimized images actually make your site faster.

= Can I revert back to original images? =

Yes, if you kept the originals. The "Revert All to Originals" button in settings will delete all converted files and restore the originals.

= Does this work with WooCommerce/Elementor/other plugins? =

Yes. OptiPress works at the WordPress core level and is compatible with page builders, WooCommerce, and other plugins. The content filter handles images regardless of how they were added.

= What happens if I disable the plugin? =

Your optimized images remain in place. To completely clean up, use the uninstallation option which removes all plugin data and optionally reverts images.

= Does this work with lazy loading plugins? =

Yes. OptiPress replaces image URLs before lazy loading plugins process them, ensuring optimized images are lazy loaded.

= Can I use both WebP and AVIF? =

Not simultaneously. You choose one output format in settings. However, you can switch formats and re-run batch processing to convert to the new format.

= How do I check if it's working? =

1. Upload a new JPG or PNG image
2. Go to the Media Library and view the attachment details
3. The "Image Optimization" meta box shows conversion status
4. Check your uploads folder - you'll see .webp or .avif files alongside originals
5. View page source - optimized images should be served in content

== Screenshots ==

1. Settings - Image Optimization: Configure engine, format, quality, and auto-convert options
2. Settings - SVG Support: Enable secure SVG uploads and batch sanitization
3. Settings - System Status: View server capabilities and format support
4. Batch Processing: Progress bar showing bulk optimization of existing images
5. Media Library Meta Box: View conversion status and details for individual images
6. Front-End Delivery Settings: Configure how optimized images are served

== Changelog ==

= 1.0.0 =
* Initial release
* Automatic WebP/AVIF conversion for JPG and PNG images
* Support for GD and Imagick engines
* Secure SVG upload with sanitization
* Batch processing for existing media
* Content filter for front-end delivery
* Optional picture element generation
* Real-time system capability detection
* Comprehensive admin interface
* Client-side SVG preview with DOMPurify
* Attachment meta box showing conversion details
* Security logging for SVG sanitization
* Clean uninstallation with optional revert

= 0.4.4 =
* Development version
* Added attachment meta box
* Enhanced batch processing
* Added security logging
* Bug fixes and improvements

= 0.4.0 =
* Development version
* Added content filter for front-end delivery
* Added picture element support
* Enhanced system checks

= 0.3.0 =
* Development version
* Added batch processing
* Added SVG sanitization
* Added admin interface

= 0.2.0 =
* Development version
* Added image conversion engines
* Added engine registry

= 0.1.0 =
* Development version
* Initial plugin structure

== Upgrade Notice ==

= 1.0.0 =
First stable release. Upgrade to get the full feature set with image optimization and secure SVG handling.

== Technical Details ==

= Engine Architecture =

OptiPress uses a modular engine system with interface-based architecture:

* `ImageEngineInterface` - Base interface for all engines
* `GD_Engine` - PHP GD library implementation
* `Imagick_Engine` - ImageMagick extension implementation
* `Engine_Registry` - Manages engine selection and validation

Future engines can be added by implementing the interface.

= Hooks & Filters =

OptiPress uses these WordPress hooks:

**Image Conversion:**
* `wp_generate_attachment_metadata` - Triggers conversion on upload
* `delete_attachment` - Cleans up converted files on deletion

**SVG Handling:**
* `upload_mimes` - Enables SVG MIME type
* `wp_check_filetype_and_ext` - Validates SVG uploads
* `wp_handle_upload_prefilter` - Enforces size limits
* `wp_handle_upload` - Performs sanitization

**Front-End Delivery:**
* `the_content` - Replaces images in post content
* `post_thumbnail_html` - Optimizes featured images
* `get_avatar` - Optimizes avatar images
* `widget_text` - Optimizes images in text widgets

= AJAX Endpoints =

All batch processing uses AJAX with nonce validation:

* `optipress_get_batch_stats` - Get conversion statistics
* `optipress_process_batch` - Process image batch
* `optipress_revert_images` - Revert to originals
* `optipress_sanitize_svg_batch` - Batch sanitize SVGs

= File Structure =

```
optipress/
├── optipress.php (main plugin file)
├── uninstall.php (cleanup on deletion)
├── includes/
│   ├── class-system-check.php
│   ├── class-image-converter.php
│   ├── class-svg-sanitizer.php
│   ├── class-batch-processor.php
│   ├── class-content-filter.php
│   ├── class-admin-interface.php
│   ├── class-attachment-meta-box.php
│   └── engines/
│       ├── interface-image-engine.php
│       ├── class-gd-engine.php
│       ├── class-imagick-engine.php
│       └── class-engine-registry.php
├── admin/
│   ├── css/
│   ├── js/
│   └── views/
└── vendor/ (Composer dependencies)
```

== Privacy Policy ==

OptiPress does not collect, store, or transmit any user data. All image processing happens locally on your server. Security logs for SVG sanitization are stored locally in your WordPress database and contain only:
* Timestamp
* Event type (sanitization success/failure)
* File name
* Error message (if applicable)

Logs older than 90 days are automatically cleaned up.

== Support ==

For support, feature requests, or bug reports:
* GitHub: https://github.com/bmeddeb/OptiPress
* Plugin Homepage: https://optipress.meddeb.me

== Author ==

Developed by Ben Meddeb
* Website: https://meddeb.me
* GitHub: https://github.com/bmeddeb

== License ==

This plugin is licensed under the MIT License. You are free to use, modify, and distribute this plugin in accordance with the license terms.
