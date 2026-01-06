<?php

namespace Modules\User\Infrastructure\Repositories\OAuth;

use Core\Support\Facades\Gate;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Modules\User\Domain\Entities\OAuth\ScopeEntity;
use Modules\User\Infrastructure\Models\OAuth\Scope as ScopeModel;
use Modules\User\Infrastructure\Models\User;

class ScopeRepository implements ScopeRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getScopeEntityByIdentifier($identifier)
    {
        /** @var ScopeModel|null $scope */
        $scope = ScopeModel::where('id', '=', $identifier)->first();

        if (!$scope) {
            return null;
        }

        $scopeEntity = new ScopeEntity();
        $scopeEntity->setIdentifier($scope->id);
        
        if (isset($scope->description)) {
            $scopeEntity->setDescription($scope->description);
        }

        return $scopeEntity;
    }

    /**
     * {@inheritdoc}
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null,
    ) {
        $clientRestrictedScopes = config('oauth2.restricted_scopes', []);
        $clientId = $clientEntity->getIdentifier();

        $allowedScopes = array_filter($scopes, function (ScopeEntityInterface $scope) use ($clientId, $clientRestrictedScopes) {
            $scopeIdentifier = $scope->getIdentifier();

            if (array_key_exists($scopeIdentifier, $clientRestrictedScopes)) {
                $allowedClients = $clientRestrictedScopes[$scopeIdentifier];
                if (!in_array($clientId, $allowedClients, true)) {
                    return false;
                }
            }
            return true;
        });

        if ($userIdentifier !== null) {
            $user = User::find($userIdentifier);
            $userRestrictedScopes = config('oauth2.user_restricted_scopes', []);

            if ($user) {
                $allowedScopes = array_filter($allowedScopes, function (ScopeEntityInterface $scope) use ($user, $userRestrictedScopes) {
                    $scopeIdentifier = $scope->getIdentifier();

                    if (array_key_exists($scopeIdentifier, $userRestrictedScopes)) {
                        $permissionName = $userRestrictedScopes[$scopeIdentifier];
                        if (!Gate::check($user, $permissionName)) {
                            return false;
                        }
                    }
                    return true;
                });
            }
        }

        return array_values($allowedScopes);
    }
}
