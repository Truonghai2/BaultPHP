# WebAssembly Integration Applied

## Summary

WebAssembly has now been applied to real system workflows using the existing
`Core\WebAssembly` classes. The integration focuses on image processing, where
WASM can deliver significant performance improvements while keeping a safe
PHP fallback.

## Applied Areas

### 1. User Avatar Processing

**File:** `Modules/User/Http/Controllers/ProfileController.php`

- Avatar resize now uses `WasmImageProcessor`.
- If WASM is unavailable, it falls back to PHP (Intervention Image).
- Output is stored to `storage/app/public/avatars/*`.

### 2. CMS Media Thumbnails (on-demand)

**File:** `Modules/Cms/Infrastructure/Models/MediaFile.php`

- `getThumbnailUrl()` now generates thumbnails via WASM if missing.
- Uses `wasm_image()->resize(...)` with configurable paths.
- Falls back to original image on failure.

### 3. Config Added

**File:** `config/wasm.php`

Key settings:
- `wasm_directory`
- `cache_enabled`
- `fallback_to_php`
- `image.thumbnail_dir`
- `image.thumbnail_url_prefix`

## Notes

- WASM runs through `WasmExecutor` → `WasmRuntime`.
- If no runtime is available, the system logs a warning and uses fallback.
- You can control behavior via environment variables:
  - `WASM_ENABLED`
  - `WASM_CACHE_ENABLED`
  - `WASM_FALLBACK_TO_PHP`
  - `WASM_DIR`
  - `WASM_THUMB_DIR`
  - `WASM_THUMB_URL`

## Next Suggested Extensions

- Apply `WasmCalculator` for heavy analytics tasks.
- Add batch thumbnail generation CLI command.
- Integrate WASM in CMS image optimization pipeline.

