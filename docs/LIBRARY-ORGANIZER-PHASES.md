# OptiPress Library Organizer - Implementation Phases

**Version**: 1.0
**Date**: 2025-10-01

## Overview

This document outlines the phased approach to implementing the OptiPress Library Organizer. Each phase builds upon the previous, allowing for incremental development and user feedback.

---

## Phase 1: Foundation & Core Data Model
**Duration**: 2-3 weeks
**Priority**: Critical
**Status**: Planning

### Goals
- Establish database schema
- Create core CPTs and taxonomies
- Build basic CRUD operations
- Implement file organization on disk
- update uninstall.php for the new scheme

### Deliverables

#### 1.1 Custom Post Types
**Files**: `includes/organizer/class-post-types.php`

- [ ] Register `optipress_item` CPT
  - Title, content, thumbnail support
  - REST API enabled
  - Custom capabilities

- [ ] Register `optipress_file` CPT
  - Child post structure
  - Hidden from admin menu
  - REST API enabled

**Acceptance Criteria**:
- CPTs registered on plugin activation
- Appear in WordPress admin (item only)
- REST endpoints accessible: `/wp-json/wp/v2/optipress_item`

#### 1.2 Taxonomies
**Files**: `includes/organizer/class-taxonomies.php`

- [ ] `optipress_collection` (hierarchical)
- [ ] `optipress_tag` (non-hierarchical)
- [ ] `optipress_access` (non-hierarchical)
- [ ] `optipress_file_type` (non-hierarchical)

**Acceptance Criteria**:
- All taxonomies registered
- Show in REST API
- Assignable to optipress_item

#### 1.3 Database Tables
**Files**: `includes/organizer/class-database.php`

- [ ] Create `wp_optipress_downloads` table
- [ ] Add indexes for performance
- [ ] Version tracking for schema updates
- [ ] Activation/deactivation hooks

```php
class Database {
    public function create_tables()
    public function update_schema($from_version, $to_version)
    public function drop_tables() // Uninstall only
}
```

**Acceptance Criteria**:
- Tables created on plugin activation
- Indexes applied
- No errors on repeated activation

#### 1.4 Core Managers
**Files**: `includes/organizer/class-item-manager.php`, `class-file-manager.php`

##### Item_Manager
```php
- [ ] create_item($data)
- [ ] get_item($id)
- [ ] update_item($id, $data)
- [ ] delete_item($id, $options)
- [ ] query_items($args)
```

##### File_Manager
```php
- [ ] add_file($item_id, $file_data)
- [ ] get_file($id)
- [ ] get_files_by_item($item_id)
- [ ] update_file($id, $data)
- [ ] delete_file($id, $delete_physical)
- [ ] move_file_to_directory($file_id, $new_path)
```

**Acceptance Criteria**:
- All CRUD operations functional
- Proper error handling
- Unit tests pass
- Post parent relationships maintained

#### 1.5 File System Organization
**Files**: `includes/organizer/class-file-system.php`

- [ ] Create uploads/optipress/ directory structure
- [ ] Implement file naming convention
- [ ] Handle file moves/copies
- [ ] Clean up orphaned files

```php
class File_System {
    public function get_item_directory($item_id, $create = true)
    public function get_file_path($item_id, $variant_type, $filename)
    public function organize_file($source_path, $item_id, $variant_type)
    public function delete_item_files($item_id)
}
```

**Acceptance Criteria**:
- Directory structure created on first use
- Files organized by item/variant
- No permission errors
- Cleanup works correctly

### Testing Phase 1
- [ ] Unit tests for all managers
- [ ] Integration test: Create item → Add files → Query → Delete
- [ ] Test CPT queries via REST API
- [ ] Test file system operations

### Documentation Phase 1
- [ ] Code documentation (PHPDoc)
- [ ] Developer guide: Using Item_Manager and File_Manager
- [ ] Database schema diagram

---

## Phase 2: Upload Integration & Migration
**Duration**: 2 weeks
**Priority**: High
**Status**: Not Started

