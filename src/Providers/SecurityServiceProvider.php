<?php

namespace App\Providers;

use Core\Security\ThreatDetector;
use Core\Security\ZeroTrustAuth;
use Core\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Zero-Trust Authentication
        $this->app->singleton(ZeroTrustAuth::class, function ($app) {
            $config = config('security.zerotrust', []);
            return new ZeroTrustAuth($config);
        });

        // Register Threat Detector
        $this->app->singleton(ThreatDetector::class, function ($app) {
            $config = config('security.threat_detection', []);
            return new ThreatDetector($config);
        });
    }
}
