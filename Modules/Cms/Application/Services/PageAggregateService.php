<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Services;

use Core\EventSourcing\AggregateRepository;
use Core\Support\Facades\Audit;
use Modules\Cms\Domain\Aggregates\PageAggregate;
use Modules\Cms\Domain\Services\PageDomainService;
use Ramsey\Uuid\Uuid;

/**
 * Page Aggregate Service
 * 
 * APPLICATION SERVICE (not Domain Service!)
 * 
 * Responsibilities:
 * - Use case orchestration
 * - Transaction boundaries
 * - Infrastructure coordination (Repository, Audit, Cache, etc.)
 * - DTO transformation
 * - Authorization checks (in real app)
 * 
 * Does NOT contain:
 * - Pure business logic (that's in Domain layer)
 * 
 * Location: Application/Services (CORRECT)
 * Why: Has infrastructure dependencies + orchestrates use cases
 */
class PageAggregateService
{
    public function __construct(
        private AggregateRepository $aggregateRepository,
        private PageDomainService $domainService
    ) {
    }

    /**
     * Create a new page (Event Sourced)
     */
    public function createPage(
        string $name,
        string $slug,
        ?int $userId = null
    ): string {
        // Validate using domain service
        $this->domainService->validatePageName($name);
        $this->domainService->validateSlug($slug);

        $pageId = Uuid::uuid4()->toString();

        $page = new PageAggregate();
        $page->create($pageId, $name, $slug, $userId);

        $this->aggregateRepository->save($page);

        Audit::log(
            'cms_action',
            "Page created via Event Sourcing: {$name}",
            [
                'page_id' => $pageId,
                'name' => $name,
                'slug' => $slug,
                'user_id' => $userId,
                'method' => 'event_sourcing'
            ],
            'info'
        );

        return $pageId;
    }

    /**
     * Update page content (Event Sourced)
     */
    public function updatePageContent(
        string $pageId,
        array $content,
        string $userId
    ): void {
        $page = $this->loadPage($pageId);

        $oldContent = $page->getContent();

        // Validate using domain service
        $this->domainService->validateContentUpdate($oldContent, $content);

        // Check if change is significant
        if (!$this->domainService->hasSignificantContentChange($oldContent, $content)) {
            // Still update but log as minor change
            Audit::log('cms_action', 'Minor content change detected', ['page_id' => $pageId], 'debug');
        }

        $page->updateContent($content, $userId);

        $this->aggregateRepository->save($page);

        Audit::log(
            'cms_action',
            "Page content updated: {$page->getName()}",
            [
                'page_id' => $pageId,
                'user_id' => $userId,
                'content_size' => strlen(json_encode($content)),
                'method' => 'event_sourcing'
            ],
            'info'
        );
    }

    /**
     * Rename page
     */
    public function renamePage(
        string $pageId,
        string $newName,
        string $newSlug,
        string $userId
    ): void {
        $page = $this->loadPage($pageId);

        // Validate
        $this->domainService->validatePageName($newName);
        $this->domainService->validateSlug($newSlug);

        if (!$this->domainService->canRename($page, $newSlug)) {
            throw new \DomainException('Page cannot be renamed at this time');
        }

        $page->rename($newName, $newSlug, $userId);

        $this->aggregateRepository->save($page);

        Audit::log(
            'cms_action',
            "Page renamed: {$page->getName()} → {$newName}",
            [
                'page_id' => $pageId,
                'old_name' => $page->getName(),
                'new_name' => $newName,
                'old_slug' => $page->getSlug(),
                'new_slug' => $newSlug,
                'user_id' => $userId
            ],
            'info'
        );
    }

    /**
     * Publish page
     */
    public function publishPage(string $pageId, string $userId): void
    {
        $page = $this->loadPage($pageId);

        // Check business rules
        if (!$this->domainService->canPublish($page)) {
            $seoScore = $this->domainService->calculateSeoScore($page);
            throw new \DomainException(
                'Page cannot be published. Issues: ' . implode(', ', $seoScore['issues'])
            );
        }

        $page->publish($userId);

        $this->aggregateRepository->save($page);

        Audit::log(
            'cms_action',
            "Page published: {$page->getName()}",
            [
                'page_id' => $pageId,
                'user_id' => $userId,
                'slug' => $page->getSlug()
            ],
            'info'
        );

        // In production, you might:
        // - Clear page cache
        // - Invalidate CDN
        // - Send notifications
        // - Update search index
    }

    /**
     * Unpublish page
     */
    public function unpublishPage(string $pageId, string $userId): void
    {
        $page = $this->loadPage($pageId);

        $page->unpublish($userId);

        $this->aggregateRepository->save($page);

        Audit::log(
            'cms_action',
            "Page unpublished: {$page->getName()}",
            [
                'page_id' => $pageId,
                'user_id' => $userId
            ],
            'info'
        );
    }

