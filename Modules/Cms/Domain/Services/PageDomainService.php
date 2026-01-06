<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Modules\Cms\Domain\Aggregates\PageAggregate;

/**
 * Page Domain Service
 *
 * PURE business logic - NO infrastructure dependencies.
 * Contains complex domain rules that don't belong in aggregate.
 *
 * Location: Domain/Services (CORRECT)
 * Why: Pure business logic with no infrastructure
 */
class PageDomainService
{
    /**
     * Check if page can be published
     *
     * Business rules:
     * - Must have a name
     * - Must not be deleted
     * - Must have valid slug
     */
    public function canPublish(PageAggregate $page): bool
    {
        if ($page->isDeleted()) {
            return false;
        }

        if (empty($page->getName())) {
            return false;
        }

        if (empty($page->getSlug())) {
            return false;
        }

        // Business rule: Must have at least one block to publish
        if (empty($page->getBlockIds())) {
            return false;
        }

        return true;
    }

    /**
     * Validate page name
     *
     * Business rules for page names
     */
    public function validatePageName(string $name): void
    {
        if (empty(trim($name))) {
            throw new \DomainException('Page name cannot be empty');
        }

        if (strlen($name) < 3) {
            throw new \DomainException('Page name must be at least 3 characters');
        }

        if (strlen($name) > 200) {
            throw new \DomainException('Page name cannot exceed 200 characters');
        }

        // Business rule: No special characters in name
        if (preg_match('/[<>{}]/', $name)) {
            throw new \DomainException('Page name cannot contain special characters: < > { }');
        }
    }

    /**
     * Validate slug
     *
     * Business rules for slugs
     */
    public function validateSlug(string $slug): void
    {
        if (empty(trim($slug))) {
            throw new \DomainException('Slug cannot be empty');
        }

        // Business rule: Slug must be URL-safe
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new \DomainException(
                'Slug must be lowercase, alphanumeric, and use hyphens only',
            );
        }

        if (strlen($slug) > 255) {
            throw new \DomainException('Slug cannot exceed 255 characters');
        }

        // Business rule: Reserved slugs
        $reservedSlugs = [
            'admin',
            'api',
            'login',
            'logout',
            'register',
            'dashboard',
            'system',
            'config',
            'settings',
        ];

        if (in_array($slug, $reservedSlugs, true)) {
            throw new \DomainException("Slug '{$slug}' is reserved and cannot be used");
        }
    }

    /**
     * Check if page can be deleted
     *
     * Business rules for deletion
     */
    public function canDelete(PageAggregate $page): bool
    {
        if ($page->isDeleted()) {
            return false; // Already deleted
        }

        // Business rule: Cannot delete homepage
        if ($page->getSlug() === 'home' || $page->getSlug() === 'index') {
            return false;
        }

        return true;
    }

    /**
     * Validate content update
     *
     * Business rules for content
     */
    public function validateContentUpdate(array $oldContent, array $newContent): void
    {
        // Business rule: Content size limit
        $serialized = json_encode($newContent);
        if (strlen($serialized) > 1048576) { // 1MB
            throw new \DomainException('Content size cannot exceed 1MB');
        }

        // Business rule: Must have required fields if specified
        if (isset($newContent['required']) && $newContent['required']) {
            if (!isset($newContent['body']) || empty($newContent['body'])) {
                throw new \DomainException('Required content field "body" is missing or empty');
            }
        }
    }

    /**
     * Check if rename is allowed
     *
     * Business rules for renaming
     */
    public function canRename(PageAggregate $page, string $newSlug): bool
    {
        if ($page->isDeleted()) {
            return false;
        }

        // Business rule: Cannot rename if published (would break URLs)
        // In production, you might want to create redirects instead
        if ($page->isPublished()) {
            // Could allow with redirect creation
            return true; // For now, allow but would create redirect
        }

        return true;
    }

    /**
     * Calculate SEO score
     *
     * Domain logic for SEO evaluation
     */
    public function calculateSeoScore(PageAggregate $page): array
    {
        $score = 100;
        $issues = [];

        // Check name length
        $nameLength = strlen($page->getName());
        if ($nameLength < 30) {
            $score -= 10;
            $issues[] = 'Page name is too short for SEO (< 30 chars)';
        } elseif ($nameLength > 60) {
            $score -= 5;
            $issues[] = 'Page name is too long for SEO (> 60 chars)';
        }

        // Check slug quality
        $slugLength = strlen($page->getSlug());
        if ($slugLength < 3) {
            $score -= 15;
            $issues[] = 'Slug is too short';
        }

        // Check content
        $content = $page->getContent();
        if (empty($content)) {
            $score -= 30;
            $issues[] = 'Page has no content';
        } else {
            if (!isset($content['description']) || empty($content['description'])) {
                $score -= 10;
                $issues[] = 'Missing meta description';
            }
        }

        // Check featured image
        if ($page->getFeaturedImagePath() === null) {
            $score -= 5;
            $issues[] = 'No featured image set';
        }

        // Check blocks
        if (empty($page->getBlockIds())) {
            $score -= 20;
            $issues[] = 'Page has no content blocks';
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'rating' => $this->getScoreRating($score),
        ];
    }

    /**
     * Get rating label for score
     */
    private function getScoreRating(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 75 => 'Good',
            $score >= 60 => 'Fair',
            $score >= 40 => 'Poor',
            default => 'Very Poor'
        };
    }

    /**
     * Validate block addition
     *
     * Business rules for adding blocks
     */
    public function validateBlockAddition(PageAggregate $page, int $blockCount): void
    {
        // Business rule: Maximum blocks per page
        $maxBlocks = 50;
        if ($blockCount >= $maxBlocks) {
            throw new \DomainException("Cannot add more than {$maxBlocks} blocks to a page");
        }

        // Business rule: Cannot add blocks to deleted page
        if ($page->isDeleted()) {
            throw new \DomainException('Cannot add blocks to deleted page');
        }
    }

    /**
     * Generate suggested slug from name
     *
     * Domain logic for slug generation
     */
    public function generateSlug(string $name): string
    {
        // Convert to lowercase
        $slug = strtolower($name);

        // Remove special characters
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);

        // Replace spaces and multiple hyphens with single hyphen
        $slug = preg_replace('/[\s-]+/', '-', $slug);

        // Trim hyphens from ends
        $slug = trim($slug, '-');

        // Limit length
        $slug = substr($slug, 0, 100);

        return $slug;
    }

    /**
     * Check if content has changed significantly
     *
     * Used to determine if update is worth recording
     */
    public function hasSignificantContentChange(array $oldContent, array $newContent): bool
    {
        // Trivial changes might not be worth an event

        // Check if only whitespace changed
        $oldJson = json_encode($oldContent, JSON_PRETTY_PRINT);
        $newJson = json_encode($newContent, JSON_PRETTY_PRINT);

        $oldNormalized = preg_replace('/\s+/', '', $oldJson);
        $newNormalized = preg_replace('/\s+/', '', $newJson);

        if ($oldNormalized === $newNormalized) {
            return false; // Only whitespace changed
        }

        // Check size of change
        $changeSize = abs(strlen($oldJson) - strlen($newJson));
        if ($changeSize < 10) {
            // Very small change, might not be significant
            // But still record it for audit trail
            return true;
        }

        return true;
    }
}
