# Code Review TODO

## High Priority — PHP
- Content filtering: Replace regex-based `<img>` rewriting in `includes/class-content-filter.php` with `WP_HTML_Tag_Processor` (WP 6.2+) or `DOMDocument` fallback for safer attribute edits and to avoid breaking markup (self-closing tags, attributes order, lazy-loading).
- File I/O hot path: Add in-request static cache for `file_exists_from_url()` to avoid repeated filesystem checks per `<img>`/`srcset` on large pages. Consider a transient cache by month directory for front-end requests.
- Uninstall cleanup: Extend `uninstall.php` to remove all `_optipress_*` meta (`_converted_sizes`, `_conversion_date`, `_original_size`, `_converted_size`, `_bytes_saved`, `_percent_saved`, `_errors`) and delete `optipress_security_log` option.
- Engine limits: Make 10MB cutoff and 25MP dimension checks filterable, e.g., `apply_filters( 'optipress_max_filesize_bytes', 10*MB_IN_BYTES )`, in both GD and Imagick engines.
- Content negotiation: In `Content_Filter::get_browser_supported_format()`, prefer configured format when supported, else gracefully fall back (AVIF→WebP) and consider Safari/WebKit quirks; add a filter `optipress_client_format` for proxy/CDN scenarios.

## High Priority — JS
- Media modal integration: `src/js/svg-preview.js` overrides `wp.media.view.Attachment.Details`. Replace with event-based hooks or subclassing that doesn’t clobber core behavior or other plugins.
- DOMPurify policy: Revisit `FORBID_ATTR` to avoid blocking legitimate SVG `xlink:href`/`href` when sanitized; prefer an allowlist via DOMPurify hooks mirroring server rules to reduce UX mismatch.

## Medium Priority — PHP
- Duplicated delivery paths: `Image_Converter` filters (`wp_get_attachment_url/srcset`) and `Content_Filter` both rewrite URLs. Define precedence or guard against double processing; document in settings help.
- Batch processing: Add time/memory guards and progress persistence; expose WP-CLI commands for batch convert/revert and SVG sanitize to avoid AJAX timeouts.
- I18n audit: Ensure all admin strings in views under `admin/views/*.php` are wrapped with `__()` and escaped with `esc_html__`/`esc_attr__` when output.
- Security log size: Cap `optipress_security_log` by count (already 100) and age; add a scheduled cleanup.

## Medium Priority — JS
- Linting/formatting: Add ESLint + Prettier with WP config; run on `src/js` and any authored files in `admin/js`.
- Build ergonomics: Add `--sourcemap` and explicit targets to esbuild; add `npm run typecheck` if TypeScript is introduced later.
- Nonce handling: Centralize `optipressAdmin.nonce` usage and add automatic retry on 403 with nonce refresh where possible.

## Low Priority — PHP
- Coding standards: Add PHPCS with WordPress ruleset; sample: `vendor/bin/phpcs --standard=WordPress --ignore=vendor,dist,node_modules .` and fix with `phpcbf`.
- Logging: Prefer `error_log` via `wp_debug` is fine; consider a `WP_Logging`-style helper with log levels and `do_action( 'optipress_log', ... )` for integrations.
- Architecture: Consider DI-friendly singletons for easier unit testing; expose interfaces for content filters.

## Low Priority — JS
- Admin UX polish: Debounce batch status refresh; add cancel controls; surface per-error details collapsible in batch UI.
- Module structure: Move ad-hoc `admin/js/*.js` sources into `src/js/` and bundle consistently.

## Quick Checks (Commands)
- PHPCS: `composer require --dev wp-coding-standards/wpcs && vendor/bin/phpcs --standard=WordPress .`
- ESLint: `npm i -D eslint @wordpress/eslint-plugin prettier && npx eslint src/js admin/js --ext .js`
- Build: `npm ci && npm run build`; Package: `./publish.sh 0.4.1`
