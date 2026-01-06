# Module Version Management System

## Tổng quan

Hệ thống quản lý version cho modules tự động phát hiện và cập nhật thông tin module khi có version mới từ filesystem, giúp đồng bộ hóa metadata giữa module.json và database một cách thông minh.

## Tính năng chính

### 1. **So sánh Version tự động**

Khi đồng bộ modules, hệ thống sẽ:

- ✅ Phát hiện modules mới và thêm vào database
- ✅ So sánh version của modules đã tồn tại
- ✅ Chỉ cập nhật thông tin khi phát hiện version mới hơn (sử dụng `version_compare()`)
- ✅ Xóa các bản ghi module đã bị xóa khỏi filesystem

### 2. **Cập nhật thông tin Module**

Khi phát hiện version mới, hệ thống tự động cập nhật:

- `version`: Version mới từ module.json
- `description`: Mô tả module (nếu có thay đổi)
- **Không thay đổi**: `enabled` status (giữ nguyên trạng thái hiện tại)

### 3. **Tự động cài đặt Dependencies**

Khi module được cập nhật version, hệ thống tự động:

- Dispatch job `InstallModuleDependenciesJob` để cài đặt/cập nhật dependencies mới
- Xử lý migrations nếu có
- Đảm bảo autoload được cập nhật

## Các thành phần

### ModuleSynchronizer

**Vị trí**: `src/Core/Module/ModuleSynchronizer.php`

**Phương thức chính**: `sync(): array`

**Kết quả trả về**:

```php
[
    'added' => ['Module1', 'Module2'],      // Modules mới được thêm
    'updated' => ['Module3', 'Module4'],     // Modules được cập nhật version
    'removed' => ['Module5']                 // Modules đã bị xóa
]
```

**Logic xử lý**:

```php
// 1. Lấy danh sách modules từ filesystem và database
$filesystemModules = $this->getFilesystemModules();
$databaseModules = Module::all()->keyBy('name');

// 2. Phát hiện modules mới
$newModules = array_diff($filesystemModuleNames, $databaseModuleNames);

// 3. Kiểm tra version updates cho modules đã tồn tại
foreach ($existingModules as $moduleName) {
    $filesystemVersion = $meta['version'] ?? '1.0.0';
    $databaseVersion = $dbModule->version ?? '1.0.0';

    if (version_compare($filesystemVersion, $databaseVersion, '>')) {
        // Cập nhật module
    }
}

// 4. Xóa modules cũ
$staleModules = array_diff($databaseModuleNames, $filesystemModuleNames);
```

### ModuleService::registerModule()

**Vị trí**: `src/Core/Services/ModuleService.php`

**Chức năng**:

- Đăng ký module mới hoặc cập nhật module đã tồn tại
- So sánh version trước khi cập nhật
- Trigger dependencies installation job

**Logic**:

```php
$existingModule = Module::where('name', $moduleName)->first();

if ($existingModule) {
    // So sánh version
    if (version_compare($filesystemVersion, $databaseVersion, '>')) {
        // Cập nhật thông tin
        $existingModule->update([...]);

        // Trigger dependencies job
        InstallModuleDependenciesJob::dispatch($moduleName);
    }
} else {
    // Tạo module mới
    Module::create([...]);
}
```

## Sử dụng

### 1. CLI Command

```bash
# Đồng bộ tất cả modules
php cli module:sync
```

**Output mẫu**:

```
Synchronizing Modules...

Registering new modules:
  + Registered: NewModule

Updating existing modules:
  ↻ Updated: ExistingModule

Module synchronization complete.
```

### 2. Web Interface

Khi cài đặt modules qua web UI (`/admin/modules`), hệ thống tự động:

1. Gọi `moduleSynchronizer->sync()`
2. Cập nhật version cho các modules đã tồn tại
3. Hiển thị thông báo về modules được cập nhật

**Thông báo mẫu**:

```
ℹ️ Đã cập nhật version cho 2 modules: Admin, User.
✅ Đã cài đặt thành công 1 module: Cms.
```

### 3. Tự động (Middleware)

`SyncModulesMiddleware` tự động kiểm tra và đồng bộ modules theo interval (mặc định 5 phút).

## Format Version

Hệ thống sử dụng **Semantic Versioning** (MAJOR.MINOR.PATCH):

```json
{
  "name": "MyModule",
  "version": "1.2.3",
  "description": "My awesome module"
}
```

**Ví dụ so sánh**:

