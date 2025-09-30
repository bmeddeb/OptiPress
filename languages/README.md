# Translation Files

This directory contains translation files for the OptiPress plugin.

## Generating the .pot File

The `.pot` (Portable Object Template) file contains all translatable strings from the plugin and serves as the template for creating translations in different languages.

### Method 1: Using WP-CLI (Recommended)

If you have [WP-CLI](https://wp-cli.org/) installed:

```bash
cd /path/to/optipress
wp i18n make-pot . languages/optipress.pot
```

### Method 2: Using Poedit

1. Download and install [Poedit](https://poedit.net/)
2. Open Poedit
3. Select "File" > "New from source code"
4. Browse to the OptiPress plugin directory
5. Poedit will scan for translatable strings
6. Save as `languages/optipress.pot`

### Method 3: Using WordPress.org Plugin Repository Tools

When you submit the plugin to WordPress.org, the translation infrastructure will automatically:
1. Extract translatable strings
2. Generate the .pot file
3. Make it available on [translate.wordpress.org](https://translate.wordpress.org/)

## Translation Files Structure

Once the .pot file is generated and translations are created, this directory will contain:

```
languages/
├── optipress.pot              # Template file (all translatable strings)
├── optipress-fr_FR.po         # French translation (example)
├── optipress-fr_FR.mo         # French compiled translation
├── optipress-es_ES.po         # Spanish translation (example)
├── optipress-es_ES.mo         # Spanish compiled translation
└── README.md                  # This file
```

## Creating Translations

### For Translators

1. Get the `optipress.pot` file from this directory
2. Use a translation tool like [Poedit](https://poedit.net/) or [Loco Translate](https://wordpress.org/plugins/loco-translate/)
3. Create a new translation from the .pot template
4. Translate all strings in your language
5. Save as `optipress-{locale}.po` (e.g., `optipress-fr_FR.po`)
6. Generate the `.mo` file (compiled translation)
7. Place both `.po` and `.mo` files in this directory

### Using translate.wordpress.org (Recommended)

Once OptiPress is on WordPress.org, translators can contribute through:
- [https://translate.wordpress.org/projects/wp-plugins/optipress/](https://translate.wordpress.org/projects/wp-plugins/optipress/)

Benefits:
- Collaborative translation
- Quality review process
- Automatic updates
- Translation memory
- Translation consistency tools

## Translation Status

Current translations:
- 🇬🇧 English (en_US): 100% (original language)
- _Translations will be added here as they become available_

## Contributing Translations

Want to translate OptiPress into your language?

1. **Via WordPress.org** (Recommended):
   - Visit [translate.wordpress.org](https://translate.wordpress.org/)
   - Search for "OptiPress"
   - Select your language
   - Start translating!

2. **Via GitHub**:
   - Fork the repository
   - Create translation files
   - Submit a pull request
   - Include both `.po` and `.mo` files

3. **Direct Contribution**:
   - Email translations to: [translate@optipress.meddeb.me](mailto:translate@optipress.meddeb.me)
   - Include your name/URL for attribution

## Technical Details

### Text Domain

All OptiPress strings use the text domain: `optipress`

### Translation Functions Used

The plugin uses standard WordPress translation functions:
- `__( $text, 'optipress' )` - Returns translated string
- `_e( $text, 'optipress' )` - Echoes translated string
- `esc_html__( $text, 'optipress' )` - Returns escaped translated string
- `esc_html_e( $text, 'optipress' )` - Echoes escaped translated string
- `esc_attr__( $text, 'optipress' )` - Returns escaped translated string for attributes
- `_n( $single, $plural, $number, 'optipress' )` - Plural forms
- `_x( $text, $context, 'optipress' )` - Translation with context

### JavaScript Translations

JavaScript strings are localized via `wp_localize_script()` and passed to:
- `optipressAdmin.i18n` - Admin JavaScript strings
- `optipressAttachment.i18n` - Attachment edit screen strings

### Loading Translations

The plugin loads translations on the `init` hook (WordPress 6.7+ requirement):

```php
add_action( 'init', 'optipress_load_textdomain' );
```

## Translator Comments

The code includes translator comments to provide context:

```php
/* translators: %d: Image ID */
__( 'File not found for image ID %d', 'optipress' )
```

These comments help translators understand the context and meaning of placeholders.

## Testing Translations

To test translations locally:

1. Install translation files in this directory
2. Change WordPress language in Settings > General
3. Reload admin pages to see translations
4. Test with Loco Translate plugin for easier testing

## Support

Translation questions or issues?
- GitHub: [https://github.com/bmeddeb/OptiPress/issues](https://github.com/bmeddeb/OptiPress/issues)
- Homepage: [https://optipress.meddeb.me](https://optipress.meddeb.me)

---

**Thank you to all translators who help make OptiPress accessible to everyone!** 🌍
