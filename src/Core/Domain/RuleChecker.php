<?php

namespace Core\Domain;

use Core\Support\Result;

/**
 * Rule Checker Helper.
 * 
 * Provides convenient methods to check multiple rules.
 */
class RuleChecker
{
    /**
     * Check a single rule.
     * 
     * @param DomainRule $rule
     * @return Result Success if rule passes, Failure with error if broken
     */
    public static function check(DomainRule $rule): Result
    {
        if ($rule->isBrokenIf()) {
            return Result::fail($rule->getError());
        }

        return Result::ok();
    }

    /**
     * Check multiple rules.
     * 
     * Returns failure on first broken rule, or success if all pass.
     * 
     * @param array<DomainRule> $rules
     * @return Result
     */
    public static function checkAll(array $rules): Result
    {
        foreach ($rules as $rule) {
            if ($rule->isBrokenIf()) {
                return Result::fail($rule->getError());
            }
        }

        return Result::ok();
    }

    /**
     * Check multiple rules and collect all errors.
     * 
     * @param array<DomainRule> $rules
     * @return Result Success if all pass, Failure with array of errors
     */
    public static function checkAllAndCollect(array $rules): Result
    {
        $errors = [];

        foreach ($rules as $rule) {
            if ($rule->isBrokenIf()) {
                $errors[] = $rule->getError();
            }
        }

        if (!empty($errors)) {
            return Result::fail($errors);
        }

        return Result::ok();
    }

    /**
     * Throw exception if rule is broken.
     * 
     * @param DomainRule $rule
     * @throws DomainError
     */
    public static function enforce(DomainRule $rule): void
    {
        if ($rule->isBrokenIf()) {
            throw $rule->getError();
        }
    }
}
