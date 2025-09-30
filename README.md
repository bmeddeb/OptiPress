***# OptiPress

**Image Optimization & Safe SVG Handling for WordPress**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)

OptiPress is a comprehensive WordPress plugin that automatically converts images to modern formats (WebP/AVIF) and enables secure SVG uploads with robust sanitization. Boost your site performance without complicated CDN setups or expensive third-party services.

---

## Features

### Image Optimization

- **Automatic Conversion**: Converts JPG and PNG to WebP or AVIF on upload
- **Multi-Engine Support**: Works with both GD and Imagick libraries
- **Smart Detection**: Auto-selects the best available engine
- **Batch Processing**: Convert thousands of existing images with AJAX-driven UI
- **Quality Control**: Configurable quality settings (1-100)
- **Reversible**: Keep originals and revert anytime
- **All Sizes**: Converts thumbnail, medium, large, and full-size images

### SVG Security

- **Secure Uploads**: Enable SVG uploads with enterprise-grade sanitization
- **Server-Side Protection**: Uses `enshrined/svg-sanitize` library
- **Client Preview**: Optional DOMPurify preview before upload
- **Threat Removal**: Strips scripts, event handlers, external references
- **Batch Sanitization**: Re-sanitize existing SVG files
- **Security Logging**: Track sanitization events with auto-cleanup

### Front-End Delivery

- **Content Filter**: Automatically serves optimized images in content
- **Picture Elements**: Optional `<picture>` tag generation
- **Browser Compatibility**: Graceful fallback for older browsers
- **Universal Support**: Works with all themes and page builders
- **Performance**: Zero overhead - simple file checks only

### System Monitoring

- **Capability Detection**: Real-time PHP and library detection
- **Format Validation**: Warns about unsupported configurations
- **Status Dashboard**: Comprehensive system information
- **Smart Warnings**: Clear guidance on requirements

---

## Requirements

| Requirement | Minimum | Recommended |
|------------|---------|-------------|
| **WordPress** | 6.7+ | Latest |
| **PHP** | 7.4+ | 8.1+ (for AVIF) |
| **Image Library** | GD or Imagick | Imagick |
| **Permissions** | Write access to uploads | - |

### Format Support Matrix

| Engine | WebP | AVIF |
|--------|------|------|
| **GD** | Always | PHP 8.1+ |
| **Imagick** | If compiled | If compiled |

---

## Installation

### From GitHub

```bash
cd wp-content/plugins/
git clone https://github.com/bmeddeb/OptiPress.git optipress
cd optipress
composer install
```

Then activate via WordPress admin: **Plugins > Installed Plugins > OptiPress**

### From Release Package

