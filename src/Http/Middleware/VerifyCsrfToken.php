<?php

namespace Http\Middleware;

use App\Exceptions\TokenMismatchException;
use Closure;
use Core\Contracts\Http\Middleware;
use Core\Session\SessionManager;
use Http\Request;
use Http\Response;

class VerifyCsrfToken implements Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected array $except = [
        'api/*', // Bỏ qua tất cả các route trong group 'api'
        'payment/webhook/stripe', // Bỏ qua một webhook cụ thể
    ];

    /**
     * The session manager instance.
     */
    protected SessionManager $session;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
    }

    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        if (
            $this->isReading($request) ||
            $this->inExceptArray($request) ||
            $this->tokensMatch($request)
        ) {
            $response = $next($request);

            return $this->addCookieToResponse($request, $response);
        }

        throw new TokenMismatchException('CSRF token mismatch.');
    }

    /**
     * Determine if the request is a "read" operation.
     */
    protected function isReading(Request $request): bool
    {
        return in_array($request->method(), ['HEAD', 'GET', 'OPTIONS']);
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     */
    protected function inExceptArray(Request $request): bool
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }
            if ($request->is($except)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if the session and input CSRF tokens match.
     */
    protected function tokensMatch(Request $request): bool
    {
        $token = $this->getTokenFromRequest($request);
        $sessionToken = $this->session->get('_token');

        return is_string($sessionToken) &&
               is_string($token) &&
               hash_equals($sessionToken, $token);
    }

    /**
     * Get the CSRF token from the request.
     *
     * @param  \Http\Request  $request
     * @return string|null
     */
    protected function getTokenFromRequest(Request $request): ?string
    {
        // Thêm kiểm tra header X-XSRF-TOKEN cho các thư viện JS như Axios
        return $request->input('_token') ?: $request->header('X-CSRF-TOKEN') ?: $request->header('X-XSRF-TOKEN');
    }

    /**
     * Add the CSRF token to the response cookies.
     * This is useful for JavaScript frameworks like Vue or React.
     *
     * @param  \Http\Request  $request
     * @param  \Http\Response $response
     * @return \Http\Response
     */
    protected function addCookieToResponse(Request $request, Response $response): Response
    {
        if ($token = $this->session->get('_token')) {
            // Cookie này không được là HttpOnly để JavaScript có thể đọc.
            $response->withCookie('XSRF-TOKEN', $token, 0, '/', null, false, false);
        }
        return $response;
    }
}