- `1.0.0` → `1.0.1` ✅ Cập nhật
- `1.0.1` → `1.1.0` ✅ Cập nhật
- `1.1.0` → `2.0.0` ✅ Cập nhật
- `1.2.0` → `1.1.9` ❌ Không cập nhật (version cũ hơn)
- `1.0.0` → `1.0.0` ❌ Không cập nhật (version giống nhau)

## Logs

Hệ thống ghi log chi tiết cho mọi thao tác:

```php
// Module phát hiện version mới
Log::info("Module 'Admin' has a newer version", [
    'old_version' => '1.0.0',
    'new_version' => '1.1.0',
]);

// Module được cập nhật
Log::info("Updating module 'Admin' from version 1.0.0 to 1.1.0");

// Sync hoàn tất
Log::info('Module synchronization complete.', [
    'added' => 1,
    'updated' => 2,
    'removed' => 0,
]);
```

## Best Practices

### 1. Cập nhật Version trong module.json

Khi phát triển module, **luôn tăng version** khi có thay đổi:

```json
{
  "name": "MyModule",
  "version": "1.1.0", // ← Tăng version
  "description": "Updated description",
  "require": {
    "new-package/library": "^2.0" // ← Dependencies mới
  }
}
```

### 2. Semantic Versioning

Tuân thủ nguyên tắc Semantic Versioning:

- **MAJOR (1.x.x)**: Breaking changes (thay đổi không tương thích)
- **MINOR (x.1.x)**: New features (tính năng mới, tương thích ngược)
- **PATCH (x.x.1)**: Bug fixes (sửa lỗi)

### 3. Testing

Sau khi cập nhật version:

```bash
# 1. Sync modules
php cli module:sync

# 2. Kiểm tra logs
tail -f storage/logs/app.log

# 3. Verify trong database
SELECT name, version FROM modules;
```

### 4. Dependencies

Khi thêm dependencies mới, đảm bảo:

- Cập nhật `module.json` với dependencies mới
- Tăng version
- Chạy sync để trigger dependencies installation

## Workflow ví dụ

### Scenario: Cập nhật module "Blog" từ v1.0.0 lên v1.1.0

**1. Cập nhật code và module.json**:

```json
{
  "name": "Blog",
  "version": "1.1.0", // ← Tăng từ 1.0.0
  "description": "Blog module with new features",
  "require": {
    "league/commonmark": "^2.0" // ← Dependencies mới
  }
}
```

**2. Chạy sync**:

```bash
php cli module:sync
```

**3. Hệ thống tự động**:

- ✅ Phát hiện version mới (1.1.0 > 1.0.0)
- ✅ Cập nhật database: `version = '1.1.0'`
- ✅ Dispatch job cài đặt `league/commonmark`
- ✅ Update autoload
- ✅ Log thông tin

**4. Kết quả**:

```
Updating existing modules:
  ↻ Updated: Blog
Module versions updated.
```

## Troubleshooting

### Version không được cập nhật?

**Kiểm tra**:

1. Version trong `module.json` có lớn hơn version trong database không?
2. Format version có đúng không? (MAJOR.MINOR.PATCH)
3. Kiểm tra logs: `storage/logs/app.log`

### Dependencies không được cài?

**Kiểm tra**:

1. Job queue có đang chạy không?

```bash
php cli queue:work
```

2. Kiểm tra `jobs` table
3. Xem logs của `InstallModuleDependenciesJob`

### Module vẫn hiển thị version cũ?

**Cache có thể đang lưu dữ liệu cũ**:

```bash
# Clear cache
php cli cache:clear

# Hoặc trong code
Cache::forget('modules.all_list');
Cache::forget('all_modules_list');
```

## API Reference

### ModuleSynchronizer::sync()

```php
/**
 * Scans the filesystem for modules and synchronizes them with the database.
 *
 * @return array An array containing 'added', 'updated', and 'removed' module names.
 */
public function sync(): array
```

### ModuleService::registerModule()

```php
/**
 * Register a module or update if exists.
 * Checks version and only updates if newer version is found.
 *
 * @param string $moduleName The module name
 * @throws \Exception If module.json is missing or invalid
 */
public function registerModule(string $moduleName): void
```

## Tổng kết

Hệ thống Version Management đảm bảo:

- ✅ **Tự động**: Phát hiện và cập nhật version
- ✅ **Thông minh**: Chỉ cập nhật khi cần thiết
- ✅ **An toàn**: Giữ nguyên trạng thái enabled/disabled
- ✅ **Linh hoạt**: Hỗ trợ cả CLI và Web UI
- ✅ **Reliable**: Logging chi tiết và error handling

---

**Tài liệu liên quan**:

- [Module Composer Dependencies](./module-composer-dependencies.md)
- [Module Installation System](../CRUD.md)
