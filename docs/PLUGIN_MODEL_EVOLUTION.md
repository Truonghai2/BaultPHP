# Hướng tiến hóa mô hình Plugin/Module lên hiện đại

Tài liệu gợi ý các hướng nâng cấp mô hình plugin (module) hiện tại của BaultFrame.

---

## 1. Mô hình hiện tại (tóm tắt)

- **Discovery:** Quét `Modules/*/module.json` → `enabled: true` → load `providers`.
- **Đăng ký:** Service providers, routes, commands, blocks (CMS) theo từng module.
- **Cấu hình:** `module.json` (name, version, enabled, providers), có thể mở rộng `require`, dependencies.

---

## 2. Các hướng tiến hóa

### 2.1. **Manifest & contract chuẩn (Open Plugin Standard)**

- **Ý tưởng:** Chuẩn hóa `module.json` thành manifest có schema rõ ràng (JSON Schema), version API, lifecycle hooks.
- **Lợi ích:** Plugin từ bên thứ ba cài vào dễ tương thích; tooling (IDE, CLI) validate được.
- **Gợi ý:**
  - Thêm `api_version`, `min_core_version`, `capabilities`, `permissions`.
  - Lifecycle: `installing` → `enabled` → `disabling` → `uninstalling` (hooks trong ModuleServiceProvider).
  - Schema `module.schema.json` và validate khi enable/install.

### 2.2. **Sandbox & permissions** (đã triển khai cơ bản)

- **Ý tưởng:** Plugin chạy với quyền tối thiểu: chỉ được gọi API/extension points đã khai báo; hạn chế filesystem, network, DB.
- **Lợi ích:** Bảo mật khi cài plugin không tin cậy (marketplace).
- **Gợi ý:**
  - Trong manifest: `permissions: ["cache:read", "storage:write", "events:subscribe"]`.
  - Runtime: wrapper/proxy cho Cache, DB, HTTP client theo permissions.
  - Tùy chọn: chạy logic “không tin cậy” trong process riêng hoặc WebAssembly (xem 2.6).

- **Đã làm (2.2):** Manifest `permissions`; ModuleContext + ModulePermissionRegistry + ModulePermissionGate; extension handlers chạy trong context module; khi bật `module_sandbox.enabled` cache wrap (SandboxedCacheStore) enforce cache:read/cache:write. Xem `docs/MODULE_SANDBOX.md`.

### 2.3. **Event-driven & extension points**

- **Ý tưởng:** Thay vì chỉ “đăng ký provider”, core phát events/extension points; plugin subscribe hoặc đăng ký handler.
- **Lợi ích:** Core ổn định, plugin không phụ thuộc chi tiết implementation; dễ thêm/bật/tắt tính năng.
- **Gợi ích:**
  - Events: `module.beforeBoot`, `request.authenticated`, `block.beforeRender`, `acl.permissionCheck`.
  - Extension points dạng registry: `$app->extend('view.global_data', fn() => [...])`.
  - Plugin chỉ listen/extend, không sửa core.

### 2.4. **Lazy loading & on-demand activation** (đã triển khai)

- **Ý tưởng:** Không load toàn bộ module khi app start; load khi route/feature của module được gọi.
- **Lợi ích:** Giảm memory và thời gian bootstrap; nhiều module nhưng chỉ dùng vài cái.
- **Gợi ý:**
  - Trong manifest: `activate`: `"on_request"` | `"on_boot"` | `"on_first_use"`.
  - Route prefix hoặc tag (ví dụ `module:user`) → khi match request mới require providers của module đó.
  - Cache danh sách “module cho route X” để tránh quét lại mỗi request.

- **Đã triển khai:** `activate` + `route_prefix`/`route_prefixes` trong manifest; `ProviderRepository::getBootProviders()`; `LazyModuleLoader` + cache `lazy_modules.php`; `Application::registerProviderAndBoot()`.

### 2.5. **Composer / package-based plugins** (đã triển khai)

- **Ý tưởng:** Plugin là package Composer; cài bằng `composer require vendor/bault-module-user`.
- **Lợi ích:** Quản lý phiên bản, dependency, autoload chuẩn; tái dùng ecosystem Composer.
- **Đã làm:** Package type `bault-module` hoặc `extra.bault.module` (name, providers); `ComposerModuleDiscovery` quét `vendor/`; `ModuleManifest::fromComposerPackage()`; `ModulePathResolver` cho path events/extensions/manifest; `modules.composer_disabled` để tắt. Xem `docs/COMPOSER_MODULES.md`.

### 2.6. **WebAssembly (Wasm) cho logic plugin** (đã triển khai)

- **Ý tưởng:** Logic “nhẹ” của plugin (rule, transform, filter) biên dịch sang Wasm; chạy trong sandbox, không chạy arbitrary PHP trong process chính.
- **Lợi ích:** Cách ly, hiệu năng ổn định; có thể dùng ngôn ngữ khác (Rust, Go) để viết plugin.
- **Đã làm:** Config `plugin_abi` (stdio/invoke), resolve `ModuleName/plugin` → `Modules/ModuleName/wasm/plugin.wasm`, helper `wasm_plugin('ModuleName', 'plugin_name', $input)`. Xem `docs/WASM_INTEGRATION_GUIDE.md` (Plugin ABI).

### 2.7. **Admin UI & marketplace** (đã triển khai)

