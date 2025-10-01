# OptiPress Library Organizer - Entity Relationship Diagram

**Version**: 1.0
**Date**: 2025-10-01

## Database Schema Visualization

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         WordPress Core Tables (Utilized)                         │
└─────────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────┐
│ wp_posts                          │
├──────────────────────────────────┤
│ ID (PK)                          │
│ post_type                        │  ← 'optipress_item' or 'optipress_file'
│ post_title                       │
│ post_content                     │
│ post_status                      │
│ post_author (FK → wp_users)      │
│ post_parent                      │  ← Links child files to parent item
│ post_date                        │
│ post_mime_type                   │
└──────────────────────────────────┘
       │
       │ (1:N)
       ▼
┌──────────────────────────────────┐
│ wp_postmeta                       │
├──────────────────────────────────┤
│ meta_id (PK)                     │
│ post_id (FK → wp_posts.ID)       │
│ meta_key                         │
│ meta_value                       │
└──────────────────────────────────┘

Meta Keys for optipress_item:
- _optipress_display_file (ID of primary display file)
- _optipress_featured_image (thumbnail URL)
- _optipress_original_dimensions (width x height)
- _optipress_upload_source (camera, scanner, etc.)
- _optipress_view_count (integer)
- _optipress_metadata (serialized EXIF data)

Meta Keys for optipress_file:
- _optipress_file_path (relative path)
- _optipress_file_size (bytes)
- _optipress_file_format (jpeg, webp, cr2, etc.)
- _optipress_dimensions (width x height)
- _optipress_variant_type (original, preview, thumb, etc.)
- _optipress_download_count (integer)
- _optipress_conversion_settings (serialized)
- _optipress_exif_data (serialized camera data)

┌──────────────────────────────────┐
│ wp_term_relationships             │
├──────────────────────────────────┤
│ object_id (FK → wp_posts.ID)     │
│ term_taxonomy_id (FK)            │
│ term_order                       │
└──────────────────────────────────┘
       │
       │ (N:M)
       ▼
┌──────────────────────────────────┐
│ wp_term_taxonomy                  │
├──────────────────────────────────┤
│ term_taxonomy_id (PK)            │
│ term_id (FK → wp_terms.term_id)  │
│ taxonomy                         │  ← 'optipress_collection', 'optipress_tag', etc.
│ description                      │
│ parent                           │  ← For hierarchical taxonomies
│ count                            │
└──────────────────────────────────┘
       │
       │ (1:1)
       ▼
┌──────────────────────────────────┐
│ wp_terms                          │
├──────────────────────────────────┤
│ term_id (PK)                     │
│ name                             │
│ slug                             │
│ term_group                       │
└──────────────────────────────────┘

Taxonomies:
- optipress_collection (hierarchical) - Folder structure
- optipress_tag (non-hierarchical) - Flexible tags
- optipress_access (non-hierarchical) - Access levels
- optipress_file_type (non-hierarchical) - Format types

