<?php

namespace Modules\User\Http\Middleware;

use Closure;
use Core\Cache\CacheManager;
use Core\Http\Response;
use Core\Routing\Attributes\Middleware;
use Core\Support\Facades\Auth;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class OAuthGuardMiddleware extends Middleware
{
    public function __construct(private ResourceServer $server, private CacheManager $cache, private PsrHttpFactory $psrHttpFactory)
    {
    }

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
        $psrRequest = $this->psrHttpFactory->createRequest($request);

        try {
            // 2. Xác thực access token và nhận về PSR-7 request đã được chứng thực
            $psrRequest = $this->server->validateAuthenticatedRequest($psrRequest);
        } catch (OAuthServerException $exception) {
            // 3. Nếu xác thực thất bại, trả về lỗi chuẩn OAuth2
            return $exception->generateHttpResponse(new Response());
        } catch (\Exception $exception) {
            $oauthException = new OAuthServerException($exception->getMessage(), 0, 'unknown_error', 500);
            return $oauthException->generateHttpResponse(new Response());
        }

        // 4. Request đã được xác thực. Lấy các thông tin từ token.
        $userId = $psrRequest->getAttribute('oauth_user_id');
        $tokenScopes = $psrRequest->getAttribute('oauth_scopes') ?? [];

        // 5. Thiết lập người dùng đã xác thực cho request hiện tại.
        // Giúp Auth::user() hoạt động trong các controller và service.
        if ($userId) {
            // Cải tiến hiệu năng: Cache thông tin người dùng để giảm truy vấn CSDL.
            $user = $this->cache->remember("user:{$userId}", 300, function () use ($userId) {
                return \Modules\User\Infrastructure\Models\User::find($userId);
            });

            // Cải tiến bảo mật: Nếu token hợp lệ nhưng user ID không tồn tại trong CSDL
            // (ví dụ: người dùng đã bị xóa), hãy từ chối truy cập.
            if (!$user) {
                $error = new OAuthServerException('Access token is not associated with a valid user.', 10, 'invalid_grant', 401);
                return $error->generateHttpResponse(new Response());
            }

            Auth::setUser($user);
        }

        // 6. Kiểm tra xem token có đủ các scope được yêu cầu không.
        if (count($scopes) > 0) {
            $missingScopes = array_diff($scopes, $tokenScopes);
            if (count($missingScopes) > 0) {
                $required = implode(' ', $scopes);
                $error = new OAuthServerException(
                    sprintf('The token does not have the required scope(s): %s', implode(', ', $missingScopes)),
                    9,
                    'insufficient_scope',
                    403,
                );
                $error->setHttpHeaders(['WWW-Authenticate' => "Bearer scope=\"{$required}\""]);
                return $error->generateHttpResponse(new Response());
            }
        }

        // 7. Chuyển request đến middleware tiếp theo.
        return $next($request);
    }
}
