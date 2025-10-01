# OptiPress Library Organizer - Visual Diagrams

**Version**: 1.0
**Date**: 2025-10-01

## Entity Relationship Diagram (Mermaid)

```mermaid
erDiagram
    OPTIPRESS_ITEM ||--o{ OPTIPRESS_FILE : "has many"
    OPTIPRESS_ITEM ||--o{ DOWNLOAD_LOG : "tracks"
    OPTIPRESS_ITEM }o--o{ COLLECTION : "belongs to"
    OPTIPRESS_ITEM }o--o{ TAG : "has"
    OPTIPRESS_ITEM }o--|| ACCESS_LEVEL : "has"
    OPTIPRESS_FILE ||--o{ DOWNLOAD_LOG : "tracks"
    OPTIPRESS_FILE ||--o{ FILE_VERSION : "has versions"
    USER ||--o{ OPTIPRESS_ITEM : "uploads"
    USER ||--o{ DOWNLOAD_LOG : "downloads"
    COLLECTION ||--o{ COLLECTION : "parent of"

    OPTIPRESS_ITEM {
        bigint id PK
        string title
        text description
        datetime upload_date
        bigint author_id FK
        string status
        int view_count
    }

    OPTIPRESS_FILE {
        bigint id PK
        bigint parent_id FK
        string filename
        string file_path
        bigint file_size
        string format
        string variant_type
        string dimensions
        int download_count
    }

    COLLECTION {
        bigint id PK
        string name
        string slug
        bigint parent_id FK
        int item_count
    }

    TAG {
        bigint id PK
        string name
        string slug
    }

    ACCESS_LEVEL {
        bigint id PK
        string name
        string slug
        text description
    }

    DOWNLOAD_LOG {
        bigint id PK
        bigint file_id FK
        bigint item_id FK
        bigint user_id FK
        string ip_address
        datetime download_date
        bigint file_size
        string method
    }

    FILE_VERSION {
        bigint id PK
        bigint file_id FK
        string version_number
        string file_path
        datetime created_date
        boolean is_active
    }

    USER {
        bigint id PK
        string username
        string email
    }
```

---

## System Architecture (High-Level)

```mermaid
graph TB
    subgraph "User Interface"
        WP_ADMIN[WordPress Admin]
        WP_FRONTEND[WordPress Frontend]
        EXTERNAL[External Apps]
    end

    subgraph "OptiPress Library Organizer"
        UI[Admin UI Manager]
        API[REST API]
        SHORTCODES[Shortcode System]

        subgraph "Core Managers"
            ITEM_MGR[Item Manager]
            FILE_MGR[File Manager]
            COLL_MGR[Collection Manager]
            ACCESS[Access Control]
            DOWNLOAD[Download Handler]
        end

        subgraph "Data Layer"
            CPT[Custom Post Types]
            TAX[Taxonomies]
            META[Post Meta]
            CUSTOM_DB[Custom Tables]
        end

        FS[File System]
    end

    WP_ADMIN --> UI
    WP_FRONTEND --> SHORTCODES
    EXTERNAL --> API

    UI --> ITEM_MGR
    UI --> COLL_MGR
    SHORTCODES --> ITEM_MGR
    SHORTCODES --> DOWNLOAD
    API --> ITEM_MGR
    API --> FILE_MGR
    API --> COLL_MGR

    ITEM_MGR --> CPT
    FILE_MGR --> CPT
    FILE_MGR --> META
    FILE_MGR --> FS
    COLL_MGR --> TAX
    ACCESS --> TAX
    DOWNLOAD --> CUSTOM_DB
    DOWNLOAD --> FS
```

---

## Upload Flow Sequence