┌──────────────────────────────────┐
│ wp_users                          │
├──────────────────────────────────┤
│ ID (PK)                          │
│ user_login                       │
│ user_email                       │
│ display_name                     │
└──────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────────────────┐
│                         OptiPress Custom Tables                                  │
└─────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ wp_optipress_downloads                   │
├─────────────────────────────────────────┤
│ id (PK, BIGINT, AUTO_INCREMENT)         │
│ file_id (FK → wp_posts.ID)              │  ← Links to optipress_file post
│ item_id (FK → wp_posts.ID)              │  ← Links to optipress_item post
│ user_id (FK → wp_users.ID, nullable)    │  ← NULL for guest downloads
│ ip_address (VARCHAR(45))                │
│ user_agent (TEXT)                       │
│ download_date (DATETIME)                │
│ file_size (BIGINT)                      │
│ download_method (VARCHAR(50))           │  ← 'direct', 'shortcode', 'api'
│ referrer (TEXT)                         │
├─────────────────────────────────────────┤
│ INDEX: file_id                          │
│ INDEX: item_id                          │
│ INDEX: user_id                          │
│ INDEX: download_date                    │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ wp_optipress_file_versions (Optional)    │
├─────────────────────────────────────────┤
│ id (PK, BIGINT, AUTO_INCREMENT)         │
│ file_id (FK → wp_posts.ID)              │
│ version_number (VARCHAR(20))            │
│ file_path (VARCHAR(500))                │
│ file_size (BIGINT)                      │
│ created_date (DATETIME)                 │
│ created_by (FK → wp_users.ID)           │
│ is_active (TINYINT(1))                  │
│ version_notes (TEXT)                    │
├─────────────────────────────────────────┤
│ INDEX: file_id                          │
│ INDEX: version_number                   │
└─────────────────────────────────────────┘
```

---

## Entity Relationships

### Primary Entities

#### 1. OptiPress Item (Parent Entity)
**Table**: `wp_posts` where `post_type = 'optipress_item'`

**Represents**: A logical image entry (e.g., "Sunset at Beach")

**Attributes**:
- ID (unique identifier)
- Title (user-friendly name)
- Description (post_content)
- Upload date (post_date)
- Author (post_author → wp_users)
- Status (publish, private, draft)

**Relationships**:
- **1:N** with OptiPress Files (via post_parent)
- **N:M** with Collections (via wp_term_relationships)
- **N:M** with Tags (via wp_term_relationships)
- **1:1** with Access Level (via wp_term_relationships)
- **1:N** with Downloads (via wp_optipress_downloads.item_id)

**Metadata** (wp_postmeta):
- Display file ID
- View count
- Featured thumbnail
- Original dimensions
- EXIF metadata

---

#### 2. OptiPress File (Child Entity)
**Table**: `wp_posts` where `post_type = 'optipress_file'`

**Represents**: A single file variant (original, preview, thumbnail, etc.)

**Attributes**:
- ID (unique identifier)
- Title (filename)
- Parent ID (post_parent → optipress_item.ID)
- MIME type (post_mime_type)
- Status (inherit from parent)

**Relationships**:
- **N:1** with OptiPress Item (via post_parent)
- **1:N** with Downloads (via wp_optipress_downloads.file_id)
- **1:N** with File Versions (optional, via wp_optipress_file_versions.file_id)

**Metadata** (wp_postmeta):
- File path
- File size
- Format (jpeg, webp, cr2, tiff)
- Dimensions
- Variant type (original, preview, thumbnail, size_name)
- Download count
- Conversion settings
- EXIF data

**Variant Types**:
- `original` - The uploaded RAW/TIFF/PSD file
- `preview` - Web-optimized display version
- `thumbnail` - Small preview (e.g., 300x300)
- `medium` - Medium size (e.g., 1024x768)
- `large` - Large size (e.g., 2048x1536)
- Custom size names from Size Profiles

---

#### 3. Collection (Folder/Category)
**Table**: `wp_terms` + `wp_term_taxonomy` where `taxonomy = 'optipress_collection'`

**Represents**: Hierarchical folder structure

**Attributes**:
- Term ID
- Name (collection name)
- Slug (URL-friendly)
- Parent ID (for nested collections)
- Description
- Count (number of items)

**Relationships**:
- **N:M** with OptiPress Items (via wp_term_relationships)
- **Self-referential** (parent-child hierarchy)

**Examples**:
```
📁 2025 Portfolio (parent: 0)
  📁 Landscapes (parent: "2025 Portfolio")
  📁 Portraits (parent: "2025 Portfolio")
📁 Client Work (parent: 0)
  📁 Project Alpha (parent: "Client Work")
