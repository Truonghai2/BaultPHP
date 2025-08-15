<?php

namespace Http\View\DataTransferObjects;

/**
 * A simple Data Transfer Object to hold team member information.
 * Using readonly properties makes the object immutable after creation.
 */
class TeamMemberData
{
    public function __construct(
        public readonly string $name,
        public readonly string $role,
        public readonly ?string $avatar = null,
    ) {
    }
}
