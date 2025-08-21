<?php

namespace Modules\User\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Modules\User\Infrastructure\Models\OAuth\RefreshToken as RefreshTokenModel;
use Modules\User\Infrastructure\Repositories\OAuth\AccessTokenRepository;
use Modules\User\Infrastructure\Repositories\OAuth\RefreshTokenRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class OAuthController extends Controller
{
    public function __construct(
        private AuthorizationServer $server,
        private AccessTokenRepository $accessTokenRepository,
        private RefreshTokenRepository $refreshTokenRepository,
    ) {
        // DI Container sẽ tự động inject các repository cần thiết.
    }

    #[Route('/oauth/authorize', method: 'GET', name: 'oauth.authorize')]
    public function HandleAuthorize(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // 1. Validate the authorization request
            $authRequest = $this->server->validateAuthorizationRequest($request);

            // 2. Get the currently authenticated user
            $user = Auth::user();
            if (!$user) {
                // Nếu chưa đăng nhập, chuyển hướng đến trang login
                return redirect()->route('auth.login.view', ['redirect' => $request->getUri()->__toString()]);
            }

            // 3. Associate the user with the auth request
            $authRequest->setUser($user);

            // 4. Show approval screen to the user
            // (Bạn cần tạo view này: user::auth.authorize)
            return response(view('user::auth.authorize', [
                'client' => $authRequest->getClient(),
                'scopes' => $authRequest->getScopes(),
                'request' => $request->getQueryParams(),
            ]));

        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(response());
        }
    }

    #[Route('/oauth/authorize', method: 'POST', name: 'oauth.approve')]
    public function approve(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $authRequest = $this->server->validateAuthorizationRequest($request);
            $user = Auth::user();

            // Thêm kiểm tra để đảm bảo người dùng vẫn còn đăng nhập.
            // Điều này xử lý trường hợp session của người dùng hết hạn giữa lúc xem form và lúc nhấn approve.
            if (!$user) {
                return redirect()->route('auth.login.view');
            }

            $authRequest->setUser($user);

            // Kiểm tra xem người dùng đã đồng ý hay từ chối
            $approved = ($request->getParsedBody()['action'] ?? 'deny') === 'approve';
            $authRequest->setAuthorizationApproved($approved);

            // 5. Hoàn tất request và trả về redirect response
            return $this->server->completeAuthorizationRequest($authRequest, response());
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(response());
        }
    }

    #[Route('/oauth/token', method: 'POST')]
    public function token(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // 6. Xử lý request cấp access token
            return $this->server->respondToAccessTokenRequest($request, response());
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(response());
        } catch (\Exception $e) {
            // Để nhất quán, hãy bọc lỗi không xác định trong một OAuthServerException
            // để trả về lỗi theo đúng chuẩn OAuth2.
            $oauthException = new OAuthServerException($e->getMessage(), 0, 'server_error', 500);
            return $oauthException->generateHttpResponse(response());
        }
    }

    /**
     * Thu hồi token hiện tại của người dùng (chức năng logout).
     * Endpoint này phải được bảo vệ bởi middleware 'api' (sử dụng OAuthGuardMiddleware).
     */
    #[Route('/oauth/revoke', method: 'POST', group: 'api')]
    public function revoke(ServerRequestInterface $request): ResponseInterface
    {
        // Middleware đã xác thực request và thêm các thuộc tính vào nó.
        $tokenId = $request->getAttribute('oauth_token_id');

        if (!$tokenId) {
            return response()->json(['message' => 'Unable to identify token for revocation.'], 400);
        }

        // 1. Thu hồi access token.
        $this->accessTokenRepository->revokeAccessToken($tokenId);

        // 2. Tìm và thu hồi refresh token được liên kết (nếu có).
        // Đây là bước quan trọng để đảm bảo logout hoàn toàn.
        $refreshToken = RefreshTokenModel::where('access_token_id', '=', $tokenId)->first();
        if ($refreshToken) {
            $this->refreshTokenRepository->revokeRefreshToken($refreshToken->id);
        }

        return response()->json(['message' => 'Tokens revoked successfully.']);
    }
}