### Goals
- Integrate organizer with existing upload flow
- Migrate existing OptiPress files
- Maintain backward compatibility

### Deliverables

#### 2.1 Upload Flow Integration
**Files**: `includes/organizer/class-upload-handler.php`

Hook into existing upload process:
- [ ] After Advanced_Formats creates preview
- [ ] Create optipress_item post
- [ ] Create optipress_file for original
- [ ] Create optipress_file for preview
- [ ] Create optipress_files for generated sizes
- [ ] Extract and store metadata

```php
class Upload_Handler {
    public function handle_advanced_format_upload($attachment_id)
    public function handle_standard_upload($attachment_id)
    public function create_item_from_attachment($attachment_id)
}
```

**Integration Points**:
- Hook: `wp_generate_attachment_metadata` (after Advanced_Formats)
- Hook: `add_attachment` (new uploads)

**Acceptance Criteria**:
- Uploading RAW creates item + files automatically
- Existing workflows not disrupted
- Can disable organizer via setting

#### 2.2 Migration System
**Files**: `includes/organizer/class-migration.php`

- [ ] Scan for existing Advanced_Formats images
- [ ] Create items for each original
- [ ] Link files correctly
- [ ] Preserve metadata
- [ ] Progress tracking (AJAX)
- [ ] Rollback capability

```php
class Migration {
    public function get_migration_candidates()
    public function migrate_attachment($attachment_id)
    public function migrate_batch($ids, $offset)
    public function rollback_migration()
}
```

**Admin UI**: Settings page section
- Migration status
- "Migrate Existing Files" button
- Progress bar
- Log of migrated items

**Acceptance Criteria**:
- All existing advanced format images migrated
- No data loss
- Original attachments still work
- Can rollback if needed

#### 2.3 Metadata Extraction
**Files**: `includes/organizer/class-metadata-extractor.php`

- [ ] Extract EXIF from images
- [ ] Extract camera info
- [ ] Get file dimensions
- [ ] Store in post meta

**Libraries**: Use existing PHP exif functions + `getimagesize()`

**Acceptance Criteria**:
- EXIF extracted on upload
- Stored in structured format
- Accessible via API
- No errors for files without EXIF

### Testing Phase 2
- [ ] Test RAW upload → Item creation
- [ ] Test standard upload → Optional item creation
- [ ] Migration test with 100 existing files
- [ ] Rollback test
- [ ] Metadata extraction for various formats

### Documentation Phase 2
- [ ] User guide: Uploading images
- [ ] Migration guide
- [ ] Troubleshooting guide

---

## Phase 3: Collections & Organization
**Duration**: 2 weeks
**Priority**: High
**Status**: Not Started

### Goals
- Implement collection management
- Build folder tree UI
- Enable bulk operations

### Deliverables

#### 3.1 Collection Manager
**Files**: `includes/organizer/class-collection-manager.php`

```php
- [ ] create_collection($name, $parent_id)
- [ ] get_collection($id)
- [ ] update_collection($id, $data)
- [ ] delete_collection($id, $delete_items)
- [ ] get_collections_tree()
- [ ] move_collection($id, $new_parent_id)
- [ ] get_items_in_collection($id, $recursive)
```

**Acceptance Criteria**:
- CRUD operations work
- Tree structure queryable
- Recursive item queries work
- Deletion handles children correctly

#### 3.2 Admin UI - Library Page
**Files**: `includes/organizer/class-admin-ui.php`, `admin/views/library-view.php`

**Menu**: OptiPress → Library (new submenu)

**Layout**:
```
┌─────────────┬──────────────────────────────────────┐
│ Collections │  Library Items (Grid/List)           │
│             │                                       │
│ 📁 All      │  [Search] [Filter] [Sort] [View]    │
│ 📁 2025     │  ┌────┐ ┌────┐ ┌────┐ ┌────┐         │
│   📁 Work   │  │IMG1│ │IMG2│ │IMG3│ │IMG4│         │
│   📁 Pers.  │  │CR2 │ │TIFF│ │PSD │ │NEF │         │
│ 📁 Archive  │  └────┘ └────┘ └────┘ └────┘         │
│             │                                       │
│ [+ New]     │  [Page 1 of 10]                      │
└─────────────┴──────────────────────────────────────┘
```

