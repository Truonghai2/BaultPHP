<?php

namespace Modules\User\Infrastructure\Repositories\OAuth;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Modules\User\Domain\Entities\OAuth\ScopeEntity;
use Modules\User\Infrastructure\Models\OAuth\Scope as ScopeModel;

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

        // Thêm mô tả vào ScopeEntity để có thể hiển thị trên màn hình cấp phép.
        // Giả sử ScopeEntity của bạn có phương thức setDescription().
        // $scopeEntity->setDescription($scope->description);

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
        // Ở đây bạn có thể thêm logic để lọc các scope dựa trên client hoặc user.
        // Ví dụ: một client chỉ được phép yêu cầu một số scope nhất định.
        // Hiện tại, chúng ta trả về tất cả các scope đã được validate.
        return $scopes;
    }
}