    /**
     * Delete page (soft delete)
     */
    public function deletePage(
        string $pageId,
        string $userId,
        string $reason = ''
    ): void {
        $page = $this->loadPage($pageId);

        if (!$this->domainService->canDelete($page)) {
            throw new \DomainException('This page cannot be deleted (system page)');
        }

        $page->delete($userId, $reason);

        $this->aggregateRepository->save($page);

        Audit::log(
            'cms_action',
            "Page deleted: {$page->getName()}",
            [
                'page_id' => $pageId,
                'user_id' => $userId,
                'reason' => $reason
            ],
            'warning'
        );
    }

    /**
     * Restore deleted page
     */
    public function restorePage(string $pageId, string $userId): void
    {
        $page = $this->loadPage($pageId);

        $page->restore($userId);

        $this->aggregateRepository->save($page);

        Audit::log(
            'cms_action',
            "Page restored: {$page->getName()}",
            [
                'page_id' => $pageId,
                'user_id' => $userId
            ],
            'info'
        );
    }

    /**
     * Change featured image
     */
    public function changeFeaturedImage(
        string $pageId,
        ?string $imagePath,
        string $userId
    ): void {
        $page = $this->loadPage($pageId);

        $page->changeFeaturedImage($imagePath, $userId);

        $this->aggregateRepository->save($page);

        Audit::log(
            'cms_action',
            "Featured image changed for page: {$page->getName()}",
            [
                'page_id' => $pageId,
                'image_path' => $imagePath,
                'user_id' => $userId
            ],
            'info'
        );
    }

    /**
     * Add block to page
     */
    public function addBlockToPage(
        string $pageId,
        string $blockId,
        string $componentClass,
        int $sortOrder,
        string $userId
    ): void {
        $page = $this->loadPage($pageId);

        // Validate using domain service
        $currentBlockCount = count($page->getBlockIds());
        $this->domainService->validateBlockAddition($page, $currentBlockCount);

        $page->addBlock($blockId, $componentClass, $sortOrder, $userId);

        $this->aggregateRepository->save($page);

        Audit::log(
            'cms_action',
            "Block added to page: {$page->getName()}",
            [
                'page_id' => $pageId,
                'block_id' => $blockId,
                'component_class' => $componentClass,
                'user_id' => $userId
            ],
            'info'
        );
    }

    /**
     * Get page state (as array)
     */
    public function getPageState(string $pageId): ?array
    {
        $page = $this->getPage($pageId);

        if (!$page) {
            return null;
        }

        $seoScore = $this->domainService->calculateSeoScore($page);

        return [
            'id' => $page->getId(),
            'name' => $page->getName(),
            'slug' => $page->getSlug(),
            'user_id' => $page->getUserId(),
            'content' => $page->getContent(),
            'featured_image_path' => $page->getFeaturedImagePath(),
            'status' => $page->getStatus(),
            'is_published' => $page->isPublished(),
            'is_deleted' => $page->isDeleted(),
            'published_at' => $page->getPublishedAt()?->format('Y-m-d H:i:s'),
            'deleted_at' => $page->getDeletedAt()?->format('Y-m-d H:i:s'),
            'block_ids' => $page->getBlockIds(),
            'block_count' => count($page->getBlockIds()),
            'version' => $page->getVersion(),
            'seo_score' => $seoScore
        ];
    }

    /**
     * Get page aggregate (for read operations)
     */
    public function getPage(string $pageId): ?PageAggregate
    {
        return $this->aggregateRepository->load(PageAggregate::class, $pageId);
    }

    /**
     * Get page history (all events)
     * 
     * Useful for audit trail, time travel debugging
     */
    public function getPageHistory(string $pageId): array
    {
        $page = $this->getPage($pageId);

        if (!$page) {
            return [];
        }

        // In a real implementation, you'd load events from event store
        // For now, just return current version info
        return [
            'page_id' => $pageId,
            'current_version' => $page->getVersion(),
            'current_state' => $this->getPageState($pageId)
            // Would include: event stream, timestamps, user actions, etc.
        ];
    }

    /**
     * Generate slug suggestion from name
     */
    public function suggestSlug(string $name): string
    {
        return $this->domainService->generateSlug($name);
    }

    /**
     * Load page or throw exception
     */
    private function loadPage(string $pageId): PageAggregate
    {
        $page = $this->getPage($pageId);

        if (!$page) {
            throw new \RuntimeException("Page {$pageId} not found in event store");
        }

        return $page;
    }
}

