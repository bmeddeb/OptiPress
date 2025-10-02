# OptiPress Library Organizer - Developer Documentation

**Version**: 0.7.0 (Phase 1 Complete)
**Status**: Foundation & Core Data Model ✅

## Overview

The OptiPress Library Organizer provides a comprehensive media management system for photographers, researchers, and content creators working with advanced image formats (RAW, TIFF, PSD). It uses a parent-child post structure to group original files with their web-optimized conversions.

## Architecture

### Custom Post Types

#### optipress_item (Parent)
- Represents a logical "image" entity
- Contains: title, description, thumbnail
- Supports: collections, tags, access levels, file types
- REST API enabled at `/wp-json/wp/v2/optipress_item`

#### optipress_file (Child)
- Represents individual file variants
- Linked to parent via `post_parent`
- Contains: file path, format, dimensions, EXIF data
- Hidden from admin menu
- REST API enabled at `/wp-json/wp/v2/optipress_file`

### Taxonomies

1. **optipress_collection** (hierarchical) - Folder/album structure
2. **optipress_tag** (non-hierarchical) - Flexible tagging
3. **optipress_access** (non-hierarchical) - Permission levels
4. **optipress_file_type** (non-hierarchical) - Format classification

### Database Tables

#### wp_optipress_downloads
Tracks download history for files:
- `file_id` - File post ID
- `item_id` - Item post ID
- `user_id` - Downloader (NULL for anonymous)
- `ip_address` - IP address
- `download_date` - Timestamp
- `file_size` - File size in bytes
- `download_method` - direct, shortcode, api

### File System Structure

```
wp-content/uploads/optipress/
└── collections/
    └── {collection-slug}/
        └── {item-id}/
            ├── original/
            │   └── IMG_5847.CR2
            ├── preview/
            │   └── preview.webp
            └── sizes/
                ├── thumb-300x300.webp
                ├── medium-1024x768.webp
                └── large-2048x1536.webp
```

Each item directory includes `.htaccess` for protection.

## Core Classes

### OptiPress_Organizer
**File**: `includes/organizer/class-organizer.php`

Bootstrap class that initializes the organizer system.

```php
// Get organizer instance
$organizer = optipress_organizer();

// Access managers
$items = $organizer->get_item_manager();
$files = $organizer->get_file_manager();
$collections = $organizer->get_collection_manager();
```

### OptiPress_Organizer_Item_Manager
**File**: `includes/organizer/class-item-manager.php`

Manages library items (optipress_item posts).

#### Methods

**Create Item**
```php
$item_id = $items->create_item( array(
    'title'       => 'Sunset at Beach',
    'description' => 'Beautiful sunset photograph',
    'collection_id' => 5,
    'tags'        => array( 'sunset', 'beach', 'landscape' ),
    'access_level' => 'public',
    'metadata'    => array( 'camera' => 'Canon EOS R5' ),
) );
```

**Get Item**
```php
$item = $items->get_item( 123 );
$details = $items->get_item_details( 123 ); // Full details with taxonomies
```

**Update Item**
```php
$items->update_item( 123, array(
    'title' => 'Updated Title',
    'collection_id' => 10,
) );
```

**Delete Item**
```php
$items->delete_item( 123, true ); // true = delete files
$items->trash_item( 123 ); // Soft delete
$items->restore_item( 123 ); // Restore from trash
```

**Query Items**
```php
$query = $items->query_items( array(
    'collection_id' => 5,
    'tag_id'        => 8,
    'search'        => 'sunset',
    'posts_per_page' => 20,
    'orderby'       => 'date',
    'order'         => 'DESC',
) );

foreach ( $query->posts as $item ) {
    echo $item->post_title;
}
```

**Count Items**
```php
$count = $items->count_items( array( 'collection_id' => 5 ) );
```

**Get Items by Collection**
```php
// With subcollections
$query = $items->get_items_by_collection( 5, true );
```

### OptiPress_Organizer_File_Manager
**File**: `includes/organizer/class-file-manager.php`

Manages file variants (optipress_file posts).

#### Methods

**Add File**
```php
$file_id = $files->add_file(
    123,                    // item_id
    '/path/to/file.cr2',   // file_path
    'original',            // variant_type
    array(                 // metadata
        'width'  => 8192,
        'height' => 5464,
        'exif_data' => array( 'camera' => 'Canon EOS R5' ),
    )
);
```

**Get File**
```php
$file = $files->get_file( 456 );
$details = $files->get_file_details( 456 ); // Full details
```

**Get Files by Item**
```php
$item_files = $files->get_files_by_item( 123 );
$files_details = $files->get_files_details_by_item( 123 );
```

**Get File by Variant Type**
```php
$original = $files->get_file_by_type( 123, 'original' );
$preview = $files->get_file_by_type( 123, 'preview' );
```

**Update File**
```php
$files->update_file( 456, array(
    'title' => 'Updated filename',
    'width' => 1920,
    'height' => 1080,
) );
```

**Delete File**
```php
$files->delete_file( 456, true ); // true = delete physical file
```

**Utility Methods**
```php
$path = $files->get_file_absolute_path( 456 );
$exists = $files->file_exists_on_disk( 456 );
$size = $files->get_file_size_human( 456 ); // "5.2 MB"
$files->increment_download_count( 456 );
```

### OptiPress_Organizer_File_System
**File**: `includes/organizer/class-file-system.php`

Manages file organization on disk.

#### Methods