1. Download the latest release `.zip` from [GitHub Releases](https://github.com/bmeddeb/OptiPress/releases)
2. Upload via **Plugins > Add New > Upload Plugin**
3. Activate the plugin

### From WordPress.org (Coming Soon)

1. Go to **Plugins > Add New**
2. Search for "OptiPress"
3. Click **Install Now** then **Activate**

---

## Configuration

### Quick Start

1. Navigate to **Settings > OptiPress**
2. **Image Optimization Tab**:
   - Select engine: `Auto-detect` (recommended)
   - Choose format: `WebP` or `AVIF`
   - Set quality: `85` (default)
   - Enable **Auto-convert on Upload**
   - Enable **Keep Original Images** (recommended)
3. **SVG Support Tab** (optional):
   - Enable **SVG Uploads** if needed
   - Run **Batch Sanitizer** for existing SVGs
4. **System Status Tab**:
   - Verify your server capabilities
   - Check format support

### Batch Processing

Convert existing images:

1. Go to **Settings > OptiPress > Image Optimization**
2. View statistics (Total / Converted / Remaining)
3. Click **Start Bulk Optimization**
4. Wait for progress bar to complete

Revert to originals:

1. Ensure **Keep Original Images** was enabled
2. Click **Revert All to Originals**
3. Confirm the action

---

## Architecture

### Engine System

OptiPress uses a modular, interface-based engine architecture:

```php
interface ImageEngineInterface {
    public function is_available(): bool;
    public function supports_format(string $format): bool;
    public function convert(string $source, string $dest, string $format, int $quality): bool;
    public function get_name(): string;
}
```

**Built-in Engines:**

- `GD_Engine` - PHP GD library (WebP always, AVIF on PHP 8.1+)
- `Imagick_Engine` - ImageMagick extension (format support depends on compilation)

**Adding Custom Engines:**

```php
class My_Custom_Engine implements \OptiPress\Engines\ImageEngineInterface {
    // Implement interface methods
}

// Register in engine registry
add_filter('optipress_engines', function($engines) {
    $engines[] = new My_Custom_Engine();
    return $engines;
});
```

### File Structure

```
optipress/
├── optipress.php                     # Main plugin file
├── uninstall.php                     # Cleanup on deletion
├── composer.json                     # PHP dependencies
├── package.json                      # JS dependencies (optional SVG preview)
├── LICENSE                           # MIT License
├── README.md                         # This file
├── readme.txt                        # WordPress.org readme
├── includes/
│   ├── class-system-check.php        # Capability detection
│   ├── class-image-converter.php     # Conversion logic
│   ├── class-svg-sanitizer.php       # SVG security
│   ├── class-batch-processor.php     # Bulk operations
│   ├── class-content-filter.php      # Front-end delivery
│   ├── class-admin-interface.php     # Settings UI
│   ├── class-attachment-meta-box.php # Media library info
│   └── engines/
│       ├── interface-image-engine.php
│       ├── class-gd-engine.php
│       ├── class-imagick-engine.php
│       └── class-engine-registry.php
├── admin/
│   ├── css/
│   │   └── admin-styles.css
│   ├── js/
│   │   ├── batch-processor.js        # AJAX batch UI
│   │   └── svg-preview.bundle.js     # DOMPurify preview
│   └── views/
│       ├── settings-optimization.php
│       ├── settings-svg.php
│       └── settings-system-status.php
├── src/
│   └── js/
│       └── svg-preview.js            # Source before bundling
├── languages/                         # Translation files (.pot)
└── vendor/                            # Composer dependencies
    └── enshrined/svg-sanitize/
```

---

## WordPress Hooks

### Image Conversion

```php
// Triggered after WordPress generates image sizes
add_filter('wp_generate_attachment_metadata', [$this, 'convert_image'], 10, 2);

// Clean up converted files on deletion
add_action('delete_attachment', [$this, 'cleanup_converted_files']);
```

### SVG Sanitization

```php
// Enable SVG MIME type
add_filter('upload_mimes', [$this, 'enable_svg_mime']);
add_filter('wp_check_filetype_and_ext', [$this, 'check_svg_filetype'], 10, 4);

// Sanitize on upload
add_filter('wp_handle_upload_prefilter', [$this, 'validate_svg_upload']);
add_filter('wp_handle_upload', [$this, 'sanitize_svg_upload']);
```

### Front-End Delivery

```php
// Replace images in content
add_filter('the_content', [$this, 'replace_images'], 20);
add_filter('post_thumbnail_html', [$this, 'replace_images'], 20);
add_filter('get_avatar', [$this, 'replace_images'], 20);
add_filter('widget_text', [$this, 'replace_images'], 20);
```

### AJAX Endpoints

All endpoints require nonces and `manage_options` capability:

```javascript
// Get batch statistics
wp.ajax.post('optipress_get_batch_stats', { security: nonce });

// Process image batch
wp.ajax.post('optipress_process_batch', { security: nonce, offset: 0, limit: 15 });

// Revert to originals
wp.ajax.post('optipress_revert_images', { security: nonce });

// Batch sanitize SVGs
wp.ajax.post('optipress_sanitize_svg_batch', { security: nonce, offset: 0 });
```

---

## Security

### SVG Sanitization Process

OptiPress uses a defense-in-depth approach for SVG security:

1. **Client-Side Preview** (Optional):
   - DOMPurify sanitization for quick preview
   - Shows user what will be removed
   - NOT authoritative - only for UX

2. **Server-Side Sanitization** (Required):
   - Safe XML parsing with `libxml_disable_entity_loader(true)`
   - `enshrined/svg-sanitize` library with `removeRemoteReferences(true)`
   - Additional regex hardening for `<foreignObject>` and event handlers
   - XML validation after sanitization

3. **Security Logging**:
   - All sanitization events logged with timestamp
   - Automatic cleanup after 90 days
   - Queryable via WordPress admin

### Threats Removed

- `<script>` tags
- JavaScript event attributes (`onclick`, `onload`, etc.)
- `<foreignObject>` elements
- External entity references (XXE attacks)
- `data:` URIs
- `javascript:` protocols
- Remote references in `xlink:href`, `url()`

---

## Development

### Building from Source

```bash
# Install PHP dependencies
composer install

# Install JS dependencies (for SVG preview)
npm install

# Build JS bundle
npm run build

# Watch for changes during development
npm run watch
```

### Coding Standards

This project follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/):

