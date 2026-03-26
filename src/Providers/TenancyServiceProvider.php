<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use Core\Tenancy\TenantResolver;
use Core\Tenancy\TenantResolverInterface;
use Core\Tenancy\Resolver\HeaderTenantResolver;
use Core\Tenancy\Resolver\SubdomainTenantResolver;
use Core\Tenancy\TenantModuleResolver;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantModuleResolver::class);

        $this->app->singleton(TenantResolverInterface::class, function ($app) {
            $resolution = config('tenancy.resolution', 'header');
            if ($resolution === 'subdomain') {
                return new SubdomainTenantResolver(
                    '',
                    config('tenancy.subdomain_slug_delimiter', '.')
                );
            }
            return new HeaderTenantResolver(config('tenancy.header_name', 'X-Tenant-Id'));
        });

        $this->app->singleton(TenantResolver::class, function ($app) {
            return new TenantResolver($app->make(TenantResolverInterface::class));
        });
    }
}

