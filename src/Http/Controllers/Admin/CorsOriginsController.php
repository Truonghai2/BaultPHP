<?php

namespace App\Http\Controllers\Admin;

use App\Http\Cors\CorsOriginsManager;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

/**
 * Controller để quản lý CORS origins qua API.
 * Hữu ích cho admin panel để quản lý origins động.
 */
#[Route(prefix: '/api/admin/cors', group: 'api')]
class CorsOriginsController extends Controller
{
    public function __construct(
        private CorsOriginsManager $originsManager
    ) {
    }

    /**
     * Lấy danh sách tất cả origins được phép.
     */
    #[Route(uri: '/origins', method: 'GET', name: 'admin.cors.origins.index')]
    public function index(): array
    {
        return [
            'success' => true,
            'data' => [
                'origins' => $this->originsManager->getAllOrigins(),
                'count' => count($this->originsManager->getAllOrigins()),
            ],
        ];
    }

    /**
     * Kiểm tra xem một origin có được phép không.
     */
    #[Route(uri: '/origins/check', method: 'POST', name: 'admin.cors.origins.check')]
    public function check(): array
    {
        $origin = request()->input('origin');

        if (empty($origin)) {
            return [
                'success' => false,
                'message' => 'Origin is required',
            ];
        }

        $isAllowed = $this->originsManager->isAllowed($origin);

        return [
            'success' => true,
            'data' => [
                'origin' => $origin,
                'is_allowed' => $isAllowed,
            ],
        ];
    }

    /**
     * Clear cache của origins.
     */
    #[Route(uri: '/origins/cache/clear', method: 'POST', name: 'admin.cors.origins.cache.clear')]
    public function clearCache(): array
    {
        $this->originsManager->clearCache();

        return [
            'success' => true,
            'message' => 'CORS origins cache cleared successfully',
        ];
    }

    /**
     * Lấy thông tin chi tiết về CORS configuration.
     */
    #[Route(uri: '/info', method: 'GET', name: 'admin.cors.info')]
    public function info(): array
    {
        $config = config('cors');
        $originsConfig = config('cors-origins');

        return [
            'success' => true,
            'data' => [
                'cors' => [
                    'supports_credentials' => $config['supports_credentials'] ?? false,
                    'max_age' => $config['max_age'] ?? 0,
                    'allowed_methods' => $config['allowed_methods'] ?? [],
                    'allowed_headers' => $config['allowed_headers'] ?? [],
                    'exposed_headers' => $config['exposed_headers'] ?? [],
                ],
                'origins' => [
                    'count' => count($this->originsManager->getAllOrigins()),
                    'cache_enabled' => $originsConfig['cache']['enabled'] ?? false,
                    'cache_ttl' => $originsConfig['cache']['ttl'] ?? 0,
                    'rules' => $originsConfig['rules'] ?? [],
                ],
                'environment' => config('app.env'),
            ],
        ];
    }
}

