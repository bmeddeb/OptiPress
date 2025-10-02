# OptiPress Uninstall Cleanup Documentation

**File**: `uninstall.php`
**Trigger**: When plugin is **deleted** (not just deactivated) from wp-admin
**Purpose**: Complete removal of all OptiPress data from WordPress installation

---

## What Gets Deleted

### 1. WordPress Options ✅
```php
delete_option( 'optipress_options' );
delete_option( 'optipress_security_log' );
delete_option( 'optipress_organizer_db_version' );
```

**Removed:**
- Plugin settings (engine, format, quality, etc.)
- SVG security log
- Organizer database schema version

---

### 2. Post Metadata ✅
```sql
DELETE FROM wp_postmeta WHERE meta_key LIKE '_optipress_%'
```

**Removes ALL meta keys starting with `_optipress_`:**

**Attachment Meta (Image Converter):**
- `_optipress_converted` - Conversion status flag
- `_optipress_format` - Output format (webp/avif)
- `_optipress_engine` - Engine used (gd/imagick)
- `_optipress_converted_sizes` - Which sizes were converted
- `_optipress_conversion_date` - When conversion happened
- `_optipress_original_size` - Original file size in bytes
- `_optipress_converted_size` - Converted file size in bytes
- `_optipress_bytes_saved` - Bytes saved by conversion
- `_optipress_percent_saved` - Percentage saved
- `_optipress_errors` - Conversion error messages

**Attachment Meta (Organizer):**
- `_optipress_is_advanced_format` - Advanced format detection flag
- `_optipress_organizer_item_id` - Link to organizer item

**Item Meta (optipress_item CPT):**
- `_optipress_metadata` - Custom item metadata
- `_optipress_display_file` - Primary display file ID
- `_optipress_view_count` - View counter
- `_optipress_size_files` - Array of size file IDs

**File Meta (optipress_file CPT):**
- `_optipress_file_path` - Relative file path
- `_optipress_file_size` - File size in bytes
- `_optipress_file_format` - File extension
- `_optipress_variant_type` - Variant type (original/preview/size)
- `_optipress_dimensions` - Width x Height
- `_optipress_conversion_settings` - Conversion settings used
- `_optipress_exif_data` - Extracted EXIF metadata
- `_optipress_iptc_data` - Extracted IPTC metadata
- `_optipress_download_count` - Download counter
- `_optipress_file_info` - Complete file information
- `_optipress_complete_metadata` - All extracted metadata
- `_optipress_size_name` - WordPress size name (thumbnail/medium/etc.)

---

### 3. Transients ✅
```sql
DELETE FROM wp_options
WHERE option_name LIKE '_transient_optipress_%'
   OR option_name LIKE '_transient_timeout_optipress_%'
```

**Removed:**
- Any temporary cached data
- Test transients (optipress_test_item_id, optipress_test_file_id)

---

### 4. Custom Post Types ✅

**optipress_item (Library Items):**
```php
// Force delete all items (bypass trash)
wp_delete_post( $item_id, true );
```
- Deletes all library item posts
- Removes post content, title, metadata
- Deletes taxonomy relationships
- Bypasses trash for permanent deletion

**optipress_file (File Variants):**
```php
// Force delete all files (bypass trash)
wp_delete_post( $file_id, true );
```
- Deletes all file variant posts
- Removes all file metadata
- Deletes parent-child relationships

**Note:** Post metadata is deleted BEFORE posts to avoid orphaned meta rows

---

### 5. Custom Taxonomies ✅

**Taxonomies Removed:**
1. `optipress_collection` - Hierarchical collections/folders
2. `optipress_tag` - Non-hierarchical tags
3. `optipress_access` - Access level classification
4. `optipress_file_type` - File type classification

**Process:**
1. Get all terms for each taxonomy
2. Delete each term using `wp_delete_term()`
3. Delete taxonomy records from `wp_term_taxonomy` table

---

### 6. Database Tables ✅

**Dropped Tables:**
- `wp_optipress_downloads` - Download tracking table

**Columns (for reference):**
- `id` - Primary key
- `file_id` - File post ID
- `item_id` - Item post ID
- `user_id` - Downloader (NULL for anonymous)
- `ip_address` - Download IP
- `download_date` - Timestamp
- `file_size` - File size in bytes
- `download_method` - direct/shortcode/api

**Method:**
```php
$database->drop_tables(); // DROP TABLE IF EXISTS
```

---

### 7. Physical Files ✅

**Directory Deleted:**
```
{uploads}/optipress/
```

**What's Inside:**
```
optipress/
└── collections/
    ├── {collection-slug}/
    │   └── {item-id}/
    │       ├── original/      (RAW, TIFF, PSD files)
    │       ├── preview/       (WebP/AVIF previews)
    │       └── sizes/         (Generated thumbnails)
    └── uncategorized/
        └── {item-id}/
            └── ...
```

**Safety Checks:**
1. Verifies directory path matches expected: `{uploads}/optipress/`
2. Only deletes if path exactly matches
3. Uses try-catch to prevent fatal errors
4. Uses `@` suppression for file operations to handle locked files