```mermaid
sequenceDiagram
    actor User
    participant WP as WordPress
    participant UH as Upload Handler
    participant AF as Advanced Formats
    participant TN as Thumbnailer
    participant IM as Item Manager
    participant FM as File Manager
    participant FS as File System
    participant DB as Database

    User->>WP: Upload IMG_5847.CR2
    WP->>AF: Process advanced format
    AF->>FS: Store original file
    AF->>AF: Generate preview.webp
    AF->>FS: Store preview file
    AF->>UH: Trigger upload handler

    UH->>IM: Create optipress_item
    IM->>DB: INSERT optipress_item post
    IM-->>UH: Item ID: 100

    UH->>FM: Add original file
    FM->>DB: INSERT optipress_file (original)
    FM->>FS: Organize into collections/100/original/
    FM-->>UH: File ID: 101

    UH->>FM: Add preview file
    FM->>DB: INSERT optipress_file (preview)
    FM->>FS: Organize into collections/100/preview/
    FM-->>UH: File ID: 102

    UH->>TN: Generate sizes
    TN->>TN: Create thumb, medium, large

    loop For each size
        TN->>FM: Add size file
        FM->>DB: INSERT optipress_file (size)
        FM->>FS: Organize into collections/100/sizes/
    end

    UH->>IM: Extract metadata
    IM->>DB: UPDATE item metadata

    UH->>IM: Auto-assign to collection
    IM->>DB: INSERT term_relationship

    UH-->>User: Upload complete
```

---

## Download Flow Sequence

```mermaid
sequenceDiagram
    actor Visitor
    participant FE as Frontend
    participant DH as Download Handler
    participant AC as Access Control
    participant DB as Database
    participant FS as File System

    Visitor->>FE: Click "Download Original"
    FE->>FE: Generate download URL + token
    FE-->>Visitor: Redirect to /optipress/download/101/abc123

    Visitor->>DH: GET /download/101/abc123
    DH->>DH: Validate token (HMAC, expiry)

    alt Token Invalid
        DH-->>Visitor: 403 Forbidden
    else Token Valid
        DH->>DB: Get file #101 info
        DB-->>DH: File + parent item data

        DH->>AC: Check permissions
        AC->>DB: Get access level
        AC->>AC: Check user capabilities

        alt No Permission
            AC-->>DH: Access denied
            DH-->>Visitor: 403 Forbidden
        else Has Permission
            AC-->>DH: Access granted

            DH->>DB: Log download
            DB-->>DH: OK

            DH->>DB: Increment download count
            DB-->>DH: OK

            DH->>FS: Read file
            FS-->>DH: File stream

            DH->>DH: Set headers (Content-Type, etc.)
            DH-->>Visitor: File download (5.2 MB)
        end
    end
```

---

## Collection Organization Flow

```mermaid
graph TB
    START[User Opens Library]

    START --> LOAD[Load Collections Tree]
    LOAD --> QUERY1[Query optipress_collection taxonomy]
    QUERY1 --> BUILD[Build hierarchical tree]
    BUILD --> DISPLAY[Display sidebar]

    DISPLAY --> ACTION{User Action}

    ACTION -->|Create Collection| CREATE[Create new term]
    CREATE --> REFRESH[Refresh tree]

    ACTION -->|Move Items| SELECT[Select items]
    SELECT --> DRAG[Drag to collection]
    DRAG --> UPDATE[Update term relationships]
    UPDATE --> CONFIRM[Show success]

    ACTION -->|Bulk Action| MULTI[Select multiple items]
    MULTI --> BULK_MOVE[Bulk move to collection]
    BULK_MOVE --> AJAX[AJAX batch processing]
    AJAX --> PROGRESS[Show progress]
    PROGRESS --> DONE[Complete]

    ACTION -->|Search| SEARCH[Enter search term]
    SEARCH --> FILTER[Filter items by query]
    FILTER --> RESULTS[Display results]
```

---

## Data Model (WordPress Integration)

```mermaid
graph LR
    subgraph "WordPress Core"
        POSTS[wp_posts]
        POSTMETA[wp_postmeta]
        TERMS[wp_terms]
        TERM_TAX[wp_term_taxonomy]
        TERM_REL[wp_term_relationships]
        USERS[wp_users]
    end

    subgraph "OptiPress Custom"
        DOWNLOADS[wp_optipress_downloads]
        VERSIONS[wp_optipress_file_versions]
    end

    subgraph "CPT Instances"
        ITEM[optipress_item posts]
        FILE[optipress_file posts]
    end

    subgraph "Taxonomy Instances"
        COLL[optipress_collection]
        TAG[optipress_tag]
        ACCESS[optipress_access]
    end

    POSTS --> ITEM
    POSTS --> FILE
    ITEM --> POSTMETA
    FILE --> POSTMETA
    FILE -->|post_parent| ITEM

    TERMS --> COLL
    TERMS --> TAG
    TERMS --> ACCESS
    TERM_TAX --> COLL
    TERM_TAX --> TAG
    TERM_TAX --> ACCESS

    ITEM --> TERM_REL
    TERM_REL --> TERM_TAX

    USERS -->|author| ITEM
    USERS -->|downloader| DOWNLOADS

    FILE --> DOWNLOADS
    ITEM --> DOWNLOADS
    FILE --> VERSIONS
```

