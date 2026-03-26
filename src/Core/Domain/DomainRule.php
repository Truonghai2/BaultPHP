<?php

namespace Core\Domain;

/**
 * Domain Rule Interface.
 * 
 * Represents a business rule that can be checked.
 * Rules encapsulate business logic and make it explicit and testable.
 * 
 * Example:
 * - TodoAlreadyCompletedRule
 * - EmailMustBeUniqueRule
 * - UserMustBeAdultRule
 */
interface DomainRule
{
    /**
     * Check if the rule is broken.
     * 
     * @return bool True if the rule is violated
     */
    public function isBrokenIf(): bool;

    /**
     * Get the error when the rule is broken.
     * 
     * @return DomainError
     */
    public function getError(): DomainError;

    /**
     * Get a human-readable description of the rule.
     * 
     * @return string
     */
    public function getMessage(): string;
}
