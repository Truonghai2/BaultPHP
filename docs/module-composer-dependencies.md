# Hệ Thống Qu ản Lý Composer Dependencies Cho Modules

## Tổng Quan

Hệ thống cho phép các module có thể định nghĩa và cài đặt Composer dependencies riêng của mình một cách tự động và an toàn.

### Tính Năng Chính

- ✅ **Auto-install Dependencies**: Tự động cài đặt dependencies khi module được install
- ✅ **Module composer.json Support**: Hỗ trợ composer.json riêng cho module
- ✅ **Dependency Merge**: Merge dependencies vào root composer.json
- ✅ **Validation**: Validate dependencies trước khi cài đặt
- ✅ **Rollback Support**: Tự động rollback khi có lỗi
- ✅ **CLI Management**: Quản lý dependencies qua CLI commands
- ✅ **Background Processing**: Cài đặt trong background job không block request
- ✅ **Progress Tracking**: Track trạng thái cài đặt
- ✅ **Error Handling**: Xử lý lỗi và retry logic

## Kiến Trúc

### Components

```
┌─────────────────────────────────────────────────────────────┐
│                   Module Installation Flow                   │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│  ModuleController::processInstall()                          │
│  - User confirms module installation                         │
│  - Calls ModuleService->registerModule()                     │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  ModuleService::registerModule()                             │
│  - Create module record in DB (status: 'installing')         │
│  - Dispatch InstallModuleDependenciesJob                     │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  InstallModuleDependenciesJob (Background)                   │
│  - Read module.json / composer.json                          │
│  - Call ComposerDependencyManager                            │
│  - Run migrations                                            │
│  - Update module status                                      │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  ComposerDependencyManager                                   │
│  - Validate dependencies                                     │
│  - Backup composer.json                                      │
│  - Run composer require/update                               │
│  - Dump autoload                                             │
│  - Rollback on error                                         │
└─────────────────────────────────────────────────────────────┘
```

## Định Nghĩa Dependencies

### Option 1: Sử Dụng module.json

Định nghĩa dependencies trong `Modules/{ModuleName}/module.json`:

```json
{
  "name": "Blog",
  "version": "1.0.0",
  "description": "Blog management system",
  "require": {
    "php": "^8.2",
    "intervention/image": "^2.7",
    "spatie/laravel-sluggable": "^3.4",
    "league/commonmark": "^2.4"
  }
}
```

**Lưu ý**:

- `php` và `ext-*` sẽ được skip (không cài qua Composer)
- Chỉ packages thật sự mới được cài đặt

### Option 2: Sử Dụng composer.json Riêng (Recommended)

Tạo `Modules/{ModuleName}/composer.json`:

```json
{
  "name": "your-vendor/blog-module",
  "description": "Blog module for BaultFrame",
  "type": "bault-module",
  "require": {
    "php": "^8.2",
    "intervention/image": "^2.7",
    "spatie/laravel-sluggable": "^3.4",
    "league/commonmark": "^2.4"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0",
    "mockery/mockery": "^1.5"
  },
  "autoload": {
    "psr-4": {
      "Modules\\Blog\\": ""
    },
    "files": ["helpers.php"]
  },
  "extra": {
    "branch-alias": {
      "dev-main": "1.x-dev"
    }
  }
}
```

**Advantages**:

- Hỗ trợ `require-dev` cho dev dependencies
- Custom autoload configuration
- Custom repositories nếu cần
- Standard Composer format
- IDE support tốt hơn

## ComposerDependencyManager Service

### API Reference

#### `installDependencies()`

Cài đặt dependencies cho module.

```php
use Core\Services\ComposerDependencyManager;

$composerManager = app(ComposerDependencyManager::class);

// From module.json
$result = $composerManager->installDependencies('Blog', [
    'intervention/image' => '^2.7',
    'spatie/laravel-sluggable' => '^3.4',
]);

// From composer.json (auto-detect)
$result = $composerManager->installDependencies('Blog');
```

**Returns**:

```php
[
    'status' => 'success',
    'message' => 'Successfully installed 2 package(s)',
    'installed' => [
        'intervention/image:^2.7',
        'spatie/laravel-sluggable:^3.4',
    ],
    'skipped' => [
        'php: ^8.2 (PHP version requirement)',
    ],
    'output' => '... composer output ...',
]
```