**Features**:
- [ ] Sidebar collection tree (collapsible)
- [ ] Grid view with thumbnails
- [ ] List view with table
- [ ] Search bar
- [ ] Filters: format, date, access level
- [ ] Sort: date, title, size
- [ ] Bulk actions: move, delete, set access
- [ ] Drag-and-drop to collections

**Tech Stack**:
- jQuery for interactions
- AJAX for tree operations
- WordPress list tables for list view

**Acceptance Criteria**:
- Page renders without errors
- Can create/edit/delete collections
- Items filterable by collection
- Bulk actions work
- Responsive design

#### 3.3 Item Edit Page
**Files**: `admin/views/item-edit.php`

**Location**: Click item → Edit page (custom admin page, not post editor)

**Sections**:
1. Preview image
2. Files list (with actions)
3. Details form (title, description)
4. Collections & Tags
5. Metadata display
6. Statistics

**Features**:
- [ ] Edit title/description
- [ ] Assign to collections (multi-select)
- [ ] Add/remove tags
- [ ] View file list
- [ ] Set display file
- [ ] Delete files
- [ ] Upload additional files

**Acceptance Criteria**:
- All edits save correctly
- File operations work
- Metadata displayed
- Can navigate back to library

### Testing Phase 3
- [ ] Collection CRUD
- [ ] Tree building
- [ ] UI interactions
- [ ] Bulk operations
- [ ] Large dataset (1000 items)

### Documentation Phase 3
- [ ] User guide: Organizing with collections
- [ ] User guide: Bulk operations
- [ ] Video tutorial (optional)

---

## Phase 4: Access Control & Download System
**Duration**: 2 weeks
**Priority**: High
**Status**: Not Started

### Goals
- Implement permission system
- Secure file downloads
- Track download statistics

### Deliverables

#### 4.1 Access Control System
**Files**: `includes/organizer/class-access-control.php`

```php
- [ ] can_view_item($item_id, $user_id)
- [ ] can_download_file($file_id, $user_id)
- [ ] set_item_access($item_id, $level)
- [ ] set_collection_access($collection_id, $level)
- [ ] check_user_capability($capability, $user_id)
```

**Access Levels**:
- Public (everyone)
- Logged In (any authenticated user)
- Subscribers (role: subscriber+)
- Contributors (role: contributor+)
- Custom roles (extensible)

**Permission Inheritance**:
- Item-level overrides collection-level
- Collection-level inherits from parent collection
- Default: Public

**Acceptance Criteria**:
- Permissions enforced on downloads
- UI shows only accessible items
- Can set per-item or per-collection
- Role-based checks work

#### 4.2 Download Handler
**Files**: `includes/organizer/class-download-handler.php`

```php
- [ ] serve_file($file_id, $user_id)
- [ ] generate_download_token($file_id, $expiry)
- [ ] validate_token($token)
- [ ] log_download($file_id, $metadata)
- [ ] get_download_stats($file_id)
```

**URL Structure**: `/wp-admin/admin-ajax.php?action=optipress_download&file={id}&token={token}`

OR

**Rewrite Rule**: `/optipress/download/{file_id}/{token}`

**Features**:
- [ ] Check permissions before serving
- [ ] Generate time-limited tokens (default: 1 hour)
- [ ] Log to database
- [ ] Support X-Sendfile
- [ ] Resume support (HTTP range requests)

**Acceptance Criteria**:
- Downloads work with permission check
- Tokens expire correctly
- Invalid tokens rejected
- Logs written correctly
- Large files download without timeout

#### 4.3 Download Logging & Statistics
**Files**: `includes/organizer/class-download-stats.php`

