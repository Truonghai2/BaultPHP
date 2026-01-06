<?php

declare(strict_types=1);

namespace Modules\Cms\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Cms\Application\Services\PageAggregateService;
use Modules\Cms\Application\Services\PageBlockAggregateService;

/**
 * Page Event Sourcing Command
 *
 * Demonstrates Event Sourcing usage with Page aggregate in CMS.
 *
 * Usage examples:
 * ```
 * # Create a page
 * php cli cms:event-sourcing create --name="My Page" --slug="my-page"
 *
 * # Update page content
 * php cli cms:event-sourcing update-content --page-id=<id> --title="New Title"
 *
 * # Publish page
 * php cli cms:event-sourcing publish --page-id=<id>
 *
 * # Show page state
 * php cli cms:event-sourcing show --page-id=<id>
 *
 * # Add block to page
 * php cli cms:event-sourcing add-block --page-id=<id> --component="TextBlock"
 * ```
 */
class PageEventSourcingCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'cms:event-sourcing 
                {action : Action (create|update-content|rename|publish|unpublish|delete|restore|add-block|show|history)}
                {--page-id= : Page ID}
                {--block-id= : Block ID}
                {--name= : Page name}
                {--slug= : Page slug}
                {--title= : Content title}
                {--body= : Content body}
                {--component= : Block component class}
                {--order= : Block sort order}
                {--image= : Featured image path}
                {--reason= : Deletion reason}
                {--user-id=1 : User ID performing action}';
    }

    public function description(): string
    {
        return 'Demonstrate Event Sourcing with CMS Page aggregate';
    }

    public function handle(): int
    {
        /** @var PageAggregateService $pageService */
        $pageService = $this->app->make(PageAggregateService::class);

        /** @var PageBlockAggregateService $blockService */
        $blockService = $this->app->make(PageBlockAggregateService::class);

        $action = $this->argument('action');
        $userId = (string) $this->option('user-id');

        try {
            switch ($action) {
                case 'create':
                    return $this->createPage($pageService, $userId);
                case 'update-content':
                    return $this->updateContent($pageService, $userId);
                case 'rename':
                    return $this->renamePage($pageService, $userId);
                case 'publish':
                    return $this->publishPage($pageService, $userId);
                case 'unpublish':
                    return $this->unpublishPage($pageService, $userId);
                case 'delete':
                    return $this->deletePage($pageService, $userId);
                case 'restore':
                    return $this->restorePage($pageService, $userId);
                case 'add-block':
                    return $this->addBlock($pageService, $blockService, $userId);
                case 'show':
                    return $this->showPage($pageService);
                case 'history':
                    return $this->showHistory($pageService);
                default:
                    $this->io->error("Unknown action: {$action}");
                    $this->showHelp();
                    return self::FAILURE;
            }
        } catch (\DomainException $e) {
            $this->io->error("Domain Error: {$e->getMessage()}");
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->io->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function createPage(PageAggregateService $service, string $userId): int
    {
        $name = $this->option('name') ?? $this->io->ask('Page Name:', 'My Awesome Page');
        $slug = $this->option('slug') ?? $service->suggestSlug($name);

        if (!$this->option('slug')) {
            $slug = $this->io->ask('Page Slug:', $slug);
        }

        $this->io->writeln('<info>Creating page via Event Sourcing...</info>');

        $pageId = $service->createPage($name, $slug, (int) $userId);

        $this->io->success('Page created successfully!');
        $this->io->table(
            ['Field', 'Value'],
            [
                ['Page ID', $pageId],
                ['Name', $name],
                ['Slug', $slug],
                ['Status', 'draft'],
                ['User ID', $userId],
            ],
        );

        $this->io->newLine();
        $this->io->writeln('<comment>✓ Events saved to event store</comment>');
        $this->io->writeln("<comment>✓ Use this ID for other actions: --page-id={$pageId}</comment>");
        $this->io->newLine();
        $this->showNextSteps($pageId);

        return self::SUCCESS;
    }

    private function updateContent(PageAggregateService $service, string $userId): int
    {
        $pageId = $this->getRequiredOption('page-id', 'Page ID is required');

        $title = $this->option('title') ?? $this->io->ask('Content Title:', 'Page Title');
        $body = $this->option('body') ?? $this->io->ask('Content Body:', 'Page content goes here...');

        $content = [
            'title' => $title,
            'body' => $body,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->io->writeln('<info>Updating page content via Event Sourcing...</info>');

        $service->updatePageContent($pageId, $content, $userId);

        $this->io->success('Content updated successfully!');
        $this->showPage($service);

        return self::SUCCESS;
    }

    private function renamePage(PageAggregateService $service, string $userId): int
    {
        $pageId = $this->getRequiredOption('page-id', 'Page ID is required');

        $newName = $this->option('name') ?? $this->io->ask('New Name:');
        $newSlug = $this->option('slug') ?? $service->suggestSlug($newName);

        if (!$this->option('slug')) {
            $newSlug = $this->io->ask('New Slug:', $newSlug);
        }

        $this->io->writeln('<info>Renaming page via Event Sourcing...</info>');

        $service->renamePage($pageId, $newName, $newSlug, $userId);

        $this->io->success('Page renamed successfully!');
        $this->showPage($service);

        return self::SUCCESS;
    }

    private function publishPage(PageAggregateService $service, string $userId): int
    {
        $pageId = $this->getRequiredOption('page-id', 'Page ID is required');

        $this->io->writeln('<info>Publishing page via Event Sourcing...</info>');

        $service->publishPage($pageId, $userId);

        $this->io->success('Page published successfully!');
        $this->showPage($service);

        return self::SUCCESS;
    }

    private function unpublishPage(PageAggregateService $service, string $userId): int
    {
        $pageId = $this->getRequiredOption('page-id', 'Page ID is required');

        $this->io->writeln('<info>Unpublishing page via Event Sourcing...</info>');

        $service->unpublishPage($pageId, $userId);

        $this->io->success('Page unpublished successfully!');
        $this->showPage($service);

        return self::SUCCESS;
    }

    private function deletePage(PageAggregateService $service, string $userId): int
    {
        $pageId = $this->getRequiredOption('page-id', 'Page ID is required');
        $reason = $this->option('reason') ?? $this->io->ask('Deletion Reason:', 'No longer needed');

        $this->io->writeln('<info>Deleting page via Event Sourcing...</info>');

        $service->deletePage($pageId, $userId, $reason);

        $this->io->success('Page deleted successfully!');
        $this->io->writeln('<comment>Page can be restored using: restore --page-id=' . $pageId . '</comment>');
        $this->showPage($service);

        return self::SUCCESS;
    }

    private function restorePage(PageAggregateService $service, string $userId): int
    {
        $pageId = $this->getRequiredOption('page-id', 'Page ID is required');

        $this->io->writeln('<info>Restoring page via Event Sourcing...</info>');

        $service->restorePage($pageId, $userId);

        $this->io->success('Page restored successfully!');
        $this->showPage($service);

        return self::SUCCESS;
    }

    private function addBlock(
        PageAggregateService $pageService,
        PageBlockAggregateService $blockService,
        string $userId,
    ): int {
        $pageId = $this->getRequiredOption('page-id', 'Page ID is required');

        $component = $this->option('component') ?? $this->io->ask('Component Class:', 'TextBlock');
        $order = (int) ($this->option('order') ?? $this->io->ask('Sort Order:', '0'));

        $this->io->writeln('<info>Creating block and adding to page via Event Sourcing...</info>');

        // Create block
        $blockId = $blockService->createBlock($pageId, $component, $order, $userId);

        // Add to page
        $pageService->addBlockToPage($pageId, $blockId, $component, $order, $userId);

        $this->io->success('Block added successfully!');
        $this->io->table(
            ['Field', 'Value'],
            [
                ['Block ID', $blockId],
                ['Component', $component],
                ['Order', $order],
            ],
        );

        $this->io->newLine();
        $this->showPage($pageService);

        return self::SUCCESS;
    }

    private function showPage(PageAggregateService $service): int
    {
        $pageId = $this->getRequiredOption('page-id', 'Page ID is required');

        $state = $service->getPageState($pageId);

        if (!$state) {
            $this->io->warning("Page {$pageId} not found in event store");
            return self::FAILURE;
        }

        $this->io->writeln('<info>Page State (reconstituted from events):</info>');

        $tableData = [
            ['Page ID', $state['id']],
            ['Name', $state['name']],
            ['Slug', $state['slug']],
            ['Status', $state['status']],
            ['Is Published', $state['is_published'] ? 'Yes' : 'No'],
            ['Is Deleted', $state['is_deleted'] ? 'Yes' : 'No'],
            ['Block Count', $state['block_count']],
            ['Version', $state['version']],
        ];

        if ($state['published_at']) {
            $tableData[] = ['Published At', $state['published_at']];
        }

        if ($state['deleted_at']) {
            $tableData[] = ['Deleted At', $state['deleted_at']];
        }

        $this->io->table(['Field', 'Value'], $tableData);

        // Show SEO Score
        $seoScore = $state['seo_score'];
        $this->io->newLine();
        $this->io->writeln('<comment>SEO Analysis:</comment>');
        $this->io->table(
            ['Metric', 'Value'],
            [
                ['Score', $seoScore['score'] . '/100'],
                ['Rating', $seoScore['rating']],
            ],
        );

        if (!empty($seoScore['issues'])) {
            $this->io->writeln('<comment>Issues:</comment>');
            foreach ($seoScore['issues'] as $issue) {
                $this->io->writeln("  - {$issue}");
            }
        }

        return self::SUCCESS;
    }

    private function showHistory(PageAggregateService $service): int
    {
        $pageId = $this->getRequiredOption('page-id', 'Page ID is required');

        $history = $service->getPageHistory($pageId);

        if (empty($history)) {
            $this->io->warning("No history found for page {$pageId}");
            return self::FAILURE;
        }

        $this->io->writeln('<info>Page History:</info>');
        $this->io->table(
            ['Field', 'Value'],
            [
                ['Page ID', $history['page_id']],
                ['Current Version', $history['current_version']],
            ],
        );

        $this->io->newLine();
        $this->io->writeln('<comment>Full event stream would be displayed here in production</comment>');
        $this->io->writeln('<comment>Showing current state:</comment>');

        return $this->showPage($service);
    }

    private function showHelp(): void
    {
        $this->io->newLine();
        $this->io->writeln('<comment>Available actions:</comment>');
        $this->io->writeln('  create          - Create a new page');
        $this->io->writeln('  update-content  - Update page content');
        $this->io->writeln('  rename          - Rename page');
        $this->io->writeln('  publish         - Publish page');
        $this->io->writeln('  unpublish       - Unpublish page');
        $this->io->writeln('  delete          - Delete page (soft delete)');
        $this->io->writeln('  restore         - Restore deleted page');
        $this->io->writeln('  add-block       - Add block to page');
        $this->io->writeln('  show            - Show page state');
        $this->io->writeln('  history         - Show page history');
        $this->io->newLine();
    }

    private function showNextSteps(string $pageId): void
    {
        $this->io->writeln('<comment>Next steps you can try:</comment>');
        $this->io->writeln("  1. Add content:    php cli cms:event-sourcing update-content --page-id={$pageId}");
        $this->io->writeln("  2. Add a block:    php cli cms:event-sourcing add-block --page-id={$pageId}");
        $this->io->writeln("  3. Publish page:   php cli cms:event-sourcing publish --page-id={$pageId}");
        $this->io->writeln("  4. View state:     php cli cms:event-sourcing show --page-id={$pageId}");
        $this->io->writeln("  5. View history:   php cli cms:event-sourcing history --page-id={$pageId}");
    }

    private function getRequiredOption(string $name, string $message): string
    {
        $value = $this->option($name);

        if (!$value) {
            $this->io->error($message);
            throw new \RuntimeException($message);
        }

        return (string) $value;
    }
}
