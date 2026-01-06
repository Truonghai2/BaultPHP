<?php

namespace App\Http\Cors;

use Core\Contracts\StatefulService;
use Psr\SimpleCache\CacheInterface;

/**
 * Quản lý và validate CORS origins.
 * Hỗ trợ wildcards, patterns, và caching.
 */
class CorsOriginsManager implements StatefulService
{
    private ?array $allowedOrigins = null;
    private array $validatedCache = [];
    private array $config;
    private array $originsConfig;

    public function __construct(
        private ?CacheInterface $cache = null
    ) {
        $this->config = config('cors', []);
        $this->originsConfig = config('cors-origins', []);
    }

    /**
     * Kiểm tra xem origin có được phép không.
     */
    public function isAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        // Normalize origin: lowercase và remove trailing slash
        $normalizedOrigin = rtrim(strtolower($origin), '/');

        // Kiểm tra cache runtime
        if (isset($this->validatedCache[$normalizedOrigin])) {
            return $this->validatedCache[$normalizedOrigin];
        }

        $result = $this->validateOrigin($normalizedOrigin);
        $this->validatedCache[$normalizedOrigin] = $result;

        return $result;
    }

    /**
     * Validate origin dựa trên config và rules.
     */
    private function validateOrigin(string $origin): bool
    {
        $allowedOrigins = $this->getAllowedOrigins();

        // 1. Kiểm tra wildcard '*' (cho phép tất cả)
        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        // 2. Kiểm tra exact match
        if (in_array($origin, $allowedOrigins, true)) {
            return $this->validateAgainstRules($origin);
        }

        // 3. Kiểm tra wildcard subdomain (*.example.com)
        foreach ($allowedOrigins as $allowed) {
            if ($this->matchWildcard($origin, $allowed)) {
                return $this->validateAgainstRules($origin);
            }
        }

        // 4. Kiểm tra patterns (regex)
        $patterns = $this->originsConfig['patterns'] ?? [];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return $this->validateAgainstRules($origin);
            }
        }

        return false;
    }

    /**
     * Match origin với wildcard pattern.
     * Ví dụ: *.example.com sẽ match với app.example.com, api.example.com
     */
    private function matchWildcard(string $origin, string $pattern): bool
    {
        if (!str_contains($pattern, '*')) {
            return false;
        }

        // Chuyển *.example.com thành regex: ^https?://([a-z0-9\-]+\.)?example\.com$
        $regex = '/^' . str_replace(
            ['*', '.'],
            ['([a-z0-9\-]+\.)?', '\.'],
            preg_quote($pattern, '/')
        ) . '$/i';

        return preg_match($regex, $origin) === 1;
    }

    /**
     * Validate origin theo các rules bổ sung.
     */
    private function validateAgainstRules(string $origin): bool
    {
        $rules = $this->originsConfig['rules'] ?? [];
        $isProduction = config('app.env') === 'production';

        // Rule: Block insecure protocol in production
        if (
            $isProduction &&
            ($rules['block_insecure_in_production'] ?? false) &&
            str_starts_with($origin, 'http://')
        ) {
            return false;
        }

        // Rule: Require HTTPS in production
        if (
            $isProduction &&
            ($rules['require_https_in_production'] ?? false) &&
            !str_starts_with($origin, 'https://')
        ) {
            return false;
        }

        // Rule: Block IP addresses
        if ($rules['block_ip_addresses'] ?? false) {
            $host = parse_url($origin, PHP_URL_HOST);
            if ($host && filter_var($host, FILTER_VALIDATE_IP)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lấy danh sách tất cả origins được phép.
     * Sử dụng cache để tối ưu hiệu suất.
     */
    private function getAllowedOrigins(): array
    {
        if ($this->allowedOrigins !== null) {
            return $this->allowedOrigins;
        }

        // Thử lấy từ cache
        $cacheConfig = $this->originsConfig['cache'] ?? [];
        if (($cacheConfig['enabled'] ?? false) && $this->cache) {
            $cacheKey = $cacheConfig['key'] ?? 'cors_allowed_origins';
            $cached = $this->cache->get($cacheKey);
            
            if (is_array($cached)) {
                $this->allowedOrigins = $cached;
                return $this->allowedOrigins;
            }
        }

        // Load từ config
        $origins = $this->loadOriginsFromConfig();

        // Lưu vào cache
        if (($cacheConfig['enabled'] ?? false) && $this->cache) {
            $cacheKey = $cacheConfig['key'] ?? 'cors_allowed_origins';
            $ttl = $cacheConfig['ttl'] ?? 3600;
            $this->cache->set($cacheKey, $origins, $ttl);
        }

        $this->allowedOrigins = $origins;
        return $this->allowedOrigins;
    }

    /**
     * Load origins từ config file.
     */
    private function loadOriginsFromConfig(): array
    {
        $origins = $this->originsConfig['allowed'] ?? [];
        
        // Fallback to old config if cors-origins.php doesn't exist
        if (empty($origins)) {
            $origins = $this->config['allowed_origins'] ?? [];
        }

        // Normalize: loại bỏ trailing slash, lowercase
        return array_unique(
            array_map(
                fn($origin) => rtrim(strtolower($origin), '/'),
                array_filter($origins, fn($o) => !empty($o))
            )
        );
    }

    /**
     * Lấy danh sách tất cả origins (cho mục đích debug/admin).
     */
    public function getAllOrigins(): array
    {
        return $this->getAllowedOrigins();
    }

    /**
     * Thêm origin mới (runtime only, không lưu vào file).
     */
    public function addOrigin(string $origin): void
    {
        $this->allowedOrigins = $this->getAllowedOrigins();
        $this->allowedOrigins[] = rtrim(strtolower($origin), '/');
        $this->allowedOrigins = array_unique($this->allowedOrigins);
        
        // Clear cache
        $this->clearCache();
    }

    /**
     * Xóa origin (runtime only).
     */
    public function removeOrigin(string $origin): void
    {
        $this->allowedOrigins = $this->getAllowedOrigins();
        $origin = rtrim(strtolower($origin), '/');
        
        $this->allowedOrigins = array_filter(
            $this->allowedOrigins,
            fn($o) => $o !== $origin
        );
        
        // Clear cache
        $this->clearCache();
    }

    /**
     * Clear cache.
     */
    public function clearCache(): void
    {
        $this->allowedOrigins = null;
        $this->validatedCache = [];
        
        if ($this->cache) {
            $cacheConfig = $this->originsConfig['cache'] ?? [];
            $cacheKey = $cacheConfig['key'] ?? 'cors_allowed_origins';
            $this->cache->delete($cacheKey);
        }
    }

    /**
     * Reset state cho StatefulService.
     */
    public function resetState(): void
    {
        $this->validatedCache = [];
        // Không reset $allowedOrigins vì nó được cache cho toàn bộ worker
    }

    /**
     * Lấy origin header hợp lệ để trả về trong response.
     * Nếu origin không được phép, trả về null.
     */
    public function getAllowedOriginHeader(?string $requestOrigin): ?string
    {
        if (empty($requestOrigin)) {
            return null;
        }

        $normalizedOrigin = rtrim(strtolower($requestOrigin), '/');
        
        if ($this->isAllowed($normalizedOrigin)) {
            return $requestOrigin; // Trả về original case
        }

        return null;
    }
}