```php
- [ ] log_download($file_id, $user_id, $ip, $metadata)
- [ ] get_file_stats($file_id)
- [ ] get_item_stats($item_id)
- [ ] get_user_downloads($user_id)
- [ ] export_logs($format) // CSV, JSON
```

**Admin UI**: Statistics tab on item edit page

**Display**:
- Total downloads
- Unique downloaders
- Download timeline (chart)
- Recent downloads (table)

**Acceptance Criteria**:
- Logs accurate
- Stats calculations correct
- Charts render
- Export works

#### 4.4 Admin UI Updates
- [ ] Access control selector on item edit
- [ ] Access control on collection edit
- [ ] Download button with permission check
- [ ] Statistics dashboard

### Testing Phase 4
- [ ] Permission checks
- [ ] Token generation/validation
- [ ] Download with various user roles
- [ ] Log accuracy
- [ ] Large file downloads
- [ ] Concurrent downloads

### Documentation Phase 4
- [ ] User guide: Setting access controls
- [ ] User guide: Downloading files
- [ ] Developer: Permission system hooks
- [ ] Security considerations

---

## Phase 5: REST API & Frontend Integration
**Duration**: 2 weeks
**Priority**: Medium
**Status**: Not Started

### Goals
- Expose full REST API
- Enable headless/JS front-end usage
- Support external integrations

### Deliverables

#### 5.1 REST API Endpoints
**Files**: `includes/organizer/class-rest-api.php`

**Items**:
- [ ] `GET /wp-json/optipress/v1/items` - List items
- [ ] `POST /wp-json/optipress/v1/items` - Create item
- [ ] `GET /wp-json/optipress/v1/items/{id}` - Get item
- [ ] `PATCH /wp-json/optipress/v1/items/{id}` - Update item
- [ ] `DELETE /wp-json/optipress/v1/items/{id}` - Delete item

**Files**:
- [ ] `GET /wp-json/optipress/v1/items/{id}/files` - List files
- [ ] `POST /wp-json/optipress/v1/items/{id}/files` - Upload file
- [ ] `GET /wp-json/optipress/v1/files/{id}` - Get file info
- [ ] `DELETE /wp-json/optipress/v1/files/{id}` - Delete file
- [ ] `POST /wp-json/optipress/v1/files/{id}/download` - Generate download URL

**Collections**:
- [ ] `GET /wp-json/optipress/v1/collections` - List collections
- [ ] `POST /wp-json/optipress/v1/collections` - Create collection
- [ ] `GET /wp-json/optipress/v1/collections/{id}` - Get collection
- [ ] `GET /wp-json/optipress/v1/collections/{id}/items` - Items in collection

**Authentication**:
- WordPress nonce (for admin)
- Application passwords (for external)
- API keys (future: custom key system)

**Acceptance Criteria**:
- All endpoints functional
- Proper authentication
- Error responses follow WP standards
- Pagination works
- Filtering and search work

#### 5.2 JavaScript Library (Optional)
**Files**: `assets/js/optipress-api.js`

Wrapper for REST API calls:
```javascript
const OptiPressAPI = {
  items: {
    list(params),
    get(id),
    create(data),
    update(id, data),
    delete(id)
  },
  files: {
    list(itemId),
    upload(itemId, file),
    download(fileId)
  },
  collections: {
    tree(),
    items(collectionId)
  }
}
```

### Testing Phase 5
- [ ] API endpoint tests
- [ ] Authentication tests
- [ ] Permission checks via API
- [ ] Pagination
- [ ] Error handling

### Documentation Phase 5
- [ ] REST API reference (full documentation)
- [ ] Authentication guide
- [ ] JavaScript library guide
- [ ] Integration examples

---

## Phase 6: Shortcodes & Frontend Display
**Duration**: 2 weeks
**Priority**: Medium
**Status**: Not Started (Previously deferred)

### Goals
- Implement shortcode system
- Enable front-end galleries
- Support download buttons

### Deliverables

#### 6.1 Download Shortcode
**Files**: `includes/organizer/class-shortcodes.php`

