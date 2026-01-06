# Auto-Sync Blocks Guide

## 🚀 Tính năng Auto-Sync Blocks

Hệ thống tự động đồng bộ block types và block instances vào database mà không cần chạy command thủ công.

## ✨ Cách hoạt động

### 1. **Auto-Sync Middleware**

- Middleware `AutoSyncBlocksMiddleware` được tự động đăng ký vào `web` middleware group
- Chỉ hoạt động trong môi trường `local` (development)
- Sử dụng cache 30 giây để tránh sync quá thường xuyên
- Không ảnh hưởng đến performance vì:
  - Chỉ chạy mỗi 30 giây một lần
  - Chỉ chạy khi có thay đổi (detect qua cache)
  - Silent fail nếu có lỗi (không break request)

### 2. **Quy trình sync tự động**

```
Request → AutoSyncBlocksMiddleware → Check cache (30s) → Sync if needed → Continue request
```

- **Lần 1**: Request đầu tiên → Sync blocks → Cache 30s
- **Lần 2-N**: Trong 30s tiếp theo → Sử dụng cache → Không sync
- **Sau 30s**: Request mới → Check lại → Sync nếu có thay đổi

## 📝 Cấu hình

### File: `config/cms.php`

```php
return [
    // Bật/tắt auto-sync (chỉ hoạt động trong local env)
    'auto_sync_blocks' => env('CMS_AUTO_SYNC_BLOCKS', true),

    // Thời gian cache sync (giây)
    'sync_cache_ttl' => env('CMS_SYNC_CACHE_TTL', 3600),
];
```

### File: `.env`

```env
# Bật auto-sync blocks (chỉ trong development)
CMS_AUTO_SYNC_BLOCKS=true

# Cache sync time (30 giây cho development, 3600 cho production)
CMS_SYNC_CACHE_TTL=30
```

## 🔧 Sử dụng

### Tạo block mới

1. **Tạo class Block mới**

```php
// Modules/Cms/Domain/Blocks/MyNewBlock.php
<?php

namespace Modules\Cms\Domain\Blocks;

class MyNewBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'my-new-block';
    }

    public function getTitle(): string
    {
        return 'My New Block';
    }

    public function getCategory(): string
    {
        return 'custom';
    }

    public function render(BlockInstance $instance, ?array $context = null): string
    {
        return '<div class="my-block">Hello World!</div>';
    }
}
```

2. **Register block trong BlockRegistry**

```php
// Modules/Cms/Domain/Services/BlockRegistry.php
public function __construct()
{
    $this->registerBlock(new MyNewBlock());
}
```

3. **Reload trang web** 🎉
   - Block tự động được sync vào database sau 30 giây
   - Không cần chạy command `php cli cms:sync-blocks`
   - Không cần restart server

### Chỉnh sửa block hiện có

1. **Sửa code trong class Block**

```php
public function render(BlockInstance $instance, ?array $context = null): string
{
    return '<div class="my-block">Updated content!</div>';
}
```

2. **Reload trang** (sau 30 giây)
   - Thay đổi được áp dụng tự động
   - Block metadata (title, description, config) được cập nhật

## ⚡ Force Sync ngay lập tức

Nếu bạn muốn sync ngay không đợi 30 giây:

### Cách 1: Dùng command

```bash
docker exec bault_app php cli cms:sync-blocks --force
```

### Cách 2: Clear cache

```bash
docker exec bault_app php cli cache:clear
```

### Cách 3: Helper function

```php
// Trong controller hoặc bất kỳ đâu
clear_block_sync_cache();
sync_blocks(force: true);
```

## 🎯 Best Practices

### Development (Local)

✅ **Nên làm:**

- Bật `CMS_AUTO_SYNC_BLOCKS=true`
- Set cache time ngắn: `CMS_SYNC_CACHE_TTL=30`
- Để middleware tự động sync
- Chỉ cần reload browser sau 30 giây

❌ **Không nên:**

- Chạy sync command thủ công (trừ khi cần ngay lập tức)
- Set cache time quá ngắn (< 10 giây) → ảnh hưởng performance

### Production

✅ **Nên làm:**

- Tắt auto-sync: `CMS_AUTO_SYNC_BLOCKS=false`
- Set cache time dài: `CMS_SYNC_CACHE_TTL=3600`
- Chạy sync command khi deploy:
  ```bash
  php cli cms:sync-blocks
  php cli cache:clear
  ```

❌ **Không nên:**

