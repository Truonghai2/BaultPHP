<?php

declare(strict_types=1);

namespace Core\Tenancy;

use Psr\Http\Message\ServerRequestInterface;

interface TenantResolverInterface
{
    /**
     * Return tenant identifier (id as string, or slug) for the request. Null if none.
     */
    public function resolve(ServerRequestInterface $request): ?string;
}
