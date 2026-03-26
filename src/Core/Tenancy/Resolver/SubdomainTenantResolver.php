<?php

declare(strict_types=1);

namespace Core\Tenancy\Resolver;

use Core\Tenancy\TenantResolverInterface;
use Psr\Http\Message\ServerRequestInterface;

class SubdomainTenantResolver implements TenantResolverInterface
{
    public function __construct(
        private string $baseHost = '',
        private string $delimiter = '.',
    ) {
        if ($this->baseHost === '' && isset($_SERVER['HTTP_HOST'])) {
            $this->baseHost = (string) $_SERVER['HTTP_HOST'];
        }
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        $host = $request->getHeaderLine('Host');
        if ($host === '') {
            return null;
        }
        $parts = explode($this->delimiter, trim($host), 2);
        if (count($parts) < 2) {
            return null;
        }
        $subdomain = strtolower($parts[0]);
        if ($subdomain === 'www' || $subdomain === '') {
            return null;
        }
        return $subdomain;
    }
}
