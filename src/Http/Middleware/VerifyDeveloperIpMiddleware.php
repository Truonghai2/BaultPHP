<?php

namespace App\Http\Middleware;

use App\Http\ResponseFactory;
use Core\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class VerifyDeveloperIpMiddleware implements MiddlewareInterface
{
    /**
     * Danh sách các IP được tin tưởng.
     *
     * @var string[]
     */
    protected array $ips;

    public function __construct(protected ResponseFactory $responseFactory, Config $config)
    {
        $ipsString = $config->get('app.developer_ips', '');
        $this->ips = $ipsString ? array_filter(array_map('trim', explode(',', $ipsString))) : [];
    }

    /**
     * Xử lý một request đến.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (empty($this->ips) && config('app.env') !== 'local') {
            return $this->responseFactory->json(['error' => 'Access denied. No developer IPs configured.'], 403);
        }

        if (empty($this->ips)) {
            return $handler->handle($request);
        }

        $clientIp = $this->getClientIp($request);

        if (!in_array($clientIp, $this->ips)) {
            return $this->responseFactory->json(['error' => 'Forbidden.'], 403);
        }

        return $handler->handle($request);
    }

    /**
     * Lấy địa chỉ IP của client từ request.
     */
    protected function getClientIp(ServerRequestInterface $request): ?string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? null;
    }
}