```php
$file_system = new OptiPress_Organizer_File_System();

// Get/create item directory
$dir = $file_system->get_item_directory( 123, true );

// Get file path for variant
$path = $file_system->get_file_path( 123, 'original', 'file.cr2' );

// Organize file
$new_path = $file_system->organize_file( '/tmp/file.cr2', 123, 'original' );

// Delete item files
$file_system->delete_item_files( 123 );

// Check writability
if ( $file_system->is_writable() ) {
    // OK to proceed
}
```

### OptiPress_Organizer_Validator
**File**: `includes/organizer/class-validator.php`

Provides validation and sanitization helpers.

#### Methods

```php
// Validate item data
$result = OptiPress_Organizer_Validator::validate_item_data( $data );
if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
}

// Validate file data
$result = OptiPress_Organizer_Validator::validate_file_data(
    $item_id,
    $file_path,
    $variant_type
);

// Validate file size (default: 100MB max)
$result = OptiPress_Organizer_Validator::validate_file_size( $file_path );

// Sanitize data
$clean_data = OptiPress_Organizer_Validator::sanitize_item_data( $data );

// Check permissions
$result = OptiPress_Organizer_Validator::check_permission( 'edit_posts' );

// Validate nonce
$result = OptiPress_Organizer_Validator::validate_nonce( $nonce, 'action_name' );
```

## Action Hooks

The organizer provides several action hooks for extensibility:

```php
// Item hooks
do_action( 'optipress_organizer_item_created', $item_id, $data );
do_action( 'optipress_organizer_item_updated', $item_id, $data );
do_action( 'optipress_organizer_before_delete_item', $item_id, $delete_files );
do_action( 'optipress_organizer_item_deleted', $item_id );

// File hooks
do_action( 'optipress_organizer_file_added', $file_id, $item_id, $variant_type, $metadata );
do_action( 'optipress_organizer_file_updated', $file_id, $metadata );
do_action( 'optipress_organizer_before_delete_file', $file_id, $delete_physical );
do_action( 'optipress_organizer_file_deleted', $file_id );
```

## Filter Hooks

```php
// Modify query args for items
$query_args = apply_filters( 'optipress_organizer_query_items_args', $query_args, $args );
```

## Post Meta Keys

### Item Meta (`optipress_item`)
- `_optipress_display_file` - ID of primary display file
- `_optipress_metadata` - Serialized array of custom metadata
- `_optipress_view_count` - Number of views

### File Meta (`optipress_file`)
- `_optipress_file_path` - Relative path from ABSPATH
- `_optipress_file_size` - Size in bytes
- `_optipress_file_format` - File extension (cr2, webp, etc.)
- `_optipress_variant_type` - Variant type (original, preview, etc.)
- `_optipress_dimensions` - WIDTHxHEIGHT format
- `_optipress_conversion_settings` - Conversion settings used
- `_optipress_exif_data` - EXIF metadata
- `_optipress_download_count` - Number of downloads

## Testing

Run integration tests via WP-CLI:

```bash
wp eval-file includes/organizer/test-integration.php
```

Tests cover:
1. Item creation
2. File addition
3. Item querying
4. Item updating
5. Item deletion
6. File system operations

## Usage Examples

### Complete Workflow

```php
// 1. Create an item
$organizer = optipress_organizer();
$items = $organizer->get_item_manager();
$files = $organizer->get_file_manager();

$item_id = $items->create_item( array(
    'title' => 'RAW Image from Photoshoot',
    'description' => 'High-resolution image from client photoshoot',
) );

// 2. Add original file
$original_id = $files->add_file(
    $item_id,
    '/path/to/IMG_5847.CR2',
    'original',
    array( 'width' => 8192, 'height' => 5464 )
);

// 3. Add preview file
$preview_id = $files->add_file(
    $item_id,
    '/path/to/preview.webp',
    'preview',
    array( 'width' => 2048, 'height' => 1365 )
);

// 4. Set display file
$items->update_item( $item_id, array(
    'display_file_id' => $preview_id,
) );

// 5. Query items
$query = $items->query_items( array(
    'posts_per_page' => 10,
    'orderby' => 'date',
    'order' => 'DESC',
) );

// 6. Get item details
$details = $items->get_item_details( $item_id );
echo "Files: " . $details['file_count'];

// 7. Get all files for item
$item_files = $files->get_files_details_by_item( $item_id );
foreach ( $item_files as $file ) {
    echo $file['variant_type'] . ': ' . $file['file_size_human'];
}
```

## Uninstallation

When the plugin is deleted (not just deactivated), `uninstall.php` will:
1. Delete all `optipress_item` and `optipress_file` posts
2. Delete all organizer taxonomies and terms
3. Drop the `wp_optipress_downloads` table
4. Delete all files in `uploads/optipress/` directory
5. Clear all organizer metadata

## Phase 1 Status

✅ **Complete** (Week 1-4)

- Custom post types (items, files)
- Taxonomies (collections, tags, access, file types)
- Database tables (downloads)
- File system organization
- Item Manager (CRUD, query, trash/restore)
- File Manager (CRUD, variants, utilities)
- Validator (validation, sanitization)
- Integration tests
- Uninstall cleanup

## Next Phases

**Phase 2**: Upload Integration & Migration (Weeks 5-7)
**Phase 3**: Collections & Organization UI (Weeks 8-12)
**Phase 4**: Access Control & Downloads (Weeks 13-15)
**Phase 5**: REST API & Frontend Integration (Weeks 16-18)
**Phase 6**: Shortcodes & Frontend Display (Weeks 19-21)

---

For implementation phases and detailed specifications, see:
- `docs/LIBRARY-ORGANIZER-PHASES.md`
- `docs/LIBRARY-ORGANIZER-ARCHITECTURE.md`
- `docs/LIBRARY-ORGANIZER-FEASIBILITY.md`
