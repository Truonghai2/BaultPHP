<?php

namespace Modules\User\Http\Middleware;

use Closure;
use Core\Http\Response;
use Core\Routing\Attributes\Middleware;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OAuthGuardMiddleware extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param RequestInterface $request
     * @param Closure $next
     * @param string ...$scopes
     * @return mixed
     */
    public function handle(RequestInterface $request, Closure $next, ...$scopes): ResponseInterface
    {
        try {
            auth()->guard('api')->validate();

            $tokenScopes = auth()->guard('api')->getScopes();

            if (count($scopes) > 0) {
                $missingScopes = array_diff($scopes, $tokenScopes);
                if (count($missingScopes) > 0) {
                    $required = implode(' ', $scopes);
                    $error = OAuthServerException::insufficientScope($required);
                    return $error->generateHttpResponse(new Response());
                }
            }
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse(new Response());
        } catch (\Exception $exception) {
            $oauthException = new OAuthServerException($exception->getMessage(), 0, 'unknown_error', 500);
            return $oauthException->generateHttpResponse(new Response());
        }
        return $next($request);
    }
}
