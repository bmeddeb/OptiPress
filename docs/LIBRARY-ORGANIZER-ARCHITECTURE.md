# OptiPress Library Organizer - System Architecture

**Version**: 1.0
**Date**: 2025-10-01
**Status**: Planning Phase

## Executive Summary

The OptiPress Library Organizer will provide comprehensive media management for photographers, researchers, and content creators who work with advanced image formats (RAW, TIFF, PSD). The system will group original files with their web-optimized conversions, offer hierarchical organization, access control, and download tracking.

## Design Decisions

### Architecture Pattern: Parent-Child Posts

**Decision**: Use **Download Monitor's proven pattern** - Parent-Child Custom Post Types

**Rationale**:
- ✅ Treats each file as a first-class entity
- ✅ Enables per-file download tracking
- ✅ Easy to add new conversions without schema changes
- ✅ Clean separation of concerns
- ✅ Leverages WordPress's built-in query system
- ✅ REST API support via `show_in_rest`

**Custom Post Types**:

1. **`optipress_item`** (Parent) - The logical "image" entity
   - Title, description, featured image
   - Taxonomies: Collections (folders), Tags, Access Levels
   - Post meta: display preferences, metadata

2. **`optipress_file`** (Child) - Individual file variants
   - Linked to parent via `post_parent`
   - Stores: file path, format, dimensions, file size, version label
   - Post meta: EXIF data, conversion settings
   - Hidden from main admin menu (accessed via parent)

**Example Structure**:
```
OptiPress Item #100 "Sunset at Beach"
├── OptiPress File #101 (original) - IMG_5847.CR2 [5.2 MB]
├── OptiPress File #102 (preview) - preview.webp [181 KB]
├── OptiPress File #103 (thumbnail) - thumb-300x300.webp [15 KB]
├── OptiPress File #104 (medium) - medium-1024x768.webp [85 KB]
└── OptiPress File #105 (large) - large-2048x1536.webp [220 KB]
```

## Database Schema

### Core Tables (WordPress Native)

#### wp_posts
```
OptiPress Item Posts:
- ID (primary key)
- post_type = 'optipress_item'
- post_title (image title)
- post_content (description)
- post_status (publish, private, draft)
- post_author (user who uploaded)
- post_date (upload date)
- post_parent = 0

OptiPress File Posts:
- ID (primary key)
- post_type = 'optipress_file'
- post_title (filename)
- post_content (file notes)
- post_status (inherit)
- post_author (inherited from parent)
- post_parent (links to optipress_item ID)
- post_mime_type (image/jpeg, image/tiff, etc.)
```

#### wp_postmeta
```
For optipress_item:
- _optipress_display_file (ID of primary display file)
- _optipress_featured_image (thumbnail for grid view)
- _optipress_original_dimensions (width x height)
- _optipress_upload_source (camera, scanner, software)
- _optipress_view_count (number of views)
- _optipress_metadata (serialized EXIF/technical data)

For optipress_file:
- _optipress_file_path (relative path in uploads/)
- _optipress_file_size (bytes)
- _optipress_file_format (jpeg, webp, avif, cr2, tiff, etc.)
- _optipress_dimensions (width x height)
- _optipress_variant_type (original, preview, thumbnail, size_name)
- _optipress_download_count (incremented on each download)
- _optipress_conversion_settings (quality, engine used)
- _optipress_exif_data (serialized camera/technical metadata)
```

#### wp_term_relationships + wp_term_taxonomy + wp_terms
```
Taxonomies:

1. optipress_collection (hierarchical) - Folder structure
   Example terms: "2025 Portfolio", "Client Work", "Personal Projects"

2. optipress_tag (non-hierarchical) - Flexible tagging
   Example terms: "landscape", "portrait", "HDR", "microscopy"

3. optipress_access (non-hierarchical) - Access control
   Terms: "public", "members_only", "subscribers", "administrators"

4. optipress_file_type (non-hierarchical) - Format classification
   Terms: "raw", "tiff", "psd", "preview", "thumbnail"
```

### Custom Tables

