<?php

namespace Modules\User\Domain\Services;

use Core\EventSourcing\Examples\UserAggregate;

/**
 * UserDomainService
 * 
 * PURE business logic - NO infrastructure dependencies.
 * Contains complex domain rules that don't belong in aggregate.
 */
class UserDomainService
{
    /**
     * Check if email change is allowed
     * 
     * Business rule: Can't change email if user is suspended
     */
    public function canChangeEmail(UserAggregate $user): bool
    {
        if ($user->getStatus() === 'suspended') {
            return false;
        }

        // More business rules here...
        return true;
    }

    /**
     * Validate new email against business rules
     */
    public function validateEmailChange(string $oldEmail, string $newEmail): void
    {
        // Business rule: Can't change to same domain competitor
        $oldDomain = substr(strrchr($oldEmail, "@"), 1);
        $newDomain = substr(strrchr($newEmail, "@"), 1);

        $competitorDomains = ['competitor.com', 'rival.com'];
        
        if (in_array($newDomain, $competitorDomains)) {
            throw new \DomainException(
                "Cannot change email to competitor domain: {$newDomain}"
            );
        }
    }

    /**
     * Calculate suspension penalty
     * 
     * Business rule: Suspension duration based on violation count
     */
    public function calculateSuspensionDuration(int $violationCount): int
    {
        return match(true) {
            $violationCount === 1 => 7,     // 7 days
            $violationCount === 2 => 30,    // 30 days
            $violationCount >= 3 => 365,    // 1 year
            default => 0
        };
    }

    /**
     * Check if user can be verified
     * 
     * Business rule: Must be pending and not suspended
     */
    public function canVerifyEmail(UserAggregate $user): bool
    {
        return $user->getStatus() === 'pending';
    }
}

