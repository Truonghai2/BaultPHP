<?php

declare(strict_types=1);

namespace Modules\User\GraphQL\Mutations;

use Core\CQRS\Command\CommandBus;
use Modules\User\Application\Commands\User\UpdateUserCommand;
use Modules\User\GraphQL\Types\UserType;
use Modules\User\Infrastructure\Models\User;
use TheCodingMachine\GraphQLite\Annotations\Mutation;

/**
 * User GraphQL Mutations
 */
class UserMutation
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    /**
     * Update user profile
     */
    #[Mutation]
    public function updateUserProfile(
        int $id,
        ?string $name = null,
        ?string $email = null,
    ): UserType {
        // Use CQRS Command Bus
        $command = new UpdateUserCommand($id, $name, $email, null);
        /** @phpstan-ignore-next-line */
        $this->commandBus->dispatch($command);

        // Reload user
        $user = User::findOrFail($id);

        return UserType::fromModel($user);
    }
}
