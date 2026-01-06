# 🎨 Page-Block Admin System Guide

## ✅ Hoàn tất hệ thống Admin cho Pages & Blocks

### 🚀 Đã triển khai:

1. ✅ **Page Management API** - CRUD operations cho pages
2. ✅ **Block Assignment API** - Gán/xóa blocks cho pages
3. ✅ **Admin Page Management UI** - Giao diện quản lý pages
4. ✅ **Block Editor với Drag & Drop** - Trình chỉnh sửa blocks trực quan

---

## 📁 Files đã tạo

### Backend API:

- **`Modules/Cms/Http/Controllers/PageManagementController.php`**
  - CRUD operations cho pages
  - Assign/remove blocks
  - Reorder blocks
  - Auto-create regions

### Frontend UI:

- **`public/admin/pages.html`** - Page management dashboard
- **`public/admin/page-blocks.html`** - Block editor với drag & drop

### Services:

- **`Modules/Cms/Domain/Services/PageBlockRenderer.php`** - Render blocks cho pages
- **`database/Seeders/PageBlockIntegrationSeeder.php`** - Seed integration data

---

## 🎯 API Endpoints

### Page Management

#### **GET /admin/pages**

List all pages với block count

```json
{
  "pages": [
    {
      "id": 1,
      "name": "Home",
      "slug": "home",
      "block_count": 3,
      "created_at": "2025-10-27T..."
    }
  ],
  "total": 5
}
```

#### **GET /admin/pages/{id}**

Get single page với blocks theo regions

```json
{
  "page": {...},
  "regions": {
    "hero": "page-home-hero",
    "content": "page-home-content",
    "sidebar": "page-home-sidebar"
  },
  "blocks": {
    "hero": [...],
    "content": [...],
    "sidebar": [...]
  }
}
```

#### **POST /admin/pages**

Create new page

```json
{
  "name": "New Page",
  "slug": "new-page"
}
```

#### **PUT /admin/pages/{id}**

Update page

```json
{
  "name": "Updated Name",
  "slug": "updated-slug"
}
```

#### **DELETE /admin/pages/{id}**

Delete page (và tất cả blocks của nó)

---

### Block Assignment

#### **POST /admin/pages/{pageId}/blocks**

Assign block to page

```json
{
  "block_type_id": 1,
  "region": "content", // hero, content, sidebar
  "title": "My Block",
  "config": {},
  "visible": true
}
```

#### **DELETE /admin/pages/{pageId}/blocks/{blockId}**

Remove block from page

#### **POST /admin/pages/{pageId}/blocks/reorder**

Reorder blocks in a region

```json
{
  "blocks": [3, 1, 2] // Array of block IDs in new order
}
```

---

## 🖥️ Admin UI Usage

### 1. Page Management (`/admin/pages.html`)

**Features:**

- ✅ View all pages in grid layout
- ✅ Create new pages
- ✅ Edit page name & slug
- ✅ Delete pages
- ✅ View block count per page
- ✅ Quick access to block editor

**Workflow:**

```
1. Open /admin/pages.html
2. Click "Create New Page"
3. Enter name (slug auto-generated)
4. Click "Save Page"
5. Regions auto-created (hero, content, sidebar)
```

---

### 2. Block Editor (`/admin/page-blocks.html`)

**Features:**

- ✅ Drag & drop block types from sidebar
- ✅ Drop into regions (hero, content, sidebar)
- ✅ View blocks organized by region
- ✅ Remove blocks
- ✅ Auto-saves block order

**Workflow:**

```
1. Click "Blocks" on a page card
2. Drag block type from left sidebar
3. Drop into region (hero/content/sidebar)
4. Enter block title
5. Click "Add Block"
6. Block appears in region
```

**Drag & Drop:**

- Drag từ sidebar → Drop vào region → Add block
- Visual feedback khi drag over region
- Auto-order blocks theo thứ tự drop

---

## 🎨 UI Features

### Page Management UI:

- **Grid Layout** - Cards cho mỗi page
- **Quick Actions** - Edit, Blocks, Delete buttons
- **Auto Slug** - Generate slug từ page name
- **Validation** - Slug must be unique & lowercase
- **Confirmation** - Confirm before delete

### Block Editor UI:

- **Sidebar** - List tất cả block types có thể drag
- **Regions** - 3 sections: Hero, Content, Sidebar
- **Block Cards** - Show block info & actions
- **Empty State** - "Drag blocks here" hint
- **Real-time Updates** - Refresh after actions

