<?php

namespace Core\EventSourcing\Examples;

use Core\EventSourcing\AggregateRoot;
use Core\EventSourcing\Examples\Events\UserEmailChanged;
use Core\EventSourcing\Examples\Events\UserRegistered;
use Core\EventSourcing\Examples\Events\UserStatusChanged;

/**
 * User Aggregate - Example Implementation
 * 
 * This demonstrates how to use Event Sourcing in practice.
 */
class UserAggregate extends AggregateRoot
{
    private string $email;
    private string $name;
    private string $status = 'pending';
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    /**
     * Register a new user
     */
    public function register(string $id, string $email, string $name): void
    {
        $this->recordThat(new UserRegistered(
            userId: $id,
            email: $email,
            name: $name
        ));
    }

    /**
     * Change user email
     */
    public function changeEmail(string $newEmail): void
    {
        if ($this->email === $newEmail) {
            return; // No change needed
        }

        $this->recordThat(new UserEmailChanged(
            userId: $this->id,
            oldEmail: $this->email,
            newEmail: $newEmail
        ));
    }

    /**
     * Verify user email
     */
    public function verifyEmail(): void
    {
        if ($this->emailVerifiedAt !== null) {
            return; // Already verified
        }

        $this->recordThat(new UserStatusChanged(
            userId: $this->id,
            oldStatus: $this->status,
            newStatus: 'active'
        ));
    }

    /**
     * Suspend user
     */
    public function suspend(string $reason): void
    {
        $this->recordThat(new UserStatusChanged(
            userId: $this->id,
            oldStatus: $this->status,
            newStatus: 'suspended',
            metadata: ['reason' => $reason]
        ));
    }

    // Event Application Methods

    protected function applyUserRegistered(UserRegistered $event): void
    {
        $this->id = $event->userId;
        $this->email = $event->email;
        $this->name = $event->name;
        $this->status = 'pending';
    }

    protected function applyUserEmailChanged(UserEmailChanged $event): void
    {
        $this->email = $event->newEmail;
        $this->emailVerifiedAt = null; // Reset verification
    }

    protected function applyUserStatusChanged(UserStatusChanged $event): void
    {
        $this->status = $event->newStatus;
        
        if ($event->newStatus === 'active' && $this->status === 'pending') {
            $this->emailVerifiedAt = $event->occurredAt;
        }
    }

    // Getters (for read models/projections)

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
}