```

---

#### 4. Tag
**Table**: `wp_terms` + `wp_term_taxonomy` where `taxonomy = 'optipress_tag'`

**Represents**: Non-hierarchical labels/keywords

**Attributes**:
- Term ID
- Name (tag name)
- Slug
- Count

**Relationships**:
- **N:M** with OptiPress Items (via wp_term_relationships)

**Examples**: "landscape", "HDR", "black-and-white", "microscopy"

---

#### 5. Access Level
**Table**: `wp_terms` + `wp_term_taxonomy` where `taxonomy = 'optipress_access'`

**Represents**: Permission levels for downloads

**Attributes**:
- Term ID
- Name (access level name)
- Slug
- Description (what this level means)

**Relationships**:
- **N:M** with OptiPress Items (via wp_term_relationships)
- Can also be applied to Collections

**Pre-defined Terms**:
- `public` - Everyone can download
- `logged_in` - Requires login
- `subscribers` - Subscriber role or higher
- `contributors` - Contributor role or higher
- `private` - Only administrators

---

#### 6. Download Log Entry
**Table**: `wp_optipress_downloads`

**Represents**: A single download event

**Attributes**:
- ID (unique log entry)
- File ID (which file was downloaded)
- Item ID (which item the file belongs to)
- User ID (who downloaded, NULL if guest)
- IP address
- User agent (browser info)
- Download date
- File size (at time of download)
- Download method (how was it accessed)
- Referrer (where the download link was clicked)

**Relationships**:
- **N:1** with OptiPress File
- **N:1** with OptiPress Item
- **N:1** with wp_users (optional)

**Indexes**: Optimized for queries like:
- "How many times was file X downloaded?"
- "What did user Y download?"
- "Downloads in the last 30 days"

---

#### 7. File Version (Optional - Future)
**Table**: `wp_optipress_file_versions`

**Represents**: Historical versions of a file

**Attributes**:
- ID
- File ID (which file this is a version of)
- Version number (e.g., "1.0", "2.0", "2023-10-01")
- File path (archived location)
- File size
- Created date
- Created by user
- Is active (current version)
- Version notes

**Relationships**:
- **N:1** with OptiPress File

**Use Cases**:
- User uploads updated version of RAW file
- Keep old version for reference
- Restore previous version

---

## Relationship Diagram (Simplified)

```
┌─────────────┐         ┌──────────────┐
│   wp_users  │◄────────┤ optipress_   │
│             │  author │    item      │
└─────────────┘         │  (wp_posts)  │
                        └───────┬──────┘
                                │
                                │ post_parent
                                │ (1:N)
                                ▼
                        ┌──────────────┐
                        │ optipress_   │
                        │    file      │
                        │  (wp_posts)  │
                        └───────┬──────┘
                                │
                                │ file_id (1:N)
                                ▼
                        ┌──────────────────┐
                        │  wp_optipress_   │
                        │   downloads      │
                        └──────────────────┘

┌──────────────┐         ┌──────────────┐
│ optipress_   │ ◄──┐    │  wp_terms    │
│    item      │    │    │              │
└──────────────┘    │    └──────────────┘
                    │           │
                    └───────────┤
                        N:M     │
                   (wp_term_relationships)
                                │
                        ┌───────┴──────┐
                        │              │
                ┌───────▼───┐  ┌───────▼───┐
                │Collections│  │   Tags    │
                │(taxonomy) │  │(taxonomy) │
                └───────────┘  └───────────┘