---

## Access Control Decision Tree

```mermaid
graph TD
    START[Download Request]

    START --> AUTH{User Logged In?}

    AUTH -->|No| GUEST[Guest User]
    AUTH -->|Yes| MEMBER[Member User]

    GUEST --> CHECK_LEVEL{Access Level?}
    MEMBER --> CHECK_LEVEL

    CHECK_LEVEL -->|Public| ALLOW[Allow Download]
    CHECK_LEVEL -->|Logged In| CHECK_AUTH{Is Authenticated?}
    CHECK_LEVEL -->|Role-Based| CHECK_ROLE{Has Required Role?}
    CHECK_LEVEL -->|Private| CHECK_ADMIN{Is Administrator?}

    CHECK_AUTH -->|No| DENY[Deny Access]
    CHECK_AUTH -->|Yes| ALLOW

    CHECK_ROLE -->|No| DENY
    CHECK_ROLE -->|Yes| ALLOW

    CHECK_ADMIN -->|No| DENY
    CHECK_ADMIN -->|Yes| ALLOW

    ALLOW --> LOG[Log Download]
    LOG --> INCREMENT[Increment Counter]
    INCREMENT --> SERVE[Serve File]

    DENY --> ERROR[403 Forbidden]
```

---

## File System Organization

```
wp-content/uploads/optipress/
│
├── collections/
│   │
│   ├── 2025-portfolio/
│   │   ├── 100/                      ← Item ID
│   │   │   ├── original/
│   │   │   │   └── IMG_5847.CR2     (5.2 MB)
│   │   │   ├── preview/
│   │   │   │   └── preview.webp     (181 KB)
│   │   │   └── sizes/
│   │   │       ├── thumb-300x300.webp
│   │   │       ├── medium-1024x768.webp
│   │   │       └── large-2048x1536.webp
│   │   │
│   │   └── 101/
│   │       ├── original/
│   │       ├── preview/
│   │       └── sizes/
│   │
│   ├── client-work/
│   │   └── project-alpha/
│   │       └── 200/
│   │
│   └── uncategorized/
│       └── 150/
│
├── temp/
│   └── processing/           ← Temporary upload area
│
└── cache/
    └── thumbnails/           ← Admin thumbnail cache
```

---

## REST API Endpoints Map

```
/wp-json/optipress/v1/
│
├── items/
│   ├── GET    /              List all items (with filters)
│   ├── POST   /              Create new item
│   ├── GET    /{id}          Get single item
│   ├── PATCH  /{id}          Update item
│   ├── DELETE /{id}          Delete item
│   └── GET    /{id}/files    List files for item
│
├── files/
│   ├── POST   /              Upload new file
│   ├── GET    /{id}          Get file info
│   ├── PATCH  /{id}          Update file metadata
│   ├── DELETE /{id}          Delete file
│   ├── POST   /{id}/download Generate download URL
│   └── GET    /{id}/stats    Get download statistics
│
├── collections/
│   ├── GET    /              List all collections (tree)
│   ├── POST   /              Create collection
│   ├── GET    /{id}          Get single collection
│   ├── PATCH  /{id}          Update collection
│   ├── DELETE /{id}          Delete collection
│   └── GET    /{id}/items    Get items in collection
│
├── tags/
│   ├── GET    /              List all tags
│   ├── POST   /              Create tag
│   └── GET    /{id}/items    Get items with tag
│
└── stats/
    ├── GET    /downloads      Download statistics
    ├── GET    /popular        Most downloaded items
    └── GET    /recent         Recent activity
```

---

## Admin UI Layout (Library Page)

