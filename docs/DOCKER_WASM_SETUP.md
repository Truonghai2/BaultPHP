# Docker WASM Setup Guide

## Tổng quan

Hướng dẫn setup WebAssembly (WASM) trong Docker environment cho BaultFrame.

## Kiến trúc Docker

### WASM Runtime trong Docker

Framework đã được tích hợp sẵn WASM runtime (wasmtime) trong Docker image:

- **WASM Runtime**: wasmtime (installed trong Dockerfile)
- **WASM Directory**: `/app/wasm` (mounted volume)
- **Auto-detection**: Tự động detect runtime khi container start

## Quick Start

### 1. Build Docker Image

```bash
docker-compose build app
```

WASM runtime sẽ được tự động cài đặt trong quá trình build.

### 2. Start Containers

```bash
docker-compose up -d
```

### 3. Verify WASM Installation

```bash
# Check inside container
docker-compose exec app wasmtime --version

# Or run verification script
docker-compose exec app bash docker/scripts/verify-wasm.sh
```

### 4. Add WASM Modules

Place your `.wasm` files in the `wasm/` directory:

```bash
# On host machine
mkdir -p wasm
cp your-module.wasm wasm/

# Or mount from host
# The wasm directory is already mounted as a volume
```

## Docker Configuration

### Environment Variables

Thêm vào `.env` hoặc `docker-compose.yml`:

```env
# WASM Configuration
WASM_RUNTIME=wasmtime
WASM_RUNTIME_PATH=/usr/local/bin/wasmtime
WASM_DIRECTORY=wasm
WASM_CACHE_ENABLED=true
WASM_CACHE_TTL=3600
WASM_FALLBACK_TO_PHP=true
```

### Volume Mounting

WASM modules directory được mount như một volume:

```yaml
volumes:
  - wasm-modules:/app/wasm
```

Điều này cho phép:
- Persist WASM modules giữa các container restarts
- Share WASM modules giữa các containers
- Easy updates không cần rebuild image

## Development Workflow

### 1. Local Development

```bash
# Start containers
docker-compose up -d

# Add WASM module
cp my-module.wasm wasm/

# Test in container
docker-compose exec app php -r "echo wasm('my-module.wasm', []);"
```

### 2. Hot Reload

WASM modules được mount từ host, nên changes sẽ được reflect ngay:

```bash
# Update WASM module on host
cp updated-module.wasm wasm/

# No need to restart container
# Changes are immediately available
```

### 3. Testing WASM Modules

```bash
# Test WASM module directly
docker-compose exec app wasmtime run wasm/my-module.wasm

# Test via PHP
docker-compose exec app php cli wasm:test my-module.wasm
```

## Production Deployment

### 1. Build Production Image

```bash
docker build --build-arg APP_ENV=production -t bault-app:latest .
```

### 2. Include WASM Modules in Image

Nếu muốn bundle WASM modules trong image:

```dockerfile
# In Dockerfile
COPY wasm/*.wasm /app/wasm/
```

Hoặc sử dụng volume mounting (recommended):

```yaml
volumes:
  - ./wasm:/app/wasm:ro  # Read-only in production
```

### 3. Verify in Production

```bash
docker-compose exec app docker/scripts/verify-wasm.sh
```

## Troubleshooting

### WASM Runtime Not Found

**Error:** `WASM runtime is not available`

**Solution:**
```bash
# Rebuild image
docker-compose build --no-cache app

# Or install manually in container
docker-compose exec app bash docker/scripts/install-wasm.sh
```

### Permission Denied

**Error:** `Permission denied` khi access WASM files

**Solution:**
```bash
# Fix permissions
docker-compose exec app chown -R appuser:appgroup /app/wasm
docker-compose exec app chmod -R 755 /app/wasm
```

### WASM Module Not Found

**Error:** `WASM file not found`

**Solution:**
1. Check file exists: `docker-compose exec app ls -la /app/wasm/`
2. Check file permissions
3. Verify path in `config/wasm.php`

### Performance Issues

**Issue:** WASM execution is slow

**Solution:**
1. Check if WASM runtime is available: `docker-compose exec app wasmtime --version`
2. Verify cache is enabled: `WASM_CACHE_ENABLED=true`
3. Check container resources: `docker stats`

## Advanced Configuration

### Custom WASM Runtime Path

Nếu muốn sử dụng custom WASM runtime:

```yaml
environment:
  - WASM_RUNTIME_PATH=/custom/path/to/wasmtime
```

### Multiple WASM Directories

Mount multiple directories:

```yaml
volumes:
  - ./wasm:/app/wasm
  - ./custom-wasm:/app/custom-wasm
```

Update `config/wasm.php`:

```php
'wasm_directory' => env('WASM_DIRECTORY', base_path('wasm')),
'modules' => [
    'module1' => base_path('wasm/module1.wasm'),
    'module2' => base_path('custom-wasm/module2.wasm'),
],
```

### WASM Runtime Selection

Switch between different runtimes:

```yaml
environment:
  - WASM_RUNTIME=wasmtime  # or wasmer, wavm, php-ext
```

## Docker Compose Examples

### Development Setup

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
      args:
        - APP_ENV=local
    volumes:
      - .:/app
      - wasm-modules:/app/wasm
    environment:
      - WASM_CACHE_ENABLED=false  # Disable cache in dev
      - WASM_FALLBACK_TO_PHP=true
```

### Production Setup

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
      args:
        - APP_ENV=production
    volumes:
      - wasm-modules:/app/wasm:ro  # Read-only in production
    environment:
      - WASM_CACHE_ENABLED=true
      - WASM_CACHE_TTL=7200  # 2 hours
      - WASM_FALLBACK_TO_PHP=false  # Fail fast in production
```

## Monitoring

### Check WASM Runtime Status

```bash
# In container
docker-compose exec app php -r "var_dump(wasm_available());"
docker-compose exec app php -r "var_dump(wasm()->getRuntimeInfo());"
```

### Logs

WASM execution logs are available in application logs:

```bash
docker-compose logs app | grep WASM
```

## Best Practices

### 1. Version Control

- ✅ Commit WASM modules to git (if small)
- ✅ Use `.gitignore` for large WASM files
- ✅ Document WASM module versions

### 2. Security

- ✅ Validate WASM files before execution
- ✅ Use read-only volumes in production
- ✅ Set appropriate file permissions

### 3. Performance

- ✅ Enable caching in production
- ✅ Use appropriate cache TTL
- ✅ Monitor WASM execution times

### 4. Development

- ✅ Use volume mounting for hot reload
- ✅ Test WASM modules before deployment
- ✅ Use fallback to PHP in development

## Scripts

### Install WASM Runtime

```bash
docker-compose exec app bash docker/scripts/install-wasm.sh
```

### Verify Installation

```bash
docker-compose exec app bash docker/scripts/verify-wasm.sh
```

### Test WASM Module

```bash
docker-compose exec app wasmtime run wasm/test-module.wasm
```

## Resources

- [Wasmtime Documentation](https://docs.wasmtime.dev/)
- [Docker Best Practices](https://docs.docker.com/develop/dev-best-practices/)
- [WASM Integration Guide](../docs/WASM_INTEGRATION_GUIDE.md)