```

---

## Data Flow Examples

### Example 1: Uploading a RAW Image

1. **User uploads** `IMG_5847.CR2` (5.2 MB, Canon RAW)

2. **OptiPress creates**:
   - `optipress_item` post #100
     - Title: "IMG_5847"
     - Status: publish
     - Author: User ID 1

3. **Advanced_Formats processes**:
   - Detects CR2 format
   - Creates preview: `preview.webp` (181 KB)
   - Stores original in: `uploads/optipress/collections/uncategorized/100/original/IMG_5847.CR2`
   - Stores preview in: `uploads/optipress/collections/uncategorized/100/preview/preview.webp`

4. **OptiPress creates files**:
   - `optipress_file` post #101 (original)
     - Parent: 100
     - Meta: variant_type='original', format='cr2', size=5452800
   - `optipress_file` post #102 (preview)
     - Parent: 100
     - Meta: variant_type='preview', format='webp', size=185139

5. **Thumbnailer processes**:
   - Creates thumbnail (300x300)
   - Creates medium (1024x768)
   - Creates large (2048x1536)

6. **OptiPress creates more files**:
   - `optipress_file` post #103 (thumbnail)
   - `optipress_file` post #104 (medium)
   - `optipress_file` post #105 (large)

7. **Metadata extraction**:
   - Reads EXIF: Camera=Canon EOS R5, ISO=400, etc.
   - Stores in item meta: `_optipress_metadata`

8. **Auto-assign to collection** (optional):
   - Add term relationship: item #100 → collection "2025 Uploads"

**Result**: 1 item with 5 file variants, organized, metadata extracted

---

### Example 2: Downloading a File

1. **User clicks** "Download Original" button on item page

2. **Frontend generates link**:
   - URL: `/wp-admin/admin-ajax.php?action=optipress_download&file=101&token=abc123`
   - Token includes: file_id, user_id, expiry timestamp, signature

3. **Download handler validates**:
   - Check token signature (HMAC)
   - Check token not expired
   - Get file #101 from database
   - Get parent item #100
   - Check access level (is file public? does user have permission?)

4. **If authorized**:
   - Log download to `wp_optipress_downloads`
     - file_id: 101
     - item_id: 100
     - user_id: 5 (or NULL if guest)
     - ip_address: 192.168.1.100
     - download_date: 2025-10-01 14:30:00
   - Increment download count in file meta
   - Serve file (via PHP readfile or X-Sendfile header)

5. **If not authorized**:
   - Return 403 Forbidden
   - Show "You don't have permission" message

**Result**: Secure download with tracking

---

### Example 3: Organizing into Collections

1. **User creates collection** "2025 Portfolio"
   - Creates term: name="2025 Portfolio", taxonomy='optipress_collection'
   - Term ID: 10

2. **User creates sub-collection** "Landscapes" under "2025 Portfolio"
   - Creates term: name="Landscapes", taxonomy='optipress_collection', parent=10
   - Term ID: 11

3. **User moves item #100 to "Landscapes"**
   - Creates term relationship: object_id=100, term_taxonomy_id=11

4. **User moves item #200 to "Landscapes"**
   - Creates term relationship: object_id=200, term_taxonomy_id=11

5. **Query items in "Landscapes"**:
   ```sql
   SELECT p.*
   FROM wp_posts p
   JOIN wp_term_relationships tr ON p.ID = tr.object_id
   JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
   WHERE p.post_type = 'optipress_item'
     AND tt.taxonomy = 'optipress_collection'
     AND tt.term_id = 11
   ```

**Result**: Items organized hierarchically

---

## Indexing Strategy

### Recommended Indexes (WordPress Core Tables)

**wp_posts**:
- Already indexed: ID (PK), post_type, post_parent, post_author
- Consider adding composite: `(post_type, post_parent)` for child queries
- Consider adding: `(post_type, post_status, post_date)` for listing

**wp_postmeta**:
- Already indexed: post_id, meta_key
- Consider adding composite: `(post_id, meta_key)` (if not already)

**wp_term_relationships**:
- Already indexed: object_id, term_taxonomy_id

### Custom Table Indexes

**wp_optipress_downloads**:
```sql
PRIMARY KEY (id)
INDEX idx_file_id (file_id)
INDEX idx_item_id (item_id)
INDEX idx_user_id (user_id)
INDEX idx_download_date (download_date)
COMPOSITE INDEX idx_file_user (file_id, user_id)
COMPOSITE INDEX idx_date_range (download_date, file_id)
```

**wp_optipress_file_versions** (future):
```sql
PRIMARY KEY (id)
INDEX idx_file_id (file_id)
INDEX idx_version (version_number)
INDEX idx_active (is_active)
```

---

## Query Examples

### Get all files for an item
```sql
SELECT *
FROM wp_posts
WHERE post_type = 'optipress_file'
  AND post_parent = 100;
