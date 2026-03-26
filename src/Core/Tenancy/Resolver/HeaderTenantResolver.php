<?php

declare(strict_types=1);

namespace Core\Tenancy\Resolver;

use Core\Tenancy\TenantResolverInterface;
use Psr\Http\Message\ServerRequestInterface;

class HeaderTenantResolver implements TenantResolverInterface
{
    public function __construct(
        private string $headerName = 'X-Tenant-Id',
    ) {
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        $value = $request->getHeaderLine($this->headerName);
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
