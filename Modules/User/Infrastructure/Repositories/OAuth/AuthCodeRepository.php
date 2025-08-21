<?php

namespace Modules\User\Infrastructure\Repositories\OAuth;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Modules\User\Domain\Entities\OAuth\AuthCodeEntity;
use Modules\User\Infrastructure\Models\OAuth\AuthCode as AuthCodeModel;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getNewAuthCode()
    {
        return new AuthCodeEntity();
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity)
    {
        AuthCodeModel::create([
            'id' => $authCodeEntity->getIdentifier(),
            'user_id' => $authCodeEntity->getUserIdentifier(),
            'client_id' => $authCodeEntity->getClient()->getIdentifier(),
            'scopes' => json_encode(array_map(fn ($scope) => $scope->getIdentifier(), $authCodeEntity->getScopes())),
            'revoked' => false,
            'expires_at' => $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function revokeAuthCode($codeId)
    {
        AuthCodeModel::where('id', '=', $codeId)->update(['revoked' => true]);
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthCodeRevoked($codeId)
    {
        $code = AuthCodeModel::find($codeId);

        return !$code || $code->revoked;
    }
}