```

### Get original file for an item
```sql
SELECT p.*, pm.meta_value AS file_path
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_optipress_variant_type'
WHERE p.post_type = 'optipress_file'
  AND p.post_parent = 100
  AND pm.meta_value = 'original';
```

### Get all items in a collection (including subcollections)
```sql
-- Get collection and all descendants
WITH RECURSIVE collection_tree AS (
  SELECT term_id FROM wp_term_taxonomy WHERE term_id = 11
  UNION ALL
  SELECT tt.term_id
  FROM wp_term_taxonomy tt
  JOIN collection_tree ct ON tt.parent = ct.term_id
)
SELECT DISTINCT p.*
FROM wp_posts p
JOIN wp_term_relationships tr ON p.ID = tr.object_id
JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
WHERE p.post_type = 'optipress_item'
  AND tt.term_id IN (SELECT term_id FROM collection_tree);
```

### Get download stats for a file
```sql
SELECT
  COUNT(*) AS total_downloads,
  COUNT(DISTINCT user_id) AS unique_users,
  COUNT(DISTINCT ip_address) AS unique_ips,
  MIN(download_date) AS first_download,
  MAX(download_date) AS last_download
FROM wp_optipress_downloads
WHERE file_id = 101;
```

### Get top downloaded items
```sql
SELECT
  i.ID,
  i.post_title,
  COUNT(d.id) AS download_count
FROM wp_posts i
LEFT JOIN wp_optipress_downloads d ON i.ID = d.item_id
WHERE i.post_type = 'optipress_item'
GROUP BY i.ID
ORDER BY download_count DESC
LIMIT 10;
```

---

## Scalability Considerations

### Performance at Scale

**Expected Volumes**:
- Items: 10,000 - 100,000
- Files: 50,000 - 500,000 (5x items avg)
- Downloads: 1M+ (grows continuously)

**Optimizations**:
1. **Caching**
   - Use object cache (Redis/Memcached) for:
     - Collection trees
     - Item metadata
     - User permissions
   - Use transients for expensive queries

2. **Pagination**
   - Always paginate large result sets
   - Use `SQL_CALC_FOUND_ROWS` sparingly (can be slow)
   - Consider cursor-based pagination for very large sets

3. **Async Processing**
   - Generate thumbnails in background queue
   - Metadata extraction async
   - Bulk operations use AJAX chunking

4. **Database Partitioning** (extreme scale)
   - Partition `wp_optipress_downloads` by date range
   - Archive old logs to separate table

5. **File System**
   - Use CDN for previews/thumbnails
   - Keep originals on fast storage
   - Implement cache purging

---

## Backup & Migration

### What to Backup

1. **Database**:
   - wp_posts (optipress_item and optipress_file entries)
   - wp_postmeta (all OptiPress meta)
   - wp_terms, wp_term_taxonomy, wp_term_relationships (collections/tags)
   - wp_optipress_downloads (logs)

2. **Files**:
   - `wp-content/uploads/optipress/` (entire directory)

### Export Format

**JSON Export Structure**:
```json
{
  "version": "1.0",
  "export_date": "2025-10-01T14:00:00Z",
  "items": [
    {
      "id": 100,
      "title": "Sunset at Beach",
      "description": "...",
      "date": "2025-09-01",
      "author": 1,
      "collections": ["2025 Portfolio", "Landscapes"],
      "tags": ["sunset", "beach"],
      "access_level": "public",
      "metadata": {...},
      "files": [
        {
          "id": 101,
          "variant_type": "original",
          "filename": "IMG_5847.CR2",
          "path": "collections/2025-portfolio/100/original/IMG_5847.CR2",
          "size": 5452800,
          "format": "cr2",
          "dimensions": "8192x5464",
          "downloads": 15,
          "metadata": {...}
        },
        {...}
      ],
      "download_history": [...]
    }
  ]
}
```

---

**Status**: ✅ Architecture Defined - Ready for Implementation
**Last Updated**: 2025-10-01