**Deletion Method:**
- Recursive iterator (CHILD_FIRST)
- Deletes files first, then directories
- Bottom-up deletion (deepest files first)

---

### 8. Cache Clearing ✅
```php
wp_cache_flush();
```

Clears all WordPress object cache to remove any cached organizer data.

---

## What Does NOT Get Deleted

### Preserved Data:

1. **Original Uploaded Images** ✅
   - Standard WordPress attachments remain
   - Original JPG/PNG files in uploads folder
   - WordPress attachment metadata preserved

2. **WordPress Core Data** ✅
   - Users, posts, pages, comments
   - Other plugin data
   - WordPress settings

3. **Converted Files (Optional)** ⚠️
   - WebP/AVIF files created by Image Converter are NOT deleted by default
   - There is commented-out code in `uninstall.php` to delete them if desired
   - To enable: Uncomment lines 54-110 in `uninstall.php`

---

## Cleanup Order

**Order matters for referential integrity:**

1. **Options** - Delete plugin settings first
2. **Post Meta** - Remove metadata before posts (avoid orphans)
3. **Transients** - Clear temporary data
4. **CPT Posts** - Delete items and files (removes taxonomy relationships)
5. **Taxonomies** - Remove custom taxonomies and terms
6. **Database Tables** - Drop custom tables
7. **Physical Files** - Delete uploads/optipress/ directory
8. **Cache** - Clear WordPress object cache

---

## Testing Uninstall

### Manual Test:
1. Activate plugin
2. Upload some RAW/TIFF images (if organizer enabled)
3. Convert some JPG images to WebP
4. Check database for optipress_item posts
5. **Deactivate** plugin (data should persist)
6. **Delete** plugin from Plugins page
7. Verify cleanup:

```sql
-- Check for remaining data
SELECT * FROM wp_options WHERE option_name LIKE '%optipress%';
SELECT * FROM wp_postmeta WHERE meta_key LIKE '_optipress_%';
SELECT * FROM wp_posts WHERE post_type LIKE 'optipress_%';
SHOW TABLES LIKE '%optipress%';
```

```bash
# Check for remaining files
ls -la wp-content/uploads/optipress/
```

All should return empty/no results.

---

## Rollback / Undo

**WARNING:** Uninstall is **PERMANENT**. There is no undo.

**Before Uninstall:**
- Database backup: `mysqldump wordpress > backup.sql`
- File backup: `tar -czf optipress-backup.tar.gz wp-content/uploads/optipress/`

**To Restore:**
```sql
mysql wordpress < backup.sql
```
```bash
tar -xzf optipress-backup.tar.gz -C wp-content/uploads/
```

---

## Known Issues / Limitations

### 1. Converted Files Not Deleted by Default
**Reason:** User may want to keep WebP/AVIF files even after uninstall
**Solution:** Uncomment optional code block in `uninstall.php` (lines 54-110)

### 2. File Locks on Windows
**Issue:** Open files cannot be deleted
**Solution:** Suppressed errors with `@` - uninstall continues anyway

### 3. Large File Count
**Issue:** Many files (10,000+) may cause timeout
**Solution:** Increase PHP `max_execution_time` before uninstall

---

## Security Considerations

### Safe Practices:
✅ Uses `WP_UNINSTALL_PLUGIN` constant check
✅ Direct database queries use `$wpdb->prepare()` for SQL injection prevention
✅ Path verification before file deletion
✅ Error suppression prevents information leakage

### Risks Mitigated:
- Accidental directory deletion (path verification)
- SQL injection (prepared statements)
- PHP fatal errors during uninstall (try-catch, @ suppression)

---

## Multisite Considerations

**Current Implementation:** Single-site only

**For Multisite:**
- Would need to loop through all sites
- Delete data from each site's tables
- Handle uploads directory per site

**Not Yet Implemented** - Single site cleanup only

---

## Performance Notes

**Expected Duration:**
- Small site (< 100 items): 1-2 seconds
- Medium site (100-1000 items): 5-10 seconds
- Large site (1000+ items): 30-60 seconds

**Optimization:**
- Wildcard postmeta deletion (1 query vs N queries)
- Bulk post deletion via `wp_delete_post()`
- RecursiveIterator for file deletion

---

## Verification Checklist

After uninstall, verify:

- [ ] `wp_options`: No `optipress_options`, `optipress_security_log`, `optipress_organizer_db_version`
- [ ] `wp_postmeta`: No rows with `meta_key LIKE '_optipress_%'`
- [ ] `wp_posts`: No posts with `post_type = 'optipress_item'` or `'optipress_file'`
- [ ] `wp_term_taxonomy`: No rows with `taxonomy` = `optipress_*`
- [ ] Database: No `wp_optipress_downloads` table
- [ ] Filesystem: No `wp-content/uploads/optipress/` directory
- [ ] Transients: No `_transient_optipress_*` options

---

**Last Updated:** 2025-10-01
**Version:** 0.6.5
**Maintainer:** Ben Meddeb