#### wp_optipress_downloads
```sql
CREATE TABLE wp_optipress_downloads (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_id BIGINT(20) UNSIGNED NOT NULL,
  item_id BIGINT(20) UNSIGNED NOT NULL,
  user_id BIGINT(20) UNSIGNED DEFAULT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent TEXT,
  download_date DATETIME NOT NULL,
  file_size BIGINT(20) UNSIGNED,
  download_method VARCHAR(50), -- 'direct', 'shortcode', 'api'
  referrer TEXT,
  INDEX file_id (file_id),
  INDEX item_id (item_id),
  INDEX user_id (user_id),
  INDEX download_date (download_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose**: Detailed download tracking without bloating postmeta

#### wp_optipress_file_versions (Optional - Future Phase)
```sql
CREATE TABLE wp_optipress_file_versions (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_id BIGINT(20) UNSIGNED NOT NULL,
  version_number VARCHAR(20) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_size BIGINT(20) UNSIGNED,
  created_date DATETIME NOT NULL,
  created_by BIGINT(20) UNSIGNED,
  is_active TINYINT(1) DEFAULT 1,
  version_notes TEXT,
  INDEX file_id (file_id),
  INDEX version_number (version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose**: Archive old versions of files (e.g., updated RAW file)

## File System Structure

### Directory Organization
```
wp-content/uploads/optipress/
├── collections/
│   ├── {collection-slug}/
│   │   ├── {item-id}/
│   │   │   ├── original/
│   │   │   │   └── IMG_5847.CR2
│   │   │   ├── preview/
│   │   │   │   └── preview.webp
│   │   │   └── sizes/
│   │   │       ├── thumb-300x300.webp
│   │   │       ├── medium-1024x768.webp
│   │   │       └── large-2048x1536.webp
├── temp/
│   └── processing/
└── cache/
    └── thumbnails/
```

**Benefits**:
- Logical organization mirrors user's mental model
- Easy backup of entire collections
- Prevents uploads dir clutter
- Supports CDN integration
- Easy to find files manually if needed

### File Naming Convention
```
Format: {type}-{dimensions}.{ext}
Examples:
- original.cr2
- preview.webp
- thumb-300x300.webp
- medium-1024x768.webp
- large-2048x1536.webp
```

## Core Components

### 1. Custom Post Types Registration

**File**: `includes/class-organizer-post-types.php`

```php
class Organizer_Post_Types {
    // Register optipress_item CPT
    // - Hierarchical: false
    // - Supports: title, editor, thumbnail, custom-fields
    // - show_in_rest: true
    // - Capabilities: read, edit_posts, etc.

    // Register optipress_file CPT
    // - Hierarchical: false (uses post_parent instead)
    // - Supports: title, custom-fields
    // - show_in_rest: true
    // - show_ui: false (hidden from admin menu)
    // - publicly_queryable: false (not directly accessible)
}
```

### 2. Taxonomy Registration

**File**: `includes/class-organizer-taxonomies.php`

```php
class Organizer_Taxonomies {
    // Register optipress_collection (hierarchical)
    // Register optipress_tag (non-hierarchical)
    // Register optipress_access (non-hierarchical)
    // Register optipress_file_type (non-hierarchical)
}
```

### 3. Item Manager

**File**: `includes/organizer/class-item-manager.php`

```php
class Item_Manager {
    // create_item($title, $description, $collection_id)
    // update_item($item_id, $data)
    // delete_item($item_id, $delete_files = false)
    // get_item($item_id)
    // get_items($args) // Query with filters
    // move_to_collection($item_id, $collection_id)
    // set_display_file($item_id, $file_id)
}
```

### 4. File Manager

**File**: `includes/organizer/class-file-manager.php`

```php
class File_Manager {
    // add_file($item_id, $file_path, $variant_type, $metadata)
    // update_file($file_id, $metadata)
    // delete_file($file_id, $delete_physical = true)
    // get_file($file_id)
    // get_files_by_item($item_id)
    // get_file_by_type($item_id, $variant_type) // e.g., 'original'
    // generate_secure_download_url($file_id, $expiry)
}
```

### 5. Collection Manager

**File**: `includes/organizer/class-collection-manager.php`

```php
class Collection_Manager {
    // create_collection($name, $parent_id = 0)
    // update_collection($collection_id, $data)
    // delete_collection($collection_id, $delete_items = false)
    // get_collection($collection_id)
    // get_collections_tree()
    // get_items_in_collection($collection_id, $recursive = false)
    // move_collection($collection_id, $new_parent_id)
}
```

### 6. Access Control

**File**: `includes/organizer/class-access-control.php`

```php
class Access_Control {
    // can_view_item($item_id, $user_id)
    // can_download_file($file_id, $user_id)
    // set_item_access($item_id, $access_level) // 'public', 'members', etc.
    // get_access_level($item_id)
    // check_collection_access($collection_id, $user_id)
}
```

### 7. Download Handler

**File**: `includes/organizer/class-download-handler.php`

```php
class Download_Handler {
    // serve_file($file_id, $user_id)
    // log_download($file_id, $item_id, $user_id, $ip, $user_agent)
    // get_download_stats($file_id)
    // generate_download_token($file_id, $expiry)
    // validate_token($token)
}
```

### 8. Metadata Extractor

**File**: `includes/organizer/class-metadata-extractor.php`

```php
class Metadata_Extractor {
    // extract_exif($file_path)
    // extract_iptc($file_path)
    // extract_dimensions($file_path)
    // get_file_info($file_path) // size, mime, etc.
    // store_metadata($file_id, $metadata)
}
```

### 9. Admin UI Manager

**File**: `includes/organizer/class-admin-ui.php`

```php
class Admin_UI {
    // add_menu_page()
    // render_library_page()
    // render_collections_page()
    // render_item_edit_page($item_id)
    // enqueue_assets()
    // ajax_handlers()
}
```

### 10. REST API Endpoints

**File**: `includes/organizer/class-rest-api.php`

```php
class REST_API {
    // GET    /wp-json/optipress/v1/items
    // POST   /wp-json/optipress/v1/items
    // GET    /wp-json/optipress/v1/items/{id}
    // PATCH  /wp-json/optipress/v1/items/{id}
    // DELETE /wp-json/optipress/v1/items/{id}

    // GET    /wp-json/optipress/v1/items/{id}/files
    // POST   /wp-json/optipress/v1/items/{id}/files
    // DELETE /wp-json/optipress/v1/files/{id}

    // GET    /wp-json/optipress/v1/collections
    // POST   /wp-json/optipress/v1/collections
    // GET    /wp-json/optipress/v1/collections/{id}/items

    // POST   /wp-json/optipress/v1/files/{id}/download
    // GET    /wp-json/optipress/v1/files/{id}/stats
}
```

## UI/UX Design

### Library View (Grid/List)

**Layout Options**:
- Grid view (thumbnails with metadata overlay)
- List view (table with sortable columns)
- Gallery view (masonry layout)

**Features**:
- Drag-and-drop to collections
- Bulk actions (move, delete, set access)
- Quick edit inline
- Search and filters (by collection, tag, format, date)
- Sort by: date, title, size, downloads

### Collections Sidebar

```
Collections
├── 📁 2025 Portfolio
│   ├── 📁 Landscapes
│   └── 📁 Portraits
├── 📁 Client Work
│   ├── 📁 Project Alpha
│   └── 📁 Project Beta
└── 📁 Personal Projects

[+ New Collection]
```

**Features**:
- Drag-and-drop to reorganize
- Context menu (rename, delete, set access)
- Collection count badges
- Collapsible tree

### Item Edit Screen

**Sections**:
1. **Preview** - Display current preview image
2. **Files** - List all variants with actions
3. **Details** - Title, description, metadata
4. **Collections & Tags** - Assign to folders/tags
5. **Access Control** - Set download permissions
6. **Statistics** - View/download counts

**File List**:
```
Files (5)
┌────────────────────────────────────────────────────────┐
│ 📄 original.cr2 (Original)                            │
│    5.2 MB • Canon EOS R5 • 8192x5464                  │
│    [View] [Download] [Set as Display] [Delete]        │
├────────────────────────────────────────────────────────┤
│ 🖼️ preview.webp (Preview) ⭐ Display File              │
│    181 KB • 2048x1365 • WebP                          │
│    [View] [Download] [Set as Display] [Rebuild]       │
├────────────────────────────────────────────────────────┤
│ 📐 thumb-300x300.webp (Thumbnail)                     │
│    15 KB • 300x300 • WebP                             │
│    [View] [Download] [Rebuild]                        │
└────────────────────────────────────────────────────────┘

[+ Add File Variant]
```

## Integration with Existing OptiPress

### Upload Flow Enhancement

**Current**: Upload RAW → Advanced_Formats creates preview → Thumbnailer creates sizes

**Enhanced**:
1. User uploads RAW file
2. Create `optipress_item` post (title from filename)
3. Create `optipress_file` for original (variant_type='original')
4. Advanced_Formats creates preview
5. Create `optipress_file` for preview (variant_type='preview')
6. Thumbnailer creates sizes
7. Create `optipress_file` for each size (variant_type='thumb', 'medium', etc.)
8. Auto-assign to collection (if specified in upload dialog)
9. Extract and store metadata

### Attachment Meta Box Integration

**Current OptiPress Meta Box** - Shows for all images

**Enhanced**:
- If image has `optipress_item` parent → Show "Managed by Library Organizer" notice
- Add button: "Open in Library Organizer"
- Link to edit item page
- Keep existing functionality (rebuild, convert) operational

### Shortcode System

**Deferred to Phase 2** (as per previous decision)

Will design comprehensive shortcode system after organizer is built:
- `[optipress_download id="123"]`
- `[optipress_gallery collection="landscapes"]`
- `[optipress_item id="123" show_download="true"]`

## Security Considerations

### File Access Protection

1. **Direct Access Prevention**
   - Store files outside webroot OR use .htaccess rules
   - All downloads go through PHP handler
   - Validate permissions before serving

2. **Secure URLs**
   - Generate time-limited download tokens
   - One-time use links (optional)
   - IP validation (optional)

3. **Upload Validation**
   - Check file extensions
   - Validate MIME types
   - Size limits
   - Virus scanning integration point (future)

### Permission Checks

```php
// Check hierarchy
if (! current_user_can('manage_options')) {
    if (! $this->can_download_file($file_id, $user_id)) {
        wp_die('Access denied');
    }
}
```

**Capability Mapping**:
- `optipress_manage_items` - Create/edit/delete items
- `optipress_manage_collections` - Organize collections
- `optipress_download_originals` - Download original files
- `optipress_view_stats` - View download statistics

## Performance Optimization

### Query Optimization

1. **Indexed Queries**
   - Custom table indexes on frequently queried columns
   - Transient caching for collection trees
   - Object caching for item/file data

2. **Lazy Loading**
   - Load file details only when item is opened
   - Pagination for large libraries
   - Infinite scroll for grid view

### File Serving

1. **X-Sendfile Support**
   - Use Nginx/Apache X-Sendfile for large file serving
   - Offload file delivery from PHP

2. **CDN Integration**
   - Store previews/thumbnails on CDN
   - Keep originals on server (protected)

3. **Thumbnail Cache**
   - Pre-generate thumbnails on upload
   - Use WordPress image sizes system
   - Separate cache directory for admin thumbnails

## Migration & Compatibility

### Upgrade from Current OptiPress

**Scenario**: Existing OptiPress users have:
- Original files stored via Advanced_Formats
- Metadata: `_optipress_converted`, `original_file`
- Standard WP attachments

**Migration Script**:
1. Find all attachments with `original_file` meta
2. For each:
   - Create `optipress_item` post
   - Create `optipress_file` for original (from `original_file` path)
   - Create `optipress_file` for current attachment (preview)
   - Link via `post_parent`
   - Copy metadata
   - Auto-assign to "Migrated Items" collection
3. Preserve all existing attachments (don't break site)
4. Provide rollback mechanism

### Backward Compatibility

- Keep existing meta boxes functional
- Don't break existing workflows
- Organizer is opt-in (can be disabled)
- Existing shortcodes keep working

## Testing Strategy

### Unit Tests
- Item CRUD operations
- File CRUD operations
- Access control logic
- Download token generation
- Metadata extraction

### Integration Tests
- Upload flow end-to-end
- Download flow with permissions
- REST API endpoints
- Collection tree operations

### Performance Tests
- Library with 10,000 items
- Query response times
- File serving speed
- Bulk operations

## Documentation Requirements

### User Documentation
- Getting started guide
- Organizing images into collections
- Setting access controls
- Using tags and metadata
- Downloading files
- Understanding statistics

### Developer Documentation
- REST API reference
- Hook & filter reference
- CPT structure
- Extending the organizer
- Creating custom file handlers

## Phase Implementation Plan

See `LIBRARY-ORGANIZER-PHASES.md` for detailed implementation phases.

---

**Next Steps**:
1. Review and approve architecture
2. Create detailed Phase 1 specification
3. Design database migration strategy
4. Build proof-of-concept for core components
5. User feedback on UI mockups
