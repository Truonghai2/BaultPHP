<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Core\Support\Context;
use Core\Tenancy\TenantResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves current tenant from request and sets tenant_id in context.
 */
class ResolveTenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TenantResolver $resolver,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!config('tenancy.enabled', false)) {
            return $handler->handle($request);
        }

        $slugOrId = $this->resolver->resolve($request);
        if ($slugOrId !== null && $slugOrId !== '') {
            $tenantId = is_numeric($slugOrId) ? (int) $slugOrId : $this->resolver->resolveIdFromSlug($slugOrId);
            if ($tenantId !== null) {
                Context::set('tenant_id', $tenantId);
            }
        }

        return $handler->handle($request);
    }
}