#### `removeDependencies()`

Xóa dependencies của module.

```php
$result = $composerManager->removeDependencies('Blog', [
    'intervention/image',
    'spatie/laravel-sluggable',
]);
```

#### `validateModuleComposer()`

Validate composer.json của module.

```php
$validation = $composerManager->validateModuleComposer('/path/to/module');

if ($validation['valid']) {
    echo "Valid composer.json";
} else {
    echo "Errors: " . implode(', ', $validation['errors']);
}
```

#### `checkComposerInstallation()`

Kiểm tra Composer có cài đặt không.

```php
$check = $composerManager->checkComposerInstallation();

if ($check['installed']) {
    echo "Composer version: " . $check['version'];
} else {
    echo "Error: " . $check['error'];
}
```

#### `dumpAutoload()`

Regenerate composer autoload files.

```php
$result = $composerManager->dumpAutoload($optimize = true);
```

## CLI Commands

### module:composer

Quản lý Composer dependencies cho modules qua CLI.

```bash
# Check Composer installation
php cli module:composer --check-composer

# Install dependencies for a module
php cli module:composer --install=Blog

# Update dependencies
php cli module:composer --update=Blog

# Remove dependencies
php cli module:composer --remove=Blog

# Check dependencies status
php cli module:composer --check=Blog

# Regenerate autoload
php cli module:composer --dump-autoload
```

### Examples

#### Install Dependencies

```bash
php cli module:composer --install=Blog
```

**Output**:

```
Installing Dependencies for Module: Blog
========================================

Dependencies to install:
  • intervention/image: ^2.7
  • spatie/laravel-sluggable: ^3.4

Proceed with installation? (yes/no) [yes]:
> yes

Installing... (this may take several minutes)

[OK] Successfully installed 2 package(s)

Installed packages:
  ✓ intervention/image:^2.7
  ✓ spatie/laravel-sluggable:^3.4

Skipped:
  ⊘ php: ^8.2 (PHP version requirement)
```

#### Check Dependencies

```bash
php cli module:composer --check=Blog
```

**Output**:

```
Checking Dependencies for Module: Blog
=======================================

module.json Dependencies:
  • php: 8.2
  • intervention/image: ^2.7
  • spatie/laravel-sluggable: ^3.4

Module composer.json:
[OK] composer.json is valid

Require:
  • intervention/image: ^2.7
  • spatie/laravel-sluggable: ^3.4

Module Status:
  Status: installed
  Enabled: Yes
  Version: 1.0.0
```

## Background Job: InstallModuleDependenciesJob

### Job Configuration

```php
class InstallModuleDependenciesJob extends Job
{
    // Số lần thử lại nếu fail
    public int $tries = 3;

    // Timeout (15 phút)
    public int $timeout = 900;
}
```

### Job Flow

1. **Read module metadata** từ module.json
2. **Check Composer** có sẵn không
3. **Install dependencies** qua ComposerDependencyManager
4. **Dump autoload** để load classes mới
5. **Run migrations** (nếu có)
6. **Update module status** trong database

### Status Tracking

Module có các status sau trong quá trình cài đặt:

- `installing` - Module đang được đăng ký
- `installing_dependencies` - Đang cài dependencies
- `installed` - Cài đặt thành công
- `installation_failed` - Cài đặt thất bại (sẽ retry)
- `installation_permanently_failed` - Thất bại vĩnh viễn (đã retry max lần)

### Monitoring Job

```bash
# Check queue status
php cli queue:work

# View failed jobs
php cli queue:failed

# Retry failed job
php cli queue:retry {job-id}

# View logs
tail -f storage/logs/bault-*.log | grep "📦"
```

## Rollback Support

Hệ thống tự động backup và rollback khi có lỗi:

### Auto-backup

```php
// Before any composer operation
$composerManager->backupComposerJson($composerPath);

// composer.json.backup được tạo
```

### Auto-rollback

```php
try {
    // Install dependencies
    $result = $composerManager->installDependencies(...);
} catch (\Throwable $e) {
    // Auto rollback from backup
    $composerManager->rollbackComposerJson($composerPath);
    throw $e;
}
```

### Manual Rollback

```bash
# Nếu cần rollback thủ công
cp composer.json.backup composer.json
composer install
```

## Error Handling

### Common Errors

#### 1. Composer Not Found

**Error**:

