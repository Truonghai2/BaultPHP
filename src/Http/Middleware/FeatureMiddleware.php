<?php

namespace App\Http\Middleware;

use Closure;
use Core\Features\FeatureManager;
use Core\Http\Exceptions\NotFoundException;
use Psr\Http\Message\ServerRequestInterface;

class FeatureMiddleware
{
    public function __construct(protected FeatureManager $featureManager)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Closure $next
     * @param string $feature The name of the feature to check.
     * @return mixed
     */
    public function handle(ServerRequestInterface $request, Closure $next, string $feature)
    {
        if (!$this->featureManager->isEnabled($feature)) {
            throw new NotFoundException('This feature is not currently enabled.');
        }

        return $next($request);
    }
}