```
┌─────────────────────────────────────────────────────────────────┐
│ OptiPress → Library                                    [User ▼] │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│ ┌────────────────┬───────────────────────────────────────────┐ │
│ │ Collections    │  Library Items                            │ │
│ │                │  ┌──────────────────────────────────────┐ │ │
│ │ 📂 All Items   │  │ 🔍 Search... [Filter ▼] [Sort ▼]    │ │ │
│ │   (1,234)      │  │                                       │ │ │
│ │                │  │ [+ Upload] [Bulk Actions ▼]          │ │ │
│ │ 📁 2025        │  └──────────────────────────────────────┘ │ │
│ │   📁 Portfolio │                                            │ │
│ │   │ 📁 Land... │  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐         │ │
│ │   │ 📁 Port... │  │ IMG │ │ IMG │ │ IMG │ │ IMG │         │ │
│ │   │            │  │ 5847│ │ 5848│ │ 5849│ │ 5850│         │ │
│ │   📁 Work      │  │ CR2 │ │ NEF │ │ ARW │ │ TIFF│         │ │
│ │                │  │ 5.2M│ │ 6.1M│ │ 4.8M│ │ 8.3M│         │ │
│ │ 📁 Archive     │  └─────┘ └─────┘ └─────┘ └─────┘         │ │
│ │                │                                            │ │
│ │ [+ New]        │  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐         │ │
│ │                │  │ IMG │ │ IMG │ │ IMG │ │ IMG │         │ │
│ │                │  │ 5851│ │ 5852│ │ 5853│ │ 5854│         │ │
│ │                │  └─────┘ └─────┘ └─────┘ └─────┘         │ │
│ │                │                                            │ │
│ │                │  Page 1 of 62          [Grid ▣] [List ☰] │ │
│ └────────────────┴───────────────────────────────────────────┘ │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Item Edit Page Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ ← Back to Library                                 [Save Changes] │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│ ┌──────────────────────┬────────────────────────────────────┐   │
│ │                      │ Edit Item: Sunset at Beach         │   │
│ │                      ├────────────────────────────────────┤   │
│ │   [Preview Image]    │                                     │   │
│ │                      │ Title: [Sunset at Beach_________]   │   │
│ │   2048 x 1365 px     │                                     │   │
│ │   preview.webp       │ Description:                        │   │
│ │   181 KB             │ [____________________________]      │   │
│ │                      │ [____________________________]      │   │
│ │ [View Full Size]     │                                     │   │
│ │                      │ Collections:                        │   │
│ └──────────────────────┤ ☑ 2025 Portfolio > Landscapes      │   │
│                        │ ☐ Client Work                       │   │
│ Files (5)              │                                     │   │
│ ┌──────────────────────┤ Tags:                               │   │
│ │ 📄 IMG_5847.CR2      │ [sunset] [beach] [HDR] [+Add]      │   │
│ │    Original          │                                     │   │
│ │    5.2 MB            │ Access Level:                       │   │
│ │    [Download] [Del]  │ (•) Public  ( ) Members  ( ) Private│   │
│ ├──────────────────────┤                                     │   │
│ │ 🖼️ preview.webp ⭐   │ Metadata:                           │   │
│ │    Preview           │ Camera: Canon EOS R5                │   │
│ │    181 KB            │ ISO: 400  f/2.8  1/500s            │   │
│ │    [Download] [Del]  │ Date: 2025-09-01 18:30             │   │
│ ├──────────────────────┤                                     │   │
│ │ 📐 thumb-300x300     │ Statistics:                         │   │
│ │    Thumbnail         │ Views: 245                          │   │
│ │    15 KB             │ Downloads: 15 (original)           │   │
│ │    [Download] [Del]  │ Last download: 2 hours ago         │   │
│ └──────────────────────┴────────────────────────────────────┘   │
│                                                                   │
│                                 [Delete Item] [Save Changes]     │
└─────────────────────────────────────────────────────────────────┘
```

---

**Status**: 📊 Visual Documentation Complete
**Last Updated**: 2025-10-01
**Purpose**: Visual aids for understanding system architecture
**Tools**: Mermaid.js diagrams (render in GitHub/GitLab/compatible viewers)