- **Ý tưởng:** Trong admin: danh sách module, bật/tắt, cài từ marketplace (zip/URL hoặc package name).
- **Lợi ích:** Trải nghiệm người dùng tốt; có thể monétize (marketplace trả phí).
- **Đã làm:**
  - **Admin UI:** Trang `/admin/modules` có tab "Installed" (bảng module, Enable/Disable, Delete, Upload ZIP) và tab "Marketplace" (catalog từ registry, nút Cài đặt).
  - **API:** `GET /api/admin/modules` (list), `POST /api/admin/modules` (install ZIP), `PUT /api/admin/modules/{name}` (toggle), `DELETE /api/admin/modules/{name}` (delete); `GET /api/admin/marketplace/catalog`, `POST /api/admin/marketplace/install` (body: `url` hoặc `id`). Signature/hash kiểm tra trong `ModuleInstallerService`.
  - **Marketplace:** `config/marketplace.php` (registry_url, enabled, timeout, token); `ModuleMarketplaceService` lấy catalog JSON và tải ZIP theo URL; `ModuleInstallerService::installFromUrl()` tải về temp rồi gọi `install()`. Registry JSON: `{ "modules": [ { "id", "name", "version", "description", "download_url", "package?", "permissions?" } ] }`.
  - **Biến môi trường:** `MODULE_MARKETPLACE_ENABLED`, `MODULE_MARKETPLACE_REGISTRY_URL`, `MODULE_MARKETPLACE_TIMEOUT`, `MODULE_MARKETPLACE_TOKEN` (tùy chọn).

### 2.8. **Multi-tenant & per-tenant plugins** (đã triển khai)

- **Ý tưởng:** Mỗi tenant có thể bật/tắt tập con plugin; config plugin theo tenant.
- **Lợi ích:** SaaS: tenant A dùng User+Cms, tenant B thêm module riêng.
- **Đã làm:**
  - **DB:** Migration `tenants` (id, name, slug, config), `tenant_modules` (tenant_id, module_name, enabled, config). Unique (tenant_id, module_name).
  - **Config:** `config/tenancy.php` — enabled, resolution (header | subdomain), header_name (X-Tenant-Id), when_missing (global | strict).
  - **Models:** `Core\Tenancy\Tenant`, `Core\Tenancy\TenantModule`. Resolver: header (X-Tenant-Id) hoặc subdomain; slug → tenant_id.
  - **Middleware:** `ResolveTenantMiddleware` — resolve tenant từ request, set `Context::set('tenant_id', $id)`.
  - **TenantModuleResolver:** `getEnabledModuleNames()` = global enabled ∩ (khi có tenant: tenant_modules.enabled=1). `ProviderRepository::getEnabledManifests()` dùng resolver → chỉ load manifest của module enabled cho context hiện tại.
  - **Helpers:** `tenant()` (Tenant|null), `current_tenant_id()` (int|null); `Context::getTenantId()` / `setTenantId()`.
  - Khi tenancy tắt: hành vi như cũ (global enabled). Bật `TENANCY_ENABLED=true` và gửi header `X-Tenant-Id: <slug hoặc id>` để scope theo tenant.

### 2.9. **Declarative config & schema** (đã triển khai)

- **Ý tưởng:** Cấu hình plugin không chỉ `module.json` mà còn routes, permissions, menu khai báo dạng declarative (YAML/JSON).
- **Lợi ích:** Core đọc schema một lần; giảm code PHP boilerplate trong từng module.
- **Đã làm:** `Modules/<Name>/manifest.yaml` (hoặc `manifest.json`) với `routes`, `permissions`, `menu.admin`, `menu.frontend`. `DeclarativeConfigLoader` load theo module; `RouteServiceProvider` đăng ký declarative routes; menu gộp vào extension point navigation. Xem `docs/DECLARATIVE_CONFIG.md`.

### 2.10. **Hot reload & development DX**

- **Ý tưởng:** Trong môi trường dev, sửa code hoặc manifest của module thì tự động reload (clear cache, re-scan) không cần restart Swoole.
- **Lợi ích:** Vòng lặp phát triển nhanh.
- **Gợi ý:**
  - File watcher (đã có hạ tầng tương tự); khi đổi file trong `Modules/` → event → clear bootstrap/cache providers (và command cache nếu cần) → worker reload hoặc next request dùng discovery lại.
  - Chỉ bật khi `APP_DEBUG` hoặc flag `PLUGIN_HOT_RELOAD`.

---

## 3. Thứ tự ưu tiên gợi ý

| Ưu tiên | Hướng              | Effort | Impact |
|--------|--------------------|--------|--------|
| 1      | Manifest & lifecycle (2.1) | Trung bình | Cao – nền cho mọi thứ sau |
| 2      | Event-driven / extension points (2.3) | Trung bình | Cao – giảm coupling |
| 3      | Lazy loading (2.4) | Trung bình | Cao – performance |
| 4      | Declarative config (2.9) | Thấp–trung bình | Trung bình – DX |
| 5      | Sandbox/permissions (2.2) | Cao | Cao nếu có marketplace |
| 6      | Composer packages (2.5) | Trung bình | Trung bình – quen thuộc với dev |
| 7      | Admin UI & marketplace (2.7) | Cao | Kinh doanh |
| 8      | Wasm (2.6) | Cao | Cho use case sandbox/đa ngôn ngữ |
| 9      | Multi-tenant (2.8) | Cao | Khi product đi theo hướng SaaS |
| 10     | Hot reload (2.10) | Thấp | DX |

---

## 4. Kết luận

- **Hiện đại hóa “nhẹ”:** Tập trung (2.1) manifest + lifecycle và (2.3) events/extension points; sau đó (2.4) lazy loading và (2.9) declarative config.
- **Hướng product (marketplace/SaaS):** Thêm (2.2) sandbox, (2.7) marketplace, (2.8) multi-tenant.
- **Kỹ thuật cao:** Wasm (2.6) khi cần sandbox chặt hoặc logic plugin đa ngôn ngữ.

Bạn có thể chọn 1–2 hướng phù hợp roadmap rồi triển khai từng bước (manifest trước, sau đó events, rồi lazy load).
