@extends('layouts.admin')

@section('title', 'Page Editor')

@section('content')
<div class="container mx-auto px-4 py-8" x-data="pageEditor({{ $pageId }})">
    <!-- Header -->
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Page Editor</h1>
            <p class="text-gray-600 dark:text-gray-400">Edit page content and blocks</p>
        </div>
        <div class="flex space-x-2">
            <button
                @click="saveAll()"
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg flex items-center"
                :disabled="!hasChanges"
                :class="{'opacity-50 cursor-not-allowed': !hasChanges}"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                Save Changes
            </button>
            <button
                @click="undo()"
                :disabled="historyIndex === 0"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                title="Undo"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </svg>
            </button>
            <button
                @click="redo()"
                :disabled="historyIndex === history.length - 1"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                title="Redo"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Page Info -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Page Title</label>
                <input
                    type="text"
                    x-model="page.name"
                    @input="markChanged()"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Slug</label>
                <input
                    type="text"
                    x-model="page.slug"
                    @input="markChanged()"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm"
                >
            </div>
        </div>
    </div>

    <!-- Block Type Selector -->
    <div class="mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add New Block</h3>
                <a href="/admin/cms/blocks/visual" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                    Switch to Visual Editor →
                </a>
            </div>
            
            <template x-if="loadingBlockTypes">
                <div class="text-center py-8 text-gray-500">
                    <svg class="animate-spin w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading block types...
                </div>
            </template>
            
            <div x-show="!loadingBlockTypes" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <template x-for="blockType in blockTypes" :key="blockType.name">
                    <button
                        @click="addBlock(blockType.name)"
                        class="p-4 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-gray-700 transition-all"
                        :title="blockType.description"
                    >
                        <div class="text-3xl mx-auto mb-2" x-text="blockType.icon || '📦'"></div>
                        <span class="text-xs font-medium text-gray-900 dark:text-white block truncate" x-text="blockType.title"></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 block mt-1" x-text="blockType.category"></span>
                    </button>
                </template>
            </div>
            
            <div x-show="!loadingBlockTypes && blockTypes.length === 0" class="text-center py-8 text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p>No block types available</p>
            </div>
        </div>
    </div>

    <!-- Blocks List -->
    <div class="space-y-4">
        <template x-for="(block, index) in blocks" :key="block.id">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="text-gray-400 cursor-move">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                            </svg>
                        </div>
                        <span class="text-xs text-gray-500" x-text="'#' + block.id"></span>
                        <span class="font-semibold text-gray-900 dark:text-white" x-text="block.block_type_title || block.block_type_name"></span>
                    </div>
                    <div class="flex space-x-2">
                        <button
                            @click="moveBlockUp(index)"
                            :disabled="index === 0"
                            class="p-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 disabled:opacity-50"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>
                        </button>
                        <button
                            @click="moveBlockDown(index)"
                            :disabled="index === blocks.length - 1"
                            class="p-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 disabled:opacity-50"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <button
                            @click="duplicateBlock(index)"
                            class="p-2 bg-purple-100 text-purple-700 rounded hover:bg-purple-200"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                        <button
                            @click="deleteBlock(index)"
                            class="p-2 bg-red-100 text-red-700 rounded hover:bg-red-200"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div>
                    <textarea
                        x-model="block.content.text"
                        @input="updateBlockContent(index)"
                        rows="6"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm"
                        placeholder="Block content..."
                    ></textarea>
                </div>
            </div>
        </template>

        <div v-show="blocks.length === 0" class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="mt-4 text-gray-500 dark:text-gray-400">No blocks yet. Add your first block above.</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
function pageEditor(pageId) {
    return {
        pageId,
        page: {},
        blocks: [],
        blockTypes: [],
        loadingBlockTypes: true,
        hasChanges: false,
        history: [],
        historyIndex: -1,

        async init() {
            await Promise.all([
                this.loadPage(),
                this.loadBlockTypes()
            ]);
            this.saveState();
        },

        async loadBlockTypes() {
            try {
                const response = await fetch('/admin/blocks/types');
                const data = await response.json();
                this.blockTypes = data.block_types || [];
            } catch (error) {
                console.error('Failed to load block types:', error);
                this.blockTypes = [];
            } finally {
                this.loadingBlockTypes = false;
            }
        },

        async loadPage() {
            // Load page data and blocks
            // const response = await fetch(`/api/pages/${this.pageId}`);
            // const data = await response.json();
            // this.page = data.page;
            // this.blocks = data.blocks || [];
            
            // Demo data
            this.page = {
                id: pageId,
                name: 'Sample Page',
                slug: 'sample-page'
            };
            this.blocks = [];
        },

        addBlock(blockTypeName) {
            const blockType = this.blockTypes.find(bt => bt.name === blockTypeName);
            const newBlock = {
                id: Date.now(),
                block_type_name: blockTypeName,
                block_type_title: blockType ? blockType.title : blockTypeName,
                order: this.blocks.length,
                content: blockType?.default_config || {},
                visible: true
            };
            this.blocks.push(newBlock);
            this.markChanged();
            this.saveState();
        },

        updateBlockContent(index) {
            this.markChanged();
        },

        moveBlockUp(index) {
            if (index === 0) return;
            [this.blocks[index], this.blocks[index - 1]] = [this.blocks[index - 1], this.blocks[index]];
            this.updateBlockOrders();
            this.markChanged();
            this.saveState();
        },

        moveBlockDown(index) {
            if (index === this.blocks.length - 1) return;
            [this.blocks[index], this.blocks[index + 1]] = [this.blocks[index + 1], this.blocks[index]];
            this.updateBlockOrders();
            this.markChanged();
            this.saveState();
        },

        duplicateBlock(index) {
            const original = this.blocks[index];
            const duplicate = { ...original, id: Date.now() };
            this.blocks.splice(index + 1, 0, duplicate);
            this.updateBlockOrders();
            this.markChanged();
            this.saveState();
        },

        deleteBlock(index) {
            if (confirm('Delete this block?')) {
                this.blocks.splice(index, 1);
                this.updateBlockOrders();
                this.markChanged();
                this.saveState();
            }
        },

        updateBlockOrders() {
            this.blocks.forEach((block, i) => {
                block.order = i;
            });
        },

        markChanged() {
            this.hasChanges = true;
        },

        async saveAll() {
            // Save page and blocks
            console.log('Saving...', this.page, this.blocks);
            this.hasChanges = false;
            // await fetch(`/api/pages/${this.pageId}`, {
            //     method: 'PUT',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({ page: this.page, blocks: this.blocks })
            // });
        },

        saveState() {
            const state = JSON.stringify({ page: this.page, blocks: this.blocks });
            this.history.splice(this.historyIndex + 1);
            this.history.push(state);
            this.historyIndex = this.history.length - 1;
        },

        undo() {
            if (this.historyIndex > 0) {
                this.historyIndex--;
                this.restoreState();
            }
        },

        redo() {
            if (this.historyIndex < this.history.length - 1) {
                this.historyIndex++;
                this.restoreState();
            }
        },

        restoreState() {
            const state = JSON.parse(this.history[this.historyIndex]);
            this.page = state.page;
            this.blocks = state.blocks;
            this.markChanged();
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

