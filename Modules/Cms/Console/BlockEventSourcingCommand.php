<?php

declare(strict_types=1);

namespace Modules\Cms\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Cms\Application\Services\PageBlockAggregateService;

/**
 * Block Event Sourcing Command
 * 
 * Demonstrates Event Sourcing for block operations
 */
class BlockEventSourcingCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'cms:block-event-sourcing 
                {action : Action (create|update|reorder|duplicate|delete|restore|show)}
                {--page-id= : Page ID (for create)}
                {--block-id= : Block ID}
                {--component= : Component class}
                {--order= : Sort order}
                {--title= : Content title}
                {--body= : Content body}
                {--user-id=1 : User ID}';
    }

    public function description(): string
    {
        return 'Demonstrate Event Sourcing with Page Block aggregate';
    }

    public function handle(): int
    {
        /** @var PageBlockAggregateService $service */
        $service = $this->app->make(PageBlockAggregateService::class);

        $action = $this->argument('action');
        $userId = (string) $this->option('user-id');

        try {
            switch ($action) {
                case 'create':
                    return $this->createBlock($service, $userId);

                case 'update':
                    return $this->updateBlock($service, $userId);

                case 'reorder':
                    return $this->reorderBlock($service, $userId);

                case 'duplicate':
                    return $this->duplicateBlock($service, $userId);

                case 'delete':
                    return $this->deleteBlock($service, $userId);

                case 'restore':
                    return $this->restoreBlock($service, $userId);

                case 'show':
                    return $this->showBlock($service);

                default:
                    $this->io->error("Unknown action: {$action}");
                    return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function createBlock(PageBlockAggregateService $service, string $userId): int
    {
        $pageId = $this->option('page-id') ?? $this->io->ask('Page ID:');
        $component = $this->option('component') ?? $this->io->ask('Component Class:', 'TextBlock');
        $order = (int) ($this->option('order') ?? $this->io->ask('Sort Order:', '0'));

        $this->io->writeln('<info>Creating block via Event Sourcing...</info>');

        $blockId = $service->createBlock($pageId, $component, $order, $userId);

        $this->io->success('Block created!');
        $this->io->writeln("<comment>Block ID: {$blockId}</comment>");
        $this->io->writeln("<comment>Use: --block-id={$blockId} for other operations</comment>");

        return self::SUCCESS;
    }

    private function updateBlock(PageBlockAggregateService $service, string $userId): int
    {
        $blockId = $this->getRequiredOption('block-id');

        $title = $this->option('title') ?? $this->io->ask('Title:', 'Block Title');
        $body = $this->option('body') ?? $this->io->ask('Body:', 'Block content...');

        $content = [
            'title' => $title,
            'body' => $body
        ];

        $this->io->writeln('<info>Updating block content...</info>');

        $service->updateBlockContent($blockId, $content, $userId);

        $this->io->success('Block updated!');
        $this->showBlock($service);

        return self::SUCCESS;
    }

    private function reorderBlock(PageBlockAggregateService $service, string $userId): int
    {
        $blockId = $this->getRequiredOption('block-id');
        $newOrder = (int) ($this->option('order') ?? $this->io->ask('New Order:'));

        $service->changeBlockOrder($blockId, $newOrder, $userId);

        $this->io->success('Block reordered!');
        $this->showBlock($service);

        return self::SUCCESS;
    }

    private function duplicateBlock(PageBlockAggregateService $service, string $userId): int
    {
        $blockId = $this->getRequiredOption('block-id');
        $newOrder = (int) ($this->option('order') ?? $this->io->ask('New Order:', '0'));

        $newBlockId = $service->duplicateBlock($blockId, $newOrder, $userId);

        $this->io->success('Block duplicated!');
        $this->io->writeln("<comment>New Block ID: {$newBlockId}</comment>");

        return self::SUCCESS;
    }

    private function deleteBlock(PageBlockAggregateService $service, string $userId): int
    {
        $blockId = $this->getRequiredOption('block-id');

        $service->deleteBlock($blockId, $userId);

        $this->io->success('Block deleted!');

        return self::SUCCESS;
    }

    private function restoreBlock(PageBlockAggregateService $service, string $userId): int
    {
        $blockId = $this->getRequiredOption('block-id');

        $service->restoreBlock($blockId, $userId);

        $this->io->success('Block restored!');
        $this->showBlock($service);

        return self::SUCCESS;
    }

    private function showBlock(PageBlockAggregateService $service): int
    {
        $blockId = $this->getRequiredOption('block-id');

        $state = $service->getBlockState($blockId);

        if (!$state) {
            $this->io->warning("Block {$blockId} not found");
            return self::FAILURE;
        }

        $this->io->writeln('<info>Block State:</info>');
        $this->io->table(
            ['Field', 'Value'],
            [
                ['Block ID', $state['id']],
                ['Page ID', $state['page_id']],
                ['Component', $state['component_class']],
                ['Order', $state['sort_order']],
                ['Is Deleted', $state['is_deleted'] ? 'Yes' : 'No'],
                ['Version', $state['version']]
            ]
        );

        if (!empty($state['content'])) {
            $this->io->writeln('<comment>Content:</comment>');
            $this->io->writeln(json_encode($state['content'], JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }

    private function getRequiredOption(string $name): string
    {
        $value = $this->option($name);
        
        if (!$value) {
            throw new \RuntimeException("--{$name} is required");
        }

        return (string) $value;
    }
}

