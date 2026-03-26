<?php

declare(strict_types=1);

namespace Modules\User\GraphQL\Queries;

use Modules\User\GraphQL\Types\UserType;
use Modules\User\Infrastructure\Models\User;
use TheCodingMachine\GraphQLite\Annotations\Query;

/**
 * User GraphQL Queries
 *
 * Note: For production, consider using QueryBus with CQRS pattern.
 * This implementation uses models directly for simplicity.
 */
class UserQuery
{
    /**
     * Get a user by ID
     */
    #[Query]
    public function user(int $id): ?UserType
    {
        $user = User::find($id);
        
        if (!$user) {
            return null;
        }

        return UserType::fromModel($user);
    }

    /**
     * Get all users
     *
     * @return UserType[]
     */
    #[Query]
    public function users(): array
    {
        $users = User::all();
        
        return $users->map(function (User $user) {
            return UserType::fromModel($user);
        })->toArray();
    }

    /**
     * Get current authenticated user
     */
    #[Query]
    public function me(): ?UserType
    {
        // TODO: Get from authentication context
        // $user = auth()->user();
        // if (!$user) {
        //     return null;
        // }
        // return UserType::fromModel($user);
        
        return null;
    }
}
