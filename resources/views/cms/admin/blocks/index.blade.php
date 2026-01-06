@extends('layouts.admin')

@section('title', 'Block Manager')

@section('content')
<div class="container mx-auto px-4 py-8" x-data="blockManager()">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Block Manager</h1>
        <p class="text-gray-600 dark:text-gray-400">Quản lý blocks trên các regions của website</p>
    </div>

    <!-- Region Tabs -->
    <div class="mb-6">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <template x-for="region in regions" :key="region.name">
                    <button
                        @click="currentRegion = region.name; loadRegionBlocks()"
                        :class="{
                            'border-blue-500 text-blue-600': currentRegion === region.name,
                            'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': currentRegion !== region.name
                        }"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                        x-text="region.title"
                    ></button>
                </template>
            </nav>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="mb-6 flex justify-between items-center">
        <div class="flex space-x-2">
            <button
                @click="showAddBlockModal = true"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Block
            </button>
            
            <button
                @click="reorderMode = !reorderMode"
                :class="reorderMode ? 'bg-green-600' : 'bg-gray-600'"
                class="hover:bg-opacity-80 text-white px-4 py-2 rounded-lg flex items-center"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <span x-text="reorderMode ? 'Save Order' : 'Reorder'"></span>
            </button>
        </div>

        <div class="text-sm text-gray-600 dark:text-gray-400">
            <span x-text="blocks.length"></span> blocks in <span x-text="currentRegion"></span>
        </div>
    </div>

    <!-- Blocks List -->
    <div class="space-y-4">
        <template x-for="(block, index) in blocks" :key="block.id">
            <div
                :class="{
                    'border-l-4 border-green-500': block.visible,
                    'border-l-4 border-gray-400 opacity-60': !block.visible,
                    'cursor-move': reorderMode
                }"
                class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 hover:shadow-lg transition-shadow"
                :draggable="reorderMode"
                @dragstart="dragStart(index)"
                @dragover.prevent
                @drop="drop(index)"
            >
                <div class="flex items-center justify-between">
                    <!-- Block Info -->
                    <div class="flex items-center space-x-4 flex-1">
                        <div v-if="reorderMode" class="text-gray-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                            </svg>
                        </div>
                        
                        <div>
                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="'#' + block.id"></span>
                        </div>

                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="block.title"></h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Type: <span class="font-mono" x-text="block.block_type_id"></span> | 
                                Weight: <span x-text="block.weight"></span> |
                                Context: <span x-text="block.context_type"></span>
                            </p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div v-show="!reorderMode" class="flex items-center space-x-2">
                        <!-- Visibility Toggle -->
                        <button
                            @click="toggleVisibility(block)"
                            :class="block.visible ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                            class="p-2 rounded-lg hover:bg-opacity-80"
                            :title="block.visible ? 'Hide' : 'Show'"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path v-if="block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                <path v-if="!block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>

                        <!-- Move Up -->
                        <button
                            @click="moveUp(block, index)"
                            :disabled="index === 0"
                            class="p-2 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Move Up"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>
                        </button>

                        <!-- Move Down -->
                        <button
                            @click="moveDown(block, index)"
                            :disabled="index === blocks.length - 1"
                            class="p-2 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Move Down"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Edit -->
                        <button
                            @click="editBlock(block)"
                            class="p-2 rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200"
                            title="Edit"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>

                        <!-- Duplicate -->
                        <button
                            @click="duplicateBlock(block)"
                            class="p-2 rounded-lg bg-purple-100 text-purple-700 hover:bg-purple-200"
                            title="Duplicate"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>

                        <!-- Delete -->
                        <button
                            @click="deleteBlock(block)"
                            class="p-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200"
                            title="Delete"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div v-show="blocks.length === 0" class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
            <p class="mt-4 text-gray-500 dark:text-gray-400">No blocks in this region</p>
            <button
                @click="showAddBlockModal = true"
                class="mt-4 text-blue-600 hover:text-blue-700 font-medium"
            >
                Add your first block
            </button>
        </div>
    </div>

    <!-- Add/Edit Block Modal -->
    <div
        x-show="showAddBlockModal || editingBlock"
        x-cloak
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        @click.self="closeModal()"
    >
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6" x-text="editingBlock ? 'Edit Block' : 'Add New Block'"></h2>

                <form @submit.prevent="saveBlock()">
                    <!-- Block Type -->
                    <div class="mb-4" v-show="!editingBlock">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Block Type</label>
                        <select
                            x-model="blockForm.block_type"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            required
                        >
                            <option value="">Select block type...</option>
                            <template x-for="type in blockTypes" :key="type.name">
                                <option :value="type.name" x-text="type.title + ' - ' + type.description"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Title -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title</label>
                        <input
                            type="text"
                            x-model="blockForm.title"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            required
                        >
                    </div>

                    <!-- Content -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Content</label>
                        <textarea
                            x-model="blockForm.content"
                            rows="6"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm"
                        ></textarea>
                    </div>

                    <!-- Config JSON -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Configuration (JSON)</label>
                        <textarea
                            x-model="blockForm.config"
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm"
                            placeholder='{"show_title": true}'
                        ></textarea>
                    </div>

                    <!-- Context -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Context Type</label>
                            <select
                                x-model="blockForm.context_type"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            >
                                <option value="global">Global</option>
                                <option value="page">Page</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Context ID</label>
                            <input
                                type="number"
                                x-model="blockForm.context_id"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                            >
                        </div>
                    </div>

                    <!-- Visible -->
                    <div class="mb-6">
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                x-model="blockForm.visible"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Visible</span>
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="closeModal()"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            <span x-text="editingBlock ? 'Update' : 'Create'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function blockManager() {
    return {
        regions: [],
        currentRegion: null,
        blocks: [],
        blockTypes: [],
        showAddBlockModal: false,
        editingBlock: null,
        reorderMode: false,
        draggedIndex: null,
        blockForm: {
            block_type: '',
            title: '',
            content: '',
            config: '{}',
            context_type: 'global',
            context_id: null,
            visible: true
        },

        async init() {
            await this.loadRegions();
            await this.loadBlockTypes();
            if (this.regions.length > 0) {
                this.currentRegion = this.regions[0].name;
                await this.loadRegionBlocks();
            }
        },

        async loadRegions() {
            try {
                const response = await fetch('/admin/blocks/regions');
                if (!response.ok) throw new Error('Failed to load regions');
                const data = await response.json();
                this.regions = data.regions;
            } catch (error) {
                console.error('Error loading regions:', error);
                alert('Failed to load regions. Please refresh the page.');
            }
        },

        async loadBlockTypes() {
            try {
                const response = await fetch('/admin/blocks/types');
                if (!response.ok) throw new Error('Failed to load block types');
                const data = await response.json();
                this.blockTypes = data.block_types;
            } catch (error) {
                console.error('Error loading block types:', error);
                alert('Failed to load block types. Please refresh the page.');
            }
        },

        async loadRegionBlocks() {
            try {
                const response = await fetch(`/admin/blocks/regions/${this.currentRegion}/blocks`);
                if (!response.ok) throw new Error('Failed to load blocks');
                const data = await response.json();
                this.blocks = data.blocks;
            } catch (error) {
                console.error('Error loading blocks:', error);
                alert('Failed to load blocks. Please refresh the page.');
            }
        },

        async saveBlock() {
            try {
                const url = this.editingBlock
                    ? `/admin/blocks/${this.editingBlock.id}`
                    : '/admin/blocks';
                
                const method = this.editingBlock ? 'PUT' : 'POST';
                
                let config = {};
                try {
                    config = JSON.parse(this.blockForm.config || '{}');
                } catch (e) {
                    alert('Invalid JSON in configuration field');
                    return;
                }
                
                const payload = {
                    ...this.blockForm,
                    region: this.currentRegion,
                    config
                };

                const response = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to save block');
                }

                await this.loadRegionBlocks();
                this.closeModal();
            } catch (error) {
                console.error('Error saving block:', error);
                alert('Failed to save block: ' + error.message);
            }
        },

        editBlock(block) {
            this.editingBlock = block;
            this.blockForm = {
                block_type: block.block_type_id,
                title: block.title,
                content: block.content || '',
                config: JSON.stringify(block.config || {}),
                context_type: block.context_type,
                context_id: block.context_id,
                visible: block.visible
            };
        },

        closeModal() {
            this.showAddBlockModal = false;
            this.editingBlock = null;
            this.blockForm = {
                block_type: '',
                title: '',
                content: '',
                config: '{}',
                context_type: 'global',
                context_id: null,
                visible: true
            };
        },

        async toggleVisibility(block) {
            await fetch(`/admin/blocks/${block.id}/toggle-visibility`, { method: 'POST' });
            await this.loadRegionBlocks();
        },

        async moveUp(block, index) {
            if (index === 0) return;
            await fetch(`/admin/blocks/${block.id}/move-up`, { method: 'POST' });
            await this.loadRegionBlocks();
        },

        async moveDown(block, index) {
            if (index === this.blocks.length - 1) return;
            await fetch(`/admin/blocks/${block.id}/move-down`, { method: 'POST' });
            await this.loadRegionBlocks();
        },

        async duplicateBlock(block) {
            await fetch(`/admin/blocks/${block.id}/duplicate`, { method: 'POST' });
            await this.loadRegionBlocks();
        },

        async deleteBlock(block) {
            if (confirm(`Delete block "${block.title}"?`)) {
                await fetch(`/admin/blocks/${block.id}`, { method: 'DELETE' });
                await this.loadRegionBlocks();
            }
        },

        dragStart(index) {
            this.draggedIndex = index;
        },

        async drop(dropIndex) {
            if (this.draggedIndex === null) return;
            
            const newOrder = [...this.blocks];
            const [dragged] = newOrder.splice(this.draggedIndex, 1);
            newOrder.splice(dropIndex, 0, dragged);
            
            const blockIds = newOrder.map(b => b.id);
            
            await fetch('/admin/blocks/reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ block_ids: blockIds })
            });
            
            await this.loadRegionBlocks();
            this.draggedIndex = null;
        }
    }
}
</script>
@endpush

@section('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
@endsection