---

## 💡 Advanced Features

### Auto Region Creation

Khi tạo page mới, system tự động tạo 3 regions:

```php
$regions = [
    "page-{slug}-hero",     // Max 1 block
    "page-{slug}-content",  // Max 10 blocks
    "page-{slug}-sidebar",  // Max 5 blocks
];
```

### Block Context System

Blocks có thể gán theo context:

- **Global** (`context_type='global'`) - Hiển thị trên tất cả pages
- **Page** (`context_type='page', context_id=page_id`) - Chỉ page cụ thể

### Permission System

API kiểm tra permissions:

- `cms.pages.view` - Xem pages
- `cms.pages.create` - Tạo pages
- `cms.pages.update` - Cập nhật pages & blocks
- `cms.pages.delete` - Xóa pages

(Bypass nếu `app.debug=true`)

---

## 🔧 Customization

### Thêm Regions mới:

```php
// In PageBlockRenderer.php
public function getPageRegions(Page $page): array
{
    return [
        'hero' => "page-{$page->slug}-hero",
        'content' => "page-{$page->slug}-content",
        'sidebar' => "page-{$page->slug}-sidebar",
        'footer' => "page-{$page->slug}-footer",  // NEW
    ];
}
```

### Thay đổi Max Blocks:

```php
// In PageManagementController.php
private function createPageRegions(Page $page): void
{
    $regions = [
        ['name' => "...", 'max_blocks' => 20], // Increase limit
    ];
}
```

### Custom Block Icons:

```javascript
// In page-blocks.html
const blockIcon = block.block_type.icon || "🎨"; // Default icon
```

---

## 🧪 Testing

### Test Page CRUD:

```bash
# List pages
curl http://localhost:888/admin/pages

# Create page
curl -X POST http://localhost:888/admin/pages \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Page","slug":"test-page"}'

# Get page
curl http://localhost:888/admin/pages/1

# Update page
curl -X PUT http://localhost:888/admin/pages/1 \
  -H "Content-Type: application/json" \
  -d '{"name":"Updated Page"}'

# Delete page
curl -X DELETE http://localhost:888/admin/pages/1
```

### Test Block Assignment:

```bash
# Assign block
curl -X POST http://localhost:888/admin/pages/1/blocks \
  -H "Content-Type: application/json" \
  -d '{
    "block_type_id": 1,
    "region": "content",
    "title": "Test Block"
  }'

# Remove block
curl -X DELETE http://localhost:888/admin/pages/1/blocks/5

# Reorder blocks
curl -X POST http://localhost:888/admin/pages/1/blocks/reorder \
  -H "Content-Type: application/json" \
  -d '{"blocks":[3,1,2]}'
```

---

## 📊 Database Structure

### Tables Used:

- `pages` - Page records
- `block_instances` - Block assignments
- `block_types` - Available block types
- `block_regions` - Region definitions

### Relationships:

```
pages (1) ─→ (n) block_instances
block_types (1) ─→ (n) block_instances
block_regions (1) ─→ (n) block_instances
```

---

## 🎯 Next Steps (Optional)

### Chức năng có thể mở rộng:

1. **Block Configuration Editor**
   - Edit block config fields trong UI
   - JSON editor hoặc form builder

2. **Block Preview**
   - Xem trước block trước khi add
   - Live preview khi edit config

3. **Page Templates**
   - Pre-defined layouts
   - Clone pages với blocks

4. **Block Reordering**
   - Drag & drop to reorder within region
   - Visual feedback

5. **Bulk Operations**
   - Duplicate multiple blocks
   - Move blocks between pages
   - Export/Import page layouts

6. **Version History**
   - Track block changes
   - Rollback to previous versions

---

## 🎉 Hoàn tất!

Hệ thống Page-Block Admin đã sẵn sàng sử dụng:

✅ **API Backend** - Full CRUD + Block management
✅ **Admin UI** - Beautiful, intuitive interface  
✅ **Drag & Drop** - Easy block assignment
✅ **Auto-sync** - Real-time updates
✅ **Permissions** - Access control ready
✅ **Documentation** - Complete guide

**Access:**

- Pages: `http://localhost:888/admin/pages.html`
- Block Editor: `http://localhost:888/admin/page-blocks.html?page=1`

**Happy building!** 🚀
