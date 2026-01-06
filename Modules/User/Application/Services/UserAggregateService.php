<?php

namespace Modules\User\Application\Services;

use Core\EventSourcing\AggregateRepository;
use Core\EventSourcing\Examples\UserAggregate;
use Core\Support\Facades\Audit;
use Modules\User\Domain\Services\UserDomainService;
use Ramsey\Uuid\Uuid;

/**
 * UserAggregateService
 * 
 * APPLICATION SERVICE (not Domain Service!)
 * 
 * Responsibilities:
 * - Use case orchestration
 * - Transaction boundaries
 * - Infrastructure coordination (Repository, Audit, etc.)
 * - DTO transformation
 * 
 * Does NOT contain:
 * - Pure business logic (that's in Domain layer)
 * 
 * Location: Application/Services (CORRECT)
 * Why: Has infrastructure dependencies + orchestrates use cases
 */
class UserAggregateService
{
    public function __construct(
        private AggregateRepository $aggregateRepository,
        private UserDomainService $domainService
    ) {}

    /**
     * Register a new user (Event Sourced)
     */
    public function registerUser(string $email, string $name): string
    {
        $userId = Uuid::uuid4()->toString();
        
        $user = new UserAggregate();
        $user->register($userId, $email, $name);

        $this->aggregateRepository->save($user);

        Audit::log(
            'user_action',
            "User registered via Event Sourcing: {$email}",
            [
                'user_id' => $userId,
                'email' => $email,
                'name' => $name,
                'method' => 'event_sourcing'
            ],
            'info'
        );

        return $userId;
    }

    /**
     * Change user email (Event Sourced)
     */
    public function changeUserEmail(string $userId, string $newEmail): void
    {
        $user = $this->aggregateRepository->load(UserAggregate::class, $userId);

        if (!$user) {
            throw new \Exception("User {$userId} not found in event store");
        }

        $oldEmail = $user->getEmail();

        if (!$this->domainService->canChangeEmail($user)) {
            throw new \DomainException("Cannot change email: User is suspended");
        }

        $this->domainService->validateEmailChange($oldEmail, $newEmail);

        $user->changeEmail($newEmail);

        $this->aggregateRepository->save($user);

        Audit::log(
            'user_action',
            "User email changed: {$oldEmail} → {$newEmail}",
            [
                'user_id' => $userId,
                'old_email' => $oldEmail,
                'new_email' => $newEmail,
                'method' => 'event_sourcing'
            ],
            'info'
        );
    }

    /**
     * Verify user email
     */
    public function verifyUserEmail(string $userId): void
    {
        $user = $this->aggregateRepository->load(UserAggregate::class, $userId);

        if (!$user) {
            throw new \Exception("User {$userId} not found");
        }

        if (!$this->domainService->canVerifyEmail($user)) {
            throw new \DomainException("Cannot verify: User must be in pending status");
        }

        $user->verifyEmail();
        
        $this->aggregateRepository->save($user);

        Audit::log(
            'user_action',
            "User email verified: {$user->getEmail()}",
            ['user_id' => $userId],
            'info'
        );
    }

    /**
     * Suspend user
     */
    public function suspendUser(string $userId, string $reason): void
    {
        $user = $this->aggregateRepository->load(UserAggregate::class, $userId);

        if (!$user) {
            throw new \Exception("User {$userId} not found");
        }

        $user->suspend($reason);
        $this->aggregateRepository->save($user);

        Audit::log(
            'security',
            "User suspended: {$user->getEmail()}",
            [
                'user_id' => $userId,
                'reason' => $reason
            ],
            'warning'
        );
    }

    /**
     * Get user aggregate (for read operations)
     */
    public function getUser(string $userId): ?UserAggregate
    {
        return $this->aggregateRepository->load(UserAggregate::class, $userId);
    }

    /**
     * Get user state (as array)
     */
    public function getUserState(string $userId): ?array
    {
        $user = $this->getUser($userId);

        if (!$user) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'status' => $user->getStatus(),
            'is_active' => $user->isActive(),
            'is_verified' => $user->isEmailVerified(),
            'version' => $user->getVersion()
        ];
    }
}