```php
[optipress_download id="123"]
[optipress_download id="123" text="Download RAW" class="btn-primary"]
[optipress_download] // Auto-detect from attachment page
```

**Features**:
- [ ] Auto-detect context (attachment page)
- [ ] Custom button text
- [ ] Custom CSS classes
- [ ] Show file size
- [ ] Show format badge
- [ ] Permission check (hide if no access)

#### 6.2 Gallery Shortcode
```php
[optipress_gallery collection="landscapes"]
[optipress_gallery ids="1,2,3,4"]
[optipress_gallery tag="portfolio" columns="4"]
```

**Features**:
- [ ] Grid layout
- [ ] Lightbox integration
- [ ] Lazy loading
- [ ] Pagination
- [ ] Download buttons (optional)

#### 6.3 Item Display Shortcode
```php
[optipress_item id="123" show_download="true"]
```

**Displays**:
- Preview image
- Title & description
- Metadata
- Download button (if enabled)

### Testing Phase 6
- [ ] Shortcode rendering
- [ ] Permissions in front-end
- [ ] Mobile responsive
- [ ] Performance with large galleries

### Documentation Phase 6
- [ ] User guide: Using shortcodes
- [ ] Shortcode reference
- [ ] Styling guide

---

## Phase 7: Advanced Features & Polish
**Duration**: 2-3 weeks
**Priority**: Low
**Status**: Not Started

### Optional/Future Features

#### 7.1 Bulk Upload UI
- Drag-and-drop multiple files
- Upload queue with progress
- Auto-organize into collection

#### 7.2 File Versioning
- Keep history of file changes
- Archive old versions
- Restore previous versions

#### 7.3 Watermarking
- Apply watermark on download
- Configurable per collection
- Preview vs original watermarking

#### 7.4 Cloud Storage Integration
- Amazon S3 backend
- Google Drive sync
- Cloudflare Images

#### 7.5 Advanced Search
- Full-text search in metadata
- Search by EXIF values
- Saved search filters

#### 7.6 Export/Import
- Export library as JSON/XML
- Import from other plugins
- Backup/restore system

---

## Success Criteria

### Phase 1 Complete When:
- [ ] Can create items and files programmatically
- [ ] Database structure stable
- [ ] Core managers tested
- [ ] No breaking changes expected

### Phase 2 Complete When:
- [ ] Upload creates items automatically
- [ ] Existing files migrated
- [ ] No workflow disruptions

### Phase 3 Complete When:
- [ ] Can organize 1000+ items easily
- [ ] Collections system intuitive
- [ ] Bulk operations work smoothly

### Phase 4 Complete When:
- [ ] Downloads secured properly
- [ ] Permissions enforced
- [ ] Statistics tracked accurately

### Phase 5 Complete When:
- [ ] API fully documented
- [ ] External app can use API
- [ ] Authentication working

### Phase 6 Complete When:
- [ ] Shortcodes work in posts/pages
- [ ] Front-end display looks good
- [ ] Performance acceptable

---

## Timeline Estimate

**Total**: 12-16 weeks (3-4 months)

- Phase 1: Weeks 1-3
- Phase 2: Weeks 4-5
- Phase 3: Weeks 6-7
- Phase 4: Weeks 8-9
- Phase 5: Weeks 10-11
- Phase 6: Weeks 12-13
- Phase 7: Weeks 14-16 (optional)

**Milestones**:
- Month 1: Foundation complete (Phases 1-2)
- Month 2: Usable system (Phases 3-4)
- Month 3: Full feature set (Phases 5-6)
- Month 4: Polish and advanced features (Phase 7)

---

## Next Immediate Actions

1. **Review Architecture** - Approve LIBRARY-ORGANIZER-ARCHITECTURE.md
2. **Prototype Phase 1** - Build Item_Manager and File_Manager
3. **Design Database** - Finalize table structure
4. **UI Mockups** - Create wireframes for Library page
5. **Set Up Tests** - Prepare PHPUnit test suite

---

**Status**: 📋 Planning Phase - Ready for review and approval
**Last Updated**: 2025-10-01
