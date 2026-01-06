<?php

namespace Modules\User\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
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

    #[Route('/oauth/authorize', method: 'GET', name: 'oauth.authorize', group: 'web')]
    public function HandleAuthorize(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $authRequest = $this->server->validateAuthorizationRequest($request);

            $user = Auth::user();
            if (!$user) {
                return redirect()->route('auth.login.view', ['redirect' => $request->getUri()->__toString()]);
            }

            // Convert Authenticatable to UserEntity
            $userEntity = new \Modules\User\Domain\Entities\OAuth\UserEntity($user->getAuthIdentifier());
            $authRequest->setUser($userEntity);

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

            $userEntity = new \Modules\User\Domain\Entities\OAuth\UserEntity($user->getAuthIdentifier());
            $authRequest->setUser($userEntity);

            $approved = ($request->getParsedBody()['action'] ?? 'deny') === 'approve';
            $authRequest->setAuthorizationApproved($approved);

            return $this->server->completeAuthorizationRequest($authRequest, response());
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(response());
        }
    }

    #[Route('/oauth/token', method: 'POST', middleware: ['throttle:20,1'])]
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
        /** @var \Core\Auth\TokenGuard $guard */
        $guard = auth()->guard('api');
        $tokenId = $guard->getTokenId();

        if (!$tokenId) {
            return response()->json(['message' => 'Unable to identify token for revocation.'], 400);
        }

        $this->accessTokenRepository->revokeAccessToken($tokenId);

        $revokedCount = $this->refreshTokenRepository->revokeRefreshTokensByAccessToken($tokenId);

        $userId = $guard->id();
        if ($userId) {
            cache()->forget("oauth:user:{$userId}");
        }

        return response()->json([
            'message' => 'Tokens revoked successfully.',
            'revoked_refresh_tokens' => $revokedCount,
        ]);
    }

    /**
     * List all active tokens for the authenticated user.
     *
     * @return ResponseInterface
     */
    #[Route('/oauth/tokens', method: 'GET', group: 'api')]
    public function listTokens(ServerRequestInterface $request): ResponseInterface
    {
        $guard = auth()->guard('api');
        $userId = $guard->id();

        if (!$userId) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        $tokens = \Modules\User\Infrastructure\Models\OAuth\AccessToken::where('user_id', '=', $userId)
            ->where('revoked', '=', false)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'client_id' => $token->client_id,
                    'scopes' => json_decode($token->scopes, true) ?? [],
                    'created_at' => $token->created_at,
                    'expires_at' => $token->expires_at,
                ];
            });

        return response()->json([
            'tokens' => $tokens,
            'count' => $tokens->count(),
        ]);
    }

    /**
     * Revoke a specific token by ID.
     * User can only revoke their own tokens.
     *
     * @param string $tokenId
     * @return ResponseInterface
     */
    #[Route('/oauth/tokens/{tokenId}', method: 'DELETE', group: 'api')]
    public function revokeToken(string $tokenId, ServerRequestInterface $request): ResponseInterface
    {
        $guard = auth()->guard('api');
        $userId = $guard->id();

        if (!$userId) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        $token = \Modules\User\Infrastructure\Models\OAuth\AccessToken::where('id', '=', $tokenId)
            ->where('user_id', '=', $userId)
            ->first();

        if (!$token) {
            return response()->json(['error' => 'Token not found or access denied.'], 404);
        }

        if ($token->revoked) {
            return response()->json(['message' => 'Token already revoked.'], 200);
        }

        $this->accessTokenRepository->revokeAccessToken($tokenId);
        $revokedCount = $this->refreshTokenRepository->revokeRefreshTokensByAccessToken($tokenId);

        // Clear user cache
        cache()->forget("oauth:user:{$userId}");

        return response()->json([
            'message' => 'Token revoked successfully.',
            'revoked_refresh_tokens' => $revokedCount,
        ]);
    }

    /**
     * Token introspection endpoint (RFC 7662).
     * Returns information about a token without requiring authentication.
     *
     * @return ResponseInterface
     */
    #[Route('/oauth/introspect', method: 'POST', middleware: ['throttle:60,1'])]
    public function introspect(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $token = $body['token'] ?? null;
        $tokenTypeHint = $body['token_type_hint'] ?? 'access_token';

        if (!$token) {
            return response()->json([
                'active' => false,
                'error' => 'invalid_request',
                'error_description' => 'Missing token parameter.',
            ], 400);
        }

        try {
            // Try to validate as access token
            $accessToken = \Modules\User\Infrastructure\Models\OAuth\AccessToken::where('id', '=', $token)->first();

            if ($accessToken && !$accessToken->revoked && strtotime($accessToken->expires_at) > time()) {
                return response()->json([
                    'active' => true,
                    'client_id' => $accessToken->client_id,
                    'username' => $accessToken->user_id ? \Modules\User\Infrastructure\Models\User::find($accessToken->user_id)?->email : null,
                    'scope' => implode(' ', json_decode($accessToken->scopes, true) ?? []),
                    'sub' => (string) $accessToken->user_id,
                    'aud' => $accessToken->client_id,
                    'iat' => strtotime($accessToken->created_at),
                    'exp' => strtotime($accessToken->expires_at),
                    'token_type' => 'Bearer',
                ]);
            }

            // Try refresh token if access token not found
            if ($tokenTypeHint === 'refresh_token' || !$accessToken) {
                $refreshToken = \Modules\User\Infrastructure\Models\OAuth\RefreshToken::where('id', '=', $token)->first();

                if ($refreshToken && !$refreshToken->revoked && strtotime($refreshToken->expires_at) > time()) {
                    $relatedAccessToken = \Modules\User\Infrastructure\Models\OAuth\AccessToken::find($refreshToken->access_token_id);

                    return response()->json([
                        'active' => true,
                        'client_id' => $relatedAccessToken?->client_id,
                        'scope' => $relatedAccessToken ? implode(' ', json_decode($relatedAccessToken->scopes, true) ?? []) : '',
                        'sub' => $relatedAccessToken?->user_id ? (string) $relatedAccessToken->user_id : null,
                        'aud' => $relatedAccessToken?->client_id,
                        'exp' => strtotime($refreshToken->expires_at),
                        'token_type' => 'refresh_token',
                    ]);
                }
            }

            // Token not found or invalid
            return response()->json([
                'active' => false,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'active' => false,
                'error' => 'server_error',
                'error_description' => 'An error occurred during token introspection.',
            ], 500);
        }
    }
}
