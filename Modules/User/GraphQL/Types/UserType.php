<?php

declare(strict_types=1);

namespace Modules\User\GraphQL\Types;

use Modules\User\Infrastructure\Models\User;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

/**
 * User GraphQL Type
 */
#[Type]
class UserType
{
    private function __construct(
        #[Field]
        public readonly int $id,
        
        #[Field]
        public readonly string $name,
        
        #[Field]
        public readonly string $email,
        
        #[Field]
        public readonly ?string $createdAt,
        
        #[Field]
        public readonly ?string $updatedAt,
    ) {
    }

    /**
     * Create UserType from User model
     */
    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $user->created_at?->format('Y-m-d H:i:s'),
            updatedAt: $user->updated_at?->format('Y-m-d H:i:s'),
        );
    }
}
