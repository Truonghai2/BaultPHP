<?php

declare(strict_types=1);

namespace Core\Tenancy;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the current tenant from the request (header, subdomain, or user).
 */
class TenantResolver
{
    public function __construct(
        private readonly TenantResolverInterface $strategy,
    ) {
    }

    /**
     * Resolve tenant id (or slug) for the request. Returns null if none.
     */
    public function resolve(ServerRequestInterface $request): ?string
    {
        return $this->strategy->resolve($request);
    }

    /**
     * Resolve tenant id from slug (lookup in DB). Returns null if not found.
     */
    public function resolveIdFromSlug(string $slug): ?int
    {
        $tenant = Tenant::where('slug', $slug)->first();
        return $tenant?->id;
    }
}