```
Composer is not installed or not accessible
```

**Solution**:

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Or use composer.phar in project root
php composer.phar --version
```

#### 2. Package Not Found

**Error**:

```
Failed to install dependencies: Package 'vendor/package' not found
```

**Solution**:

- Kiểm tra package name chính xác
- Kiểm tra package có tồn tại trên Packagist không
- Thêm custom repository nếu là private package

#### 3. Version Conflict

**Error**:

```
Your requirements could not be resolved to an installable set of packages
```

**Solution**:

- Kiểm tra version constraints
- Update dependencies trong module.json
- Dùng `composer why-not vendor/package version` để debug

#### 4. Timeout

**Error**:

```
ProcessTimedOutException: The process timed out
```

**Solution**:

```php
// Increase timeout trong ComposerDependencyManager
private const COMPOSER_TIMEOUT = 1200; // 20 minutes
```

### Debug

```bash
# Enable verbose logging
php cli module:composer --install=Blog -vvv

# Check composer.json
cat composer.json

# Check composer.lock
cat composer.lock

# Manual composer install
composer install --no-interaction --prefer-dist -vvv
```

## Best Practices

### 1. Use Semantic Versioning

```json
{
  "require": {
    "vendor/package": "^2.0", // ✅ Good: Allow minor updates
    "other/lib": "~1.4.2", // ✅ Good: Allow patch updates
    "exact/version": "1.0.0", // ⚠️ Too strict
    "any/version": "*" // ❌ Bad: Too loose
  }
}
```

### 2. Minimize Dependencies

Chỉ require những packages thật sự cần thiết:

```json
{
  "require": {
    "intervention/image": "^2.7", // ✅ Cần cho image processing
    "guzzlehttp/guzzle": "^7.0" // ❌ Không cần, dùng built-in HTTP client
  }
}
```

### 3. Use Composer.json for Complex Modules

Nếu module có:

- Nhiều dependencies
- Dev dependencies
- Custom autoload rules
- Custom repositories

→ Dùng `composer.json` thay vì chỉ `module.json`

### 4. Test Before Deploying

```bash
# Dev environment
php cli module:composer --install=Blog

# Run tests
php cli test

# Check for issues
php cli module:composer --check=Blog

# Production: Test trên staging trước
```

### 5. Document Dependencies

Trong `README.md` của module:

```markdown
## Dependencies

This module requires:

- PHP ^8.2
- intervention/image ^2.7 - For image manipulation
- spatie/laravel-sluggable ^3.4 - For URL-friendly slugs

## Installation

Dependencies will be automatically installed when you install the module via admin panel or:

`\`\`bash
php cli module:sync
php cli module:composer --install=Blog
\`\`\`
```

## Examples

### Example 1: Simple Module with Few Dependencies

**module.json**:

```json
{
  "name": "SimpleModule",
  "version": "1.0.0",
  "require": {
    "php": "^8.2",
    "ext-gd": "*",
    "monolog/monolog": "^3.0"
  }
}
```

**Cài đặt**:

```bash
# Via Web UI: Click "Install" button

# Via CLI:
php cli module:sync
php cli module:composer --install=SimpleModule
```

### Example 2: Complex Module with composer.json

**Module structure**:

```
Modules/ECommerce/
├── composer.json
├── module.json
├── Application/
├── Domain/
├── Infrastructure/
└── helpers.php
```

**composer.json**:

```json
{
  "name": "baultframe/ecommerce-module",
  "description": "E-commerce module for BaultFrame",
  "type": "bault-module",
  "require": {
    "php": "^8.2",
    "stripe/stripe-php": "^10.0",
    "paypal/rest-api-sdk-php": "^1.14",
    "intervention/image": "^2.7"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0"
  },
  "autoload": {
    "psr-4": {
      "Modules\\ECommerce\\": ""
    },
    "files": ["helpers.php"]
  },
  "extra": {
    "laravel": {
      "providers": ["Modules\\ECommerce\\Providers\\ECommerceServiceProvider"]
    }
  }
}
```

**module.json**:

```json
{
  "name": "ECommerce",
  "display_name": "E-Commerce",
  "version": "1.0.0",
  "description": "Full-featured e-commerce solution",
  "author": "BaultFrame Team",
  "enabled": false
}
```

**Cài đặt**:

```bash
# System sẽ tự động detect composer.json và merge vào root
php cli module:sync
php cli module:composer --install=ECommerce
```

### Example 3: Module with Private Repository

**composer.json**:

```json
{
  "name": "mycompany/custom-module",
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/mycompany/private-package.git"
    }
  ],
  "require": {
    "mycompany/private-package": "^1.0"
  }
}
```

**Cài đặt**:

```bash
# Configure GitHub token first
composer config -g github-oauth.github.com YOUR_TOKEN

# Install module
php cli module:composer --install=CustomModule
```

## Troubleshooting Guide

### Issue: Dependencies không được cài

**Check**:

```bash
# 1. Check module status
php cli module:composer --check=ModuleName

# 2. Check queue worker
ps aux | grep "queue:work"

# 3. Check failed jobs
php cli queue:failed

# 4. Check logs
tail -f storage/logs/bault-*.log | grep ModuleName
```

**Solution**:

```bash
# Start queue worker if not running
php cli queue:work &

# Retry failed job
php cli queue:retry {job-id}

# Or manually install
php cli module:composer --install=ModuleName
```

### Issue: Composer timeout

**Solution**:

```bash
# Increase timeout (edit ComposerDependencyManager.php)
private const COMPOSER_TIMEOUT = 1200; // 20 minutes

# Or run manually
cd /path/to/project
composer require package/name --timeout=1200
```

### Issue: Version conflict

**Solution**:

```bash
# Check what's blocking
composer why-not package/name version

# Try updating other packages
composer update --with-all-dependencies

# Or adjust version constraint in module.json/composer.json
```

## Performance Tips

### 1. Use Composer Cache

```bash
# Composer tự động cache packages
# Check cache location
composer config cache-dir

# Clear cache if needed
composer clear-cache
```

### 2. Optimize Autoload

```bash
# Always run after installing dependencies
composer dump-autoload --optimize
```

### 3. Use Packagist Mirror (Optional)

```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://mirrors.aliyun.com/composer/"
    }
  ]
}
```

### 4. Pre-download Dependencies

```bash
# Pre-download common packages to cache
composer global require popular/package
```

## Security Considerations

### 1. Validate Package Sources

- Chỉ cài packages từ Packagist hoặc trusted sources
- Kiểm tra package trước khi thêm vào dependencies
- Review code của packages bên thứ 3

### 2. Pin Versions in Production

```json
{
  "require": {
    "vendor/package": "2.1.5" // Exact version in production
  }
}
```

### 3. Use Composer Lock File

```bash
# Always commit composer.lock
git add composer.lock
git commit -m "Lock dependencies"

# Deploy với composer install (not update)
composer install --no-dev --optimize-autoloader
```

### 4. Scan for Vulnerabilities

```bash
# Using Composer audit (Composer 2.4+)
composer audit

# Or use third-party tools
composer require --dev roave/security-advisories:dev-latest
```

## API Documentation

### ComposerDependencyManager Methods

| Method                        | Parameters                                                   | Returns | Description                     |
| ----------------------------- | ------------------------------------------------------------ | ------- | ------------------------------- |
| `installDependencies()`       | `string $moduleName, ?array $dependencies, bool $updateOnly` | `array` | Install module dependencies     |
| `removeDependencies()`        | `string $moduleName, array $packages`                        | `array` | Remove module dependencies      |
| `validateModuleComposer()`    | `string $modulePath`                                         | `array` | Validate module's composer.json |
| `checkComposerInstallation()` | -                                                            | `array` | Check if Composer is installed  |
| `dumpAutoload()`              | `bool $optimize`                                             | `array` | Regenerate autoload files       |

### Return Format

All methods return standardized array:

```php
[
    'status' => 'success' | 'error',
    'message' => 'Human readable message',
    'data' => [...], // Optional additional data
]
```

## Conclusion

Hệ thống quản lý Composer dependencies cho modules cung cấp:

- ✅ Tự động hóa hoàn toàn
- ✅ An toàn với rollback support
- ✅ Linh hoạt với 2 options (module.json / composer.json)
- ✅ Dễ sử dụng qua Web UI và CLI
- ✅ Robust error handling
- ✅ Production-ready

**Next Steps**:

1. Đọc examples trong `docs/examples/`
2. Test với module đơn giản trước
3. Deploy lên staging để test thoroughly
4. Monitor logs và performance

Happy coding! 🚀