```bash
# Check PHP code
composer phpcs

# Auto-fix PHP code
composer phpcbf

# Check JS code
npm run lint

# Auto-fix JS code
npm run lint:fix
```

### Testing Checklist

- [ ] Test with WordPress 6.7+
- [ ] Test on PHP 7.4, 8.0, 8.1, 8.2, 8.3
- [ ] Test with GD only, Imagick only, both
- [ ] Test WebP conversion
- [ ] Test AVIF conversion (PHP 8.1+)
- [ ] Test SVG upload and sanitization
- [ ] Test batch processing with 100+ images
- [ ] Test revert functionality
- [ ] Test content filter on front-end
- [ ] Test with popular themes and plugins
- [ ] Test uninstallation cleanup

---

## Documentation

### User Guides

- [Installation Guide](docs/installation.md) *(coming soon)*
- [Configuration Guide](docs/configuration.md) *(coming soon)*
- [Troubleshooting](docs/troubleshooting.md) *(coming soon)*
- [FAQ](docs/faq.md) *(coming soon)*

### Developer Guides

- [API Reference](docs/api.md) *(coming soon)*
- [Creating Custom Engines](docs/custom-engines.md) *(coming soon)*
- [Hooks & Filters](docs/hooks.md) *(coming soon)*
- [Contributing Guidelines](CONTRIBUTING.md) *(coming soon)*

---

## Contributing

Contributions are welcome! Please read the [Contributing Guidelines](CONTRIBUTING.md) *(coming soon)* first.

### Development Process

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Make your changes
4. Run tests and code standards checks
5. Commit with clear messages: `git commit -m "feat: add custom engine support"`
6. Push to your fork: `git push origin feature/my-feature`
7. Create a Pull Request

### Commit Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` New features
- `fix:` Bug fixes
- `docs:` Documentation changes
- `style:` Code style changes (formatting, etc.)
- `refactor:` Code refactoring
- `test:` Adding or updating tests
- `chore:` Maintenance tasks

---

## Changelog

### [1.0.0] - TBD

- Initial stable release
- Automatic WebP/AVIF conversion
- Secure SVG upload support
- Batch processing
- Content filter delivery
- Admin interface
- System monitoring

See [CHANGELOG.md](CHANGELOG.md) *(coming soon)* for full history.

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

### MIT License Summary

**Permissions**: Commercial use, modification, distribution, private use
**Limitations**: Liability, warranty
**Conditions**: License and copyright notice must be included

---

## Author

**Ben Meddeb**

- Website: [meddeb.me](https://ben.meddeb.me)
- GitHub: [@bmeddeb](https://github.com/bmeddeb)
- Plugin Homepage: [optipress.meddeb.me](https://optipress.meddeb.me)

---

## Acknowledgments

- [enshrined/svg-sanitize](https://github.com/darylldoyle/svg-sanitizer) - SVG sanitization library
- [DOMPurify](https://github.com/cure53/DOMPurify) - Client-side HTML sanitizer
- WordPress community for feedback and testing

---

## Support

- **Bug Reports**: [GitHub Issues](https://github.com/bmeddeb/OptiPress/issues)
- **Feature Requests**: [GitHub Discussions](https://github.com/bmeddeb/OptiPress/discussions)
- **Security Issues**: Please email security concerns privately to [security@meddeb.me](mailto:security@meddeb.me)

---

## Show Your Support

If you find OptiPress useful, please consider:

- Starring this repository
- Reporting bugs
- Suggesting features
- Contributing code or documentation
- [Buying me a coffee](https://buymeacoffee.com/bmeddeb)

---

**Made with love for the WordPress community**