- Bật auto-sync trong production
- Quên chạy sync command khi deploy block mới

## 🐛 Troubleshooting

### Block không hiển thị sau khi tạo mới

**Nguyên nhân:** Cache chưa hết hoặc block chưa được register.

**Giải pháp:**

```bash
# 1. Check block đã register chưa
docker exec bault_app php cli cache:blocks

# 2. Force sync
docker exec bault_app php cli cms:sync-blocks --force

# 3. Clear all cache
docker exec bault_app php cli cache:clear

# 4. Reload server
docker exec bault_app php cli serve:reload
```

### Thay đổi code block không được áp dụng

**Nguyên nhân:** Opcache hoặc block instance cache.

**Giải pháp:**

```bash
# 1. Reload server để clear opcache
docker exec bault_app php cli serve:reload

# 2. Force sync blocks
docker exec bault_app php cli cms:sync-blocks --force
```

### Performance issue khi auto-sync

**Nguyên nhân:** Cache time quá ngắn.

**Giải pháp:**

```env
# Tăng cache time lên
CMS_SYNC_CACHE_TTL=60  # 1 phút thay vì 30 giây
```

## 📊 Monitoring

### Check sync status

```bash
# Xem log
docker exec bault_app tail -f storage/logs/bault-*.log | grep "Block sync"

# Check last sync time
docker exec bault_app php -r "echo date('Y-m-d H:i:s', last_block_sync());"

# Check if synced
docker exec bault_app php -r "echo blocks_synced() ? 'Yes' : 'No';"
```

### Helper functions

```php
// Check if blocks are synced
if (blocks_synced()) {
    echo "Blocks are synced!";
}

// Get last sync time
$lastSync = last_block_sync(); // Unix timestamp

// Clear sync cache
clear_block_sync_cache();

// Manual sync
$stats = sync_blocks(force: true);
```

## 🎨 Workflow Examples

### Example 1: Tạo block mới cho homepage

```bash
# 1. Tạo block class
cat > Modules/Cms/Domain/Blocks/PromoBannerBlock.php << 'EOF'
<?php
namespace Modules\Cms\Domain\Blocks;

class PromoBannerBlock extends AbstractBlock
{
    public function getName(): string { return 'promo-banner'; }
    public function getTitle(): string { return 'Promo Banner'; }
    public function getCategory(): string { return 'marketing'; }

    public function render(BlockInstance $instance, ?array $context = null): string
    {
        return '<div class="promo-banner">50% OFF!</div>';
    }
}
EOF

# 2. Register trong BlockRegistry
# (Thêm dòng: $this->registerBlock(new PromoBannerBlock());)

# 3. Đợi 30 giây hoặc force sync
docker exec bault_app php cli cms:sync-blocks --force

# 4. Tạo block instance qua admin panel hoặc seeder
# 5. Block tự động hiển thị trên trang!
```

### Example 2: Update block hiện có

```bash
# 1. Sửa code trong WelcomeBannerBlock.php
# 2. Đợi 30 giây
# 3. Reload browser → Thấy thay đổi ngay!

# Hoặc force sync nếu cần ngay:
docker exec bault_app php cli cms:sync-blocks --force
docker exec bault_app php cli serve:reload
```

## 🔐 Security Notes

- Auto-sync **chỉ hoạt động trong local environment**
- Production luôn phải sync thủ công khi deploy
- Middleware check `config('app.env') === 'local'` trước khi sync
- Silent fail để không expose lỗi trong production

## 📚 Related Commands

```bash
# Sync blocks
php cli cms:sync-blocks [--force]

# Cache blocks
php cli cache:blocks

# Clear cache
php cli cache:clear

# View all blocks
php cli cache:blocks

# Reload server
php cli serve:reload
```

## 💡 Tips

1. **Development workflow:**
   - Tạo block → Đợi 30s → Test
   - Hoặc: Tạo block → Force sync → Test ngay

2. **Giảm waiting time:**
   - Set `CMS_SYNC_CACHE_TTL=10` trong `.env` (10 giây)
   - Trade-off: Nhiều sync calls hơn

3. **Debug mode:**
   - Check logs: `storage/logs/bault-*.log`
   - Enable verbose: `APP_DEBUG=true`

4. **Hotkey suggestion:**
   - Tạo script để force sync nhanh:
   ```bash
   alias sync-blocks="docker exec bault_app php cli cms:sync-blocks --force"
   ```

---

**Happy coding! 🚀**
