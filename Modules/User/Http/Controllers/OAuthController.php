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
    }

    #[Route('/oauth/authorize', method: 'GET', name: 'oauth.authorize')]
    public function HandleAuthorize(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $authRequest = $this->server->validateAuthorizationRequest($request);

            $user = Auth::user();
            if (!$user) {
                return redirect()->route('auth.login.view', ['redirect' => $request->getUri()->__toString()]);
            }

            $authRequest->setUser($user);

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

            if (!$user) {
                return redirect()->route('auth.login.view');
            }

            $authRequest->setUser($user);

            $approved = ($request->getParsedBody()['action'] ?? 'deny') === 'approve';
            $authRequest->setAuthorizationApproved($approved);

            return $this->server->completeAuthorizationRequest($authRequest, response());
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(response());
        }
    }

    #[Route('/oauth/token', method: 'POST')]
    public function token(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return $this->server->respondToAccessTokenRequest($request, response());
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(response());
        } catch (\Exception $e) {
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
        $tokenId = $request->getAttribute('oauth_token_id');

        if (!$tokenId) {
            return response()->json(['message' => 'Unable to identify token for revocation.'], 400);
        }

        $this->accessTokenRepository->revokeAccessToken($tokenId);

        $refreshToken = RefreshTokenModel::where('access_token_id', '=', $tokenId)->first();
        if ($refreshToken) {
            $this->refreshTokenRepository->revokeRefreshToken($refreshToken->id);
        }

        return response()->json(['message' => 'Tokens revoked successfully.']);
    }
}
