<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageRevision;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\User\Infrastructure\Models\User;

/**
 * Page Revision Service
 * 
 * Handles creating, restoring, and managing page revisions
 */
class PageRevisionService
{
    /**
     * Create a new revision for a page
     */
    public function createRevision(
        Page $page,
        User $user,
        string $summary = '',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): PageRevision {
        // Get current page state with blocks
        $content = [
            'name' => $page->name,
            'slug' => $page->slug,
            'status' => $page->status ?? 'draft',
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'meta_keywords' => $page->meta_keywords,
            'og_image' => $page->og_image,
            'blocks' => $page->blocks()->get()->map(function($block) {
                return [
                    'block_type_id' => $block->block_type_id,
                    'region' => $block->region,
                    'sort_order' => $block->sort_order,
                    'visible' => $block->visible,
                ];
            })->toArray(),
        ];
        
        // Get next revision number
        $revisionNumber = $this->getNextRevisionNumber($page);
        
        // Create revision
        $revision = PageRevision::create([
            'page_id' => $page->id,
            'user_id' => $user->id,
            'content' => $content,
            'revision_number' => $revisionNumber,
            'change_summary' => $summary ?: "Revision #{$revisionNumber}",
            'ip_address' => $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            'user_agent' => $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
        ]);
        
        // Clean old revisions (keep last 50)
        $this->cleanOldRevisions($page, 50);
        
        return $revision;
    }
    
    /**
     * Restore page to a specific revision
     */
    public function restore(PageRevision $revision, User $user): Page
    {
        $content = $revision->content;
        $page = $revision->page;
        
        // Create new revision before restore (safety)
        $this->createRevision($page, $user, "Before restoring to revision #{$revision->revision_number}");
        
        // Restore page data
        $page->name = $content['name'];
        $page->slug = $content['slug'];
        if (isset($content['status'])) {
            $page->status = $content['status'];
        }
        if (isset($content['meta_title'])) {
            $page->meta_title = $content['meta_title'];
        }
        if (isset($content['meta_description'])) {
            $page->meta_description = $content['meta_description'];
        }
        if (isset($content['meta_keywords'])) {
            $page->meta_keywords = $content['meta_keywords'];
        }
        if (isset($content['og_image'])) {
            $page->og_image = $content['og_image'];
        }
        $page->save();
        
        // Restore blocks
        $page->blocks()->delete();
        
        if (isset($content['blocks'])) {
            foreach ($content['blocks'] as $blockData) {
                PageBlock::create([
                    'page_id' => $page->id,
                    'block_type_id' => $blockData['block_type_id'],
                    'region' => $blockData['region'],
                    'sort_order' => $blockData['sort_order'],
                    'visible' => $blockData['visible'] ?? true,
                ]);
            }
        }
        
        // Create revision after restore
        $this->createRevision($page, $user, "Restored to revision #{$revision->revision_number}");
        
        return $page;
    }
    
    /**
     * Get all revisions for a page
     */
    public function getRevisions(Page $page, int $limit = 20)
    {
        return PageRevision::where('page_id', $page->id)
            ->with('user')
            ->orderBy('revision_number', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Compare two revisions
     */
    public function compare(PageRevision $revision1, PageRevision $revision2): array
    {
        $content1 = $revision1->content;
        $content2 = $revision2->content;
        
        $changes = [];
        
        // Compare fields
        foreach (['name', 'slug', 'status', 'meta_title', 'meta_description'] as $field) {
            $old = $content1[$field] ?? null;
            $new = $content2[$field] ?? null;
            
            if ($old !== $new) {
                $changes[$field] = [
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }
        
        // Compare blocks count
        $blocks1 = count($content1['blocks'] ?? []);
        $blocks2 = count($content2['blocks'] ?? []);
        
        if ($blocks1 !== $blocks2) {
            $changes['blocks_count'] = [
                'old' => $blocks1,
                'new' => $blocks2,
            ];
        }
        
        return $changes;
    }
    
    /**
     * Get next revision number for a page
     */
    private function getNextRevisionNumber(Page $page): int
    {
        $lastRevision = PageRevision::where('page_id', $page->id)
            ->orderBy('revision_number', 'desc')
            ->first();
        
        return $lastRevision ? $lastRevision->revision_number + 1 : 1;
    }
    
    /**
     * Clean old revisions (keep only recent ones)
     */
    private function cleanOldRevisions(Page $page, int $keep = 50): void
    {
        $count = PageRevision::where('page_id', $page->id)->count();
        
        if ($count > $keep) {
            $toDelete = $count - $keep;
            
            PageRevision::where('page_id', $page->id)
                ->orderBy('revision_number', 'asc')
                ->limit($toDelete)
                ->delete();
        }
    }
}

