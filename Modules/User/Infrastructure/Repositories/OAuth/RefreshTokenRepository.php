<?php

namespace Modules\User\Infrastructure\Repositories\OAuth;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Modules\User\Domain\Entities\OAuth\RefreshTokenEntity;
use Modules\User\Infrastructure\Models\OAuth\RefreshToken as RefreshTokenModel;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getNewRefreshToken()
    {
        return new RefreshTokenEntity();
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity)
    {
        RefreshTokenModel::create([
            'id' => $refreshTokenEntity->getIdentifier(),
            'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            'revoked' => false,
            'expires_at' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function revokeRefreshToken($tokenId)
    {
        RefreshTokenModel::where('id', '=', $tokenId)->update(['revoked' => true]);
    }

    /**
     * {@inheritdoc}
     */
    public function isRefreshTokenRevoked($tokenId)
    {
        $token = RefreshTokenModel::find($tokenId);

        return !$token || $token->revoked;
    }

    /**
     * Revoke all refresh tokens associated with an access token.
     * This ensures proper token cascade revocation.
     */
    public function revokeRefreshTokensByAccessToken(string $accessTokenId): int
    {
        return RefreshTokenModel::where('access_token_id', '=', $accessTokenId)
            ->update(['revoked' => true]);
    }
}
