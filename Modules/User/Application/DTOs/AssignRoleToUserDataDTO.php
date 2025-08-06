<?php

namespace Modules\User\Application\DTOs;

/**
 * Data Transfer Object for assigning a role to a user.
 */
class AssignRoleToUserDataDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly int $roleId,
        public readonly string $contextLevel,
        public readonly int $instanceId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            roleId: (int) $data['role_id'],
            contextLevel: $data['context_level'],
            instanceId: $data['instance_id'],
        );
    }
}
