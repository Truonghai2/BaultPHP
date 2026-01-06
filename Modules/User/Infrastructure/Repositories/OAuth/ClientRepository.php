<?php

namespace Modules\User\Infrastructure\Repositories\OAuth;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Modules\User\Domain\Entities\OAuth\ClientEntity;
use Modules\User\Infrastructure\Models\OAuth\Client as ClientModel;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getClientEntity($clientIdentifier)
    {
        /** @var ClientModel|null $client */
        $client = ClientModel::where('id', '=', $clientIdentifier)->first();

        if (!$client || $client->revoked) {
            return null;
        }

        $clientEntity = new ClientEntity($client->id);
        $clientEntity->setName($client->name);
        $clientEntity->setRedirectUri(explode(',', $client->redirect));
        $clientEntity->isConfidential(!is_null($client->secret));

        return $clientEntity;
    }

    /**
     * {@inheritdoc}
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        /** @var ClientModel|null $client */
        $client = ClientModel::where('id', '=', $clientIdentifier)->first();

        if (!$client || $client->revoked) {
            return false;
        }

        if ($client->secret !== null) {
            if (!$clientSecret) {
                return false;
            }
            
            if (str_starts_with($client->secret, '$2y$')) {
                return password_verify($clientSecret, $client->secret);
            } else {
                return hash_equals((string) $client->secret, $clientSecret);
            }
        }
        elseif ($clientSecret !== null) {
            return false;
        }

        return true;
    }
}
