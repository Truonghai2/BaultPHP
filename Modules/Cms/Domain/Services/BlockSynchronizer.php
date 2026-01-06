<?php

namespace Modules\Cms\Domain\Services;

use Core\Support\Facades\Log;
use Modules\Cms\Infrastructure\Models\BlockType;

/**
 * Class BlockSynchronizer
 *
 * Synchronize block types from code (BlockRegistry) to database.
 * - Add new blocks.
 * - Update information of changed blocks (title, description, category, config).
 * - Remove blocks that no longer exist in code.
 */
class BlockSynchronizer
{
    public function __construct(private BlockRegistry $blockRegistry)
    {
    }

    /**
     * Perform the synchronization process.
     *
     * @return array Result of synchronization ['added' => [], 'updated' => [], 'removed' => []]
     */
    public function sync(): array
    {
        Log::info('Starting block types synchronization...');

        $registeredBlocks = $this->blockRegistry->getBlocks();
        $databaseBlocks = BlockType::all()->keyBy('name');

        $registeredBlockNames = array_keys($registeredBlocks);
        $databaseBlockNames = $databaseBlocks->keys()->toArray();

        $newBlockNames = array_diff($registeredBlockNames, $databaseBlockNames);
        $removedBlockNames = array_diff($databaseBlockNames, $registeredBlockNames);
        $existingBlockNames = array_intersect($registeredBlockNames, $databaseBlockNames);

        $added = $this->addNewBlocks($newBlockNames, $registeredBlocks);
        $updated = $this->updateExistingBlocks($existingBlockNames, $registeredBlocks, $databaseBlocks);
        $removed = $this->removeStaleBlocks($removedBlockNames);

        $result = compact('added', 'updated', 'removed');

        if (array_sum(array_map('count', $result)) > 0) {
            Log::info('Block types synchronization completed.', $result);
        } else {
            Log::info('No changes found in block types.');
        }

        return $result;
    }

    private function addNewBlocks(array $names, array $registeredBlocks): array
    {
        $added = [];
        foreach ($names as $name) {
            $block = $registeredBlocks[$name];
            BlockType::create([
                'name' => $block->getName(),
                'title' => $block->getTitle(),
                'description' => $block->getDescription(),
                'class' => get_class($block),
                'category' => $block->getCategory(),
                'icon' => $block->getIcon(),
                'default_config' => $block->getDefaultConfig(),
                'configurable' => $block->isConfigurable(),
                'is_active' => true,
                'version' => $block->getVersion(),
            ]);
            $added[] = $name;
        }
        return $added;
    }

    private function updateExistingBlocks(array $names, array $registeredBlocks, $databaseBlocks): array
    {
        $updated = [];
        foreach ($names as $name) {
            $block = $registeredBlocks[$name];
            $dbBlock = $databaseBlocks[$name];

            $updates = [
                'title' => $block->getTitle(),
                'description' => $block->getDescription(),
                'class' => get_class($block),
                'category' => $block->getCategory(),
                'icon' => $block->getIcon(),
                'default_config' => $block->getDefaultConfig(),
                'configurable' => $block->isConfigurable(),
                'is_active' => true,
                'version' => $block->getVersion(),
            ];

            $dbBlock->fill($updates);
            if ($dbBlock->isDirty()) {
                $dbBlock->save();
                $updated[] = $name;
            }
        }
        return $updated;
    }

    private function removeStaleBlocks(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        Log::warning('Removing block types that no longer exist in code.', ['blocks' => $names]);
        BlockType::whereIn('name', $names)->delete();

        return $names;
    }
}
