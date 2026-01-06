@extends('layouts.admin')

@push('scripts-head')<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script><script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.x.x/dist/cdn.min.js"></script>@endpush
@section('title', 'Block Visual Editor')

@section('content')
<div class="h-full" x-data="visualBlockEditor()">
    <!-- Top Bar -->
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Page Layout</h1>
            <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-600 dark:text-gray-400">Context:</label>
                <select 
                    x-model="contextType" 
                    @change="loadBlocks()"
                    class="text-sm border-gray-300 rounded-lg"
                >
                    <option value="global">Global</option>
                    <option value="page">Current Page</option>
                    <option value="user">User Specific</option>
                </select>
            </div>
        </div>
        
        <div class="flex items-center space-x-3">
            <button
                @click="toggleEditMode()"
                :class="editMode ? 'bg-green-600' : 'bg-blue-600'"
                class="px-4 py-2 text-white rounded-lg hover:opacity-90 flex items-center"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span x-text="editMode ? 'Editing...' : 'Edit Mode'"></span>
            </button>
            
            <button
                @click="saveLayout()"
                :disabled="!hasChanges"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                Save Layout
            </button>
        </div>
    </div>

    <div class="flex h-[calc(100vh-180px)]">
        <!-- Add Block Sidebar -->
        <div 
            x-show="editMode" 
            x-transition
            class="w-80 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 overflow-y-auto"
        >
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add a block
                </h2>
                
                <!-- Search -->
                <input
                    type="search"
                    x-model="blockSearch"
                    placeholder="Search blocks..."
                    class="w-full px-4 py-2 mb-4 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm"
                >

                <!-- Block Categories -->
                <template x-for="category in filteredCategories" :key="category">
                    <div class="mb-6">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3" x-text="category"></h3>
                        
                        <div class="space-y-2">
                            <template x-for="blockType in getBlocksByCategory(category)" :key="blockType.name">
                                <div
                                    @click="selectBlockType(blockType)"
                                    draggable="true"
                                    @dragstart="dragStart($event, blockType)"
                                    class="p-3 border-2 border-gray-200 dark:border-gray-600 rounded-lg hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-gray-700 cursor-move transition-all"
                                >
                                    <div class="flex items-center">
                                        <div class="text-2xl mr-3" x-text="blockType.icon || '📦'"></div>
                                        <div class="flex-1">
                                            <div class="font-semibold text-sm text-gray-900 dark:text-white" x-text="blockType.title"></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400" x-text="blockType.description"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- No results -->
                <div x-show="filteredCategories.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <p>No blocks found</p>
                </div>
            </div>
        </div>

        <!-- Page Layout Preview -->
        <div class="flex-1 bg-gray-100 dark:bg-gray-900 overflow-y-auto p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Layout Grid -->
                <div class="grid grid-cols-12 gap-6">
                    <!-- Header Region (Full Width) -->
                    <div class="col-span-12">
                        <div
                            x-data="blockRegion('header')"
                            @drop="drop($event, 'header')"
                            @dragover.prevent
                            @dragenter="dragEnter()"
                            @dragleave="dragLeave()"
                            :class="{'ring-4 ring-blue-500': isDragOver}"
                            class="min-h-32 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 transition-all"
                        >
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-gray-700 dark:text-gray-300 text-sm uppercase tracking-wide">📌 Header</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="getRegionBlocks('header').length + ' blocks'"></span>
                            </div>
                            
                            <!-- Blocks in Header -->
                            <div class="space-y-3" x-sortable="handleSort" :data-region-name="'header'">
                                <template x-for="(block, index) in getRegionBlocks('header')" :key="block.id">
                                    <div
                                        draggable="true"
                                        @dragstart="dragStartBlock($event, block, 'header', index)"
                                        :class="{'opacity-50': draggedBlock && draggedBlock.id === block.id}"
                                        class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border-2 border-gray-200 dark:border-gray-600 hover:border-blue-400 transition-all group cursor-move"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3 flex-1">
                                                <div class="text-gray-400 group-hover:text-blue-500">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                                    </svg>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="font-semibold text-gray-900 dark:text-white" x-text="block.title"></div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        Type: <span x-text="block.block_type_name"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div x-show="editMode" class="flex items-center space-x-1">
                                                <button
                                                    @click="toggleBlockVisibility(block)"
                                                    :class="block.visible ? 'text-green-600' : 'text-gray-400'"
                                                    class="p-2 hover:bg-gray-200 dark:hover:bg-gray-600 rounded"
                                                    :title="block.visible ? 'Hide' : 'Show'"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path x-show="block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path x-show="block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        <path x-show="!block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                    </svg>
                                                </button>
                                                <button
                                                    @click="editBlock(block)"
                                                    class="p-2 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-blue-600"
                                                    title="Settings"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                </button>
                                                <button
                                                    @click="deleteBlock(block, 'header', index)"
                                                    class="p-2 hover:bg-red-100 dark:hover:bg-red-900 rounded text-red-600"
                                                    title="Delete"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Empty State -->
                                <div
                                    x-show="getRegionBlocks('header').length === 0"
                                    class="text-center py-8 text-gray-400 dark:text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg"
                                >
                                    <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <p class="text-sm">Drop blocks here</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Region (Full Width) -->
                    <div class="col-span-12">
                        <div
                            x-data="blockRegion('hero')"
                            @drop="drop($event, 'hero')"
                            @dragover.prevent
                            @dragenter="dragEnter()"
                            @dragleave="dragLeave()"
                            :class="{'ring-4 ring-blue-500': isDragOver}"
                            class="min-h-48 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-gray-800 dark:to-gray-700 rounded-lg shadow-lg p-4 transition-all"
                        >
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-gray-700 dark:text-gray-300 text-sm uppercase tracking-wide">🎯 Hero Section</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="getRegionBlocks('hero').length + ' blocks'"></span>
                            </div>
                            
                            <div class="space-y-3" x-sortable="handleSort" :data-region-name="'hero'">
                                <template x-for="(block, index) in getRegionBlocks('hero')" :key="block.id">
                                    <div
                                        draggable="true"
                                        @dragstart="dragStartBlock($event, block, 'hero', index)"
                                        :class="{'opacity-50': draggedBlock && draggedBlock.id === block.id}"
                                        class="bg-white dark:bg-gray-700 rounded-lg p-4 border-2 border-gray-200 dark:border-gray-600 hover:border-purple-400 transition-all group cursor-move"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3 flex-1">
                                                <div class="text-gray-400 group-hover:text-purple-500">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                                    </svg>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="font-semibold text-gray-900 dark:text-white" x-text="block.title"></div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        Type: <span x-text="block.block_type_name"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div x-show="editMode" class="flex items-center space-x-1">
                                                <button @click="toggleBlockVisibility(block)" :class="block.visible ? 'text-green-600' : 'text-gray-400'" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" :title="block.visible ? 'Hide' : 'Show'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path x-show="block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path x-show="block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        <path x-show="!block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                    </svg>
                                                </button>
                                                <button @click="editBlock(block)" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-blue-600" title="Settings">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                                </button>
                                                <button @click="deleteBlock(block, 'hero', index)" class="p-2 hover:bg-red-100 dark:hover:bg-red-900 rounded text-red-600" title="Delete">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div x-show="getRegionBlocks('hero').length === 0" class="text-center py-8 text-gray-400 dark:text-gray-500 border-2 border-dashed border-purple-300 dark:border-gray-600 rounded-lg">
                                    <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <p class="text-sm">Drop hero blocks here</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Area -->
                    <div class="col-span-12 lg:col-span-9 order-2">
                        <div
                            x-data="blockRegion('content')"
                            @drop="drop($event, 'content')"
                            @dragover.prevent
                            @dragenter="dragEnter()"
                            @dragleave="dragLeave()"
                            :class="{'ring-4 ring-blue-500': isDragOver}"
                            class="min-h-96 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 transition-all"
                        >
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-gray-700 dark:text-gray-300 text-sm uppercase tracking-wide">📄 Main Content</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="getRegionBlocks('content').length + ' blocks'"></span>
                            </div>

                            <!-- Blocks in Content -->
                            <div class="space-y-3" x-sortable="handleSort" :data-region-name="'content'">
                                <template x-for="(block, index) in getRegionBlocks('content')" :key="block.id">
                                    <div
                                        draggable="true"
                                        @dragstart="dragStartBlock($event, block, 'content', index)"
                                        :class="{'opacity-50': draggedBlock && draggedBlock.id === block.id}"
                                        class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border-2 border-gray-200 dark:border-gray-600 hover:border-blue-400 transition-all group cursor-move"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3 flex-1">
                                                <div class="text-gray-400 group-hover:text-blue-500">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                                    </svg>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="font-semibold text-gray-900 dark:text-white" x-text="block.title"></div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        Type: <span x-text="block.block_type_name"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div x-show="editMode" class="flex items-center space-x-1">
                                                <button @click="toggleBlockVisibility(block)" :class="block.visible ? 'text-green-600' : 'text-gray-400'" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" :title="block.visible ? 'Hide' : 'Show'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path x-show="block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path x-show="block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        <path x-show="!block.visible" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                    </svg>
                                                </button>
                                                <button @click="editBlock(block)" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-blue-600" title="Settings">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                                </button>
                                                <button @click="deleteBlock(block, 'content', index)" class="p-2 hover:bg-red-100 dark:hover:bg-red-900 rounded text-red-600" title="Delete">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div x-show="getRegionBlocks('content').length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                                    <svg class="w-16 h-16 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                    <p>Drop blocks here or click to add</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Left -->
                    <div class="col-span-12 lg:col-span-3 order-1 lg:order-1 space-y-6">
                        <div
                            x-data="blockRegion('sidebar-left')"
                            @drop="drop($event, 'sidebar-left')"
                            @dragover.prevent
                            @dragenter="dragEnter()"
                            @dragleave="dragLeave()"
                            :class="{'ring-4 ring-blue-500': isDragOver}"
                            class="min-h-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 transition-all"
                        >
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-gray-700 dark:text-gray-300 text-sm uppercase tracking-wide">◀ Sidebar Left</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="getRegionBlocks('sidebar-left').length"></span>
                            </div>

                            <div class="space-y-3" x-sortable="handleSort" :data-region-name="'sidebar-left'">
                                <template x-for="(block, index) in getRegionBlocks('sidebar-left')" :key="block.id">
                                    <div draggable="true" @dragstart="dragStartBlock($event, block, 'sidebar-left', index)" class="bg-gray-50 dark:bg-gray-700 rounded p-3 border border-gray-200 dark:border-gray-600 hover:border-blue-400 group cursor-move">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="font-semibold text-sm text-gray-900 dark:text-white" x-text="block.title"></div>
                                            <div x-show="editMode" class="flex space-x-1">
                                                <button @click="toggleBlockVisibility(block)" class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-xs" :title="block.visible ? 'Hide' : 'Show'"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></button>
                                                <button @click="deleteBlock(block, 'sidebar-left', index)" class="p-1 hover:bg-red-100 rounded text-red-600 text-xs" title="Delete"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-500" x-text="block.block_type_name"></div>
                                    </div>
                                </template>

                                <div x-show="getRegionBlocks('sidebar-left').length === 0" class="text-center py-6 text-gray-400 text-sm border-2 border-dashed border-gray-300 dark:border-gray-600 rounded">Drop here</div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Right -->
                    <div class="col-span-12 lg:col-span-3 order-3 space-y-6">
                        <div
                            x-data="blockRegion('sidebar')"
                            @drop="drop($event, 'sidebar')"
                            @dragover.prevent
                            @dragenter="dragEnter()"
                            @dragleave="dragLeave()"
                            :class="{'ring-4 ring-blue-500': isDragOver}"
                            class="min-h-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 transition-all"
                        >
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-gray-700 dark:text-gray-300 text-sm uppercase tracking-wide">▶ Sidebar Right</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="getRegionBlocks('sidebar').length"></span>
                            </div>

                            <div class="space-y-3" x-sortable="handleSort" :data-region-name="'sidebar'">
                                <template x-for="(block, index) in getRegionBlocks('sidebar')" :key="block.id">
                                    <div draggable="true" @dragstart="dragStartBlock($event, block, 'sidebar', index)" class="bg-gray-50 dark:bg-gray-700 rounded p-3 border border-gray-200 dark:border-gray-600 hover:border-blue-400 group cursor-move">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="font-semibold text-sm text-gray-900 dark:text-white" x-text="block.title"></div>
                                            <div x-show="editMode" class="flex space-x-1">
                                                <button @click="toggleBlockVisibility(block)" class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-xs" :title="block.visible ? 'Hide' : 'Show'"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></button>
                                                <button @click="deleteBlock(block, 'sidebar', index)" class="p-1 hover:bg-red-100 rounded text-red-600 text-xs" title="Delete"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-500" x-text="block.block_type_name"></div>
                                    </div>
                                </template>

                                <div x-show="getRegionBlocks('sidebar').length === 0" class="text-center py-6 text-gray-400 text-sm border-2 border-dashed border-gray-300 dark:border-gray-600 rounded">Drop here</div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Region (Full Width) -->
                    <div class="col-span-12">
                        <div
                            x-data="blockRegion('footer')"
                            @drop="drop($event, 'footer')"
                            @dragover.prevent
                            @dragenter="dragEnter()"
                            @dragleave="dragLeave()"
                            :class="{'ring-4 ring-blue-500': isDragOver}"
                            class="min-h-24 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 transition-all"
                        >
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-gray-700 dark:text-gray-300 text-sm uppercase tracking-wide">🔽 Footer</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="getRegionBlocks('footer').length + ' blocks'"></span>
                            </div>

                            <div class="space-y-3" x-sortable="handleSort" :data-region-name="'footer'">
                                <template x-for="(block, index) in getRegionBlocks('footer')" :key="block.id">
                                    <div draggable="true" @dragstart="dragStartBlock($event, block, 'footer', index)" class="bg-gray-50 dark:bg-gray-700 rounded p-3 border border-gray-200 hover:border-blue-400 group cursor-move">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-2 flex-1">
                                                <div class="font-semibold text-sm text-gray-900 dark:text-white" x-text="block.title"></div>
                                            </div>
                                            <div x-show="editMode" class="flex items-center space-x-1">
                                                <button @click="toggleBlockVisibility(block)" class="p-1 hover:bg-gray-200 rounded" :title="block.visible ? 'Hide' : 'Show'"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></button>
                                                <button @click="deleteBlock(block, 'footer', index)" class="p-1 hover:bg-red-100 rounded text-red-600" title="Delete"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div x-show="getRegionBlocks('footer').length === 0" class="text-center py-4 text-gray-400 text-sm border-2 border-dashed border-gray-300 rounded">Drop footer blocks here</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Block Modal -->
    <div x-show="editingBlock" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="editingBlock = null">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] flex flex-col">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Block Settings: <span x-text="editingBlock?.title" class="text-blue-600"></span></h2>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-text="editingBlock?.block_type?.description"></p>
            </div>
            <div class="p-6 overflow-y-auto">
                <form x-if="editingBlock" @submit.prevent="saveBlockSettings()">
                    <div class="space-y-6">
                        <!-- General Settings -->
                        <div class="p-4 border rounded-lg">
                            <h3 class="font-semibold mb-4 text-gray-800 dark:text-gray-200">General</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Block Title</label>
                                    <input type="text" x-model="editingBlock.title" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                </div>
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" x-model="editingBlock.visible" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Visible on site</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Configuration Fields -->
                        <div x-show="editingBlock.block_type?.config_schema?.length > 0" class="p-4 border rounded-lg">
                             <h3 class="font-semibold mb-4 text-gray-800 dark:text-gray-200">Configuration</h3>
                            <div class="space-y-4">
                                <template x-for="field in editingBlock.block_type.config_schema" :key="field.name">
                                    <div>
                                        <label :for="'field-' + field.name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" x-text="field.label"></label>
                                        
                                        <!-- Text Input -->
                                        <input x-if="field.type === 'text'" :type="field.type" :id="'field-' + field.name" x-model="editingBlock.config[field.name]" :placeholder="field.placeholder" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                        
                                        <!-- Textarea -->
                                        <textarea x-if="field.type === 'textarea'" :id="'field-' + field.name" x-model="editingBlock.config[field.name]" rows="4" :placeholder="field.placeholder" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm"></textarea>

                                        <!-- Boolean (Checkbox) -->
                                        <label x-if="field.type === 'boolean'" class="flex items-center">
                                            <input :id="'field-' + field.name" type="checkbox" x-model="editingBlock.config[field.name]" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400" x-text="field.description || ''"></span>
                                        </label>

                                        <!-- Select (Dropdown) -->
                                        <select x-if="field.type === 'select'" :id="'field-' + field.name" x-model="editingBlock.config[field.name]" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                            <template x-for="option in field.options" :key="option.value">
                                                <option :value="option.value" x-text="option.label"></option>
                                            </template>
                                        </select>

                                        <!-- Color Picker -->
                                        <input x-if="field.type === 'color'" type="color" :id="'field-' + field.name" x-model="editingBlock.config[field.name]" class="p-1 h-10 w-14 block bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 cursor-pointer rounded-lg">
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                        <button type="button" @click="editingBlock = null" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function blockRegion(regionName) {
    return {
        isDragOver: false,
        dragEnter() {
            this.isDragOver = true;
        },
        dragLeave() {
            this.isDragOver = false;
        }
    }
}

function visualBlockEditor() {
    return {
        editMode: false,
        contextType: 'global',
        contextId: null,
        hasChanges: false,
        blockSearch: '',
        blockTypes: [],
        blocks: {},
        draggedBlock: null,
        draggedBlockType: null,
        sourceRegion: null,
        sourceIndex: null,
        editingBlock: null,
        apiQueue: [],

        async init() {
            await this.loadBlockTypes();
            await this.loadBlocks();
        },

        async loadBlockTypes() {
            try {
                const response = await fetch('/admin/blocks/types');
                if (!response.ok) throw new Error('Failed to load block types');
                const data = await response.json();
                this.blockTypes = data.block_types || [];
            } catch (error) {
                console.error('Error loading block types:', error);
                alert('Failed to load block types. Please refresh the page.');
                this.blockTypes = [];
            }
        },

        async loadBlocks() {
            try {
                // Load blocks for all regions
                const regions = ['header', 'hero', 'content', 'sidebar-left', 'sidebar', 'footer'];
                this.blocks = {};
                
                // Fetch all regions in parallel to improve loading time
                const fetchPromises = regions.map(async (region) => {
                    const response = await fetch(`/admin/blocks/regions/${region}/blocks?context_type=${this.contextType}`);
                    if (!response.ok) {
                        // Log a warning but don't block other regions from loading
                        console.warn(`Failed to load ${region} blocks:`, response.statusText);
                        return { region, blocks: [] };
                    }
                    const data = await response.json();
                    return { region, blocks: data.blocks || [] };
                });

                const results = await Promise.all(fetchPromises);
                results.forEach(({ region, blocks }) => {
                    this.blocks[region] = blocks;
                });
            } catch (error) {
                console.error('Error loading blocks:', error);
                alert('Failed to load blocks. Please refresh the page.');
            }
        },

        get filteredCategories() {
            if (!this.blockSearch) {
                return [...new Set(this.blockTypes.map(b => b.category))];
            }
            const filtered = this.blockTypes.filter(b => 
                b.title.toLowerCase().includes(this.blockSearch.toLowerCase()) ||
                b.description.toLowerCase().includes(this.blockSearch.toLowerCase())
            );
            return [...new Set(filtered.map(b => b.category))];
        },

        getBlocksByCategory(category) {
            const blocks = this.blockTypes.filter(b => b.category === category);
            if (!this.blockSearch) return blocks;
            return blocks.filter(b => 
                b.title.toLowerCase().includes(this.blockSearch.toLowerCase()) ||
                b.description.toLowerCase().includes(this.blockSearch.toLowerCase())
            );
        },

        getRegionBlocks(regionName) {
            return this.blocks[regionName] || [];
        },

        toggleEditMode() {
            this.editMode = !this.editMode;
        },

        dragStart(event, blockType) {
            this.draggedBlockType = blockType;
            event.dataTransfer.effectAllowed = 'move';
        },

        dragStartBlock(event, block, region, index) {
            this.draggedBlock = block;
            this.sourceRegion = region;
            this.sourceIndex = index;
            event.dataTransfer.effectAllowed = 'move';
        },

        async drop(event, targetRegion) {
            event.preventDefault();
            
            if (this.draggedBlockType) {
                // Case 1: Adding a new block from the sidebar
                await this.createBlock(this.draggedBlockType, targetRegion);
                this.draggedBlockType = null;
            } else if (this.draggedBlock && this.sourceRegion) {
                // Case 2: Moving an existing block
                const targetElement = event.target.closest('[x-sortable]');
                const targetIndex = Array.from(targetElement.children).indexOf(event.target.closest('[draggable="true"]'));

                // Remove from old region
                const blockToMove = this.blocks[this.sourceRegion].splice(this.sourceIndex, 1)[0];

                // Add to new region at the correct position
                if (targetIndex >= 0) {
                    this.blocks[targetRegion].splice(targetIndex, 0, blockToMove);
                } else {
                    this.blocks[targetRegion].push(blockToMove);
                }

                await this.reorderRegion(targetRegion);
                if (this.sourceRegion !== targetRegion) {
                    await this.reorderRegion(this.sourceRegion);
                }

                this.draggedBlock = null;
                this.sourceRegion = null;
            }
        },

        async createBlock(blockType, region) {
            try {
                const response = await fetch('/admin/blocks', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        block_type_name: blockType.name,
                        region: region,
                        context_type: this.contextType,
                        context_id: this.contextId,
                        visible: true
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to create block');
                }

                await this.loadBlocks();
                this.hasChanges = true;
            } catch (error) {
                console.error('Error creating block:', error);
                alert('Failed to create block: ' + error.message);
            }
        },

        // Debounce timer for reorderRegion API calls
        reorderDebounceTimer: null,

        async reorderRegion(regionName) {
            clearTimeout(this.reorderDebounceTimer); // Clear any previous debounce timer
            this.reorderDebounceTimer = setTimeout(async () => {
                const blockIds = this.blocks[regionName].map(b => b.id);
                console.log(`[Debounced] Reordering region ${regionName} with IDs:`, blockIds);
                await this.queueApiCall(`/admin/blocks/reorder`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        block_ids: blockIds,
                        region: regionName, // Send region context
                        context_type: this.contextType,
                        context_id: this.contextId,
                    })
                });
                // No need to reloadBlocks() here, as the UI is already updated by SortableJS and Alpine.js
            }, 300); // Wait for 300ms after the last sort event before making the API call
            this.hasChanges = true;
        },

        handleSort(event) {
            this.reorderRegion(event.target.dataset.regionName);
            this.hasChanges = true;
        },

        async toggleBlockVisibility(block) {
            await fetch(`/admin/blocks/${block.id}/toggle-visibility`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ context_type: this.contextType })
            });
            await this.loadBlocks(); // Reload to get fresh state
            this.hasChanges = true;
        },

        editBlock(block) {
            // Find the full block type definition, including the schema
            const blockType = this.blockTypes.find(bt => bt.name === block.block_type_name);

            // Deep copy the block to avoid modifying the original state directly
            let blockCopy = JSON.parse(JSON.stringify(block));

            // Ensure config is an object
            blockCopy.config = blockCopy.config || {};

            // Populate default values from schema if they don't exist in the block's config
            if (blockType && blockType.config_schema) {
                blockType.config_schema.forEach(field => {
                    if (blockCopy.config[field.name] === undefined && field.default !== undefined) {
                        blockCopy.config[field.name] = field.default;
                    }
                });
            }

            // Attach the full block type definition to the editing object
            blockCopy.block_type = blockType;

            this.editingBlock = blockCopy;
        },

        async saveBlockSettings() {
            if (!this.editingBlock) return;

            await fetch(`/admin/blocks/${this.editingBlock.id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(this.editingBlock) // Send the whole object
            });

            await this.loadBlocks();
            this.editingBlock = null;
            this.hasChanges = true;
        },

        async deleteBlock(block, region, index) {
            if (!confirm(`Delete "${block.title}"?`)) return;

            try {
                const response = await fetch(`/admin/blocks/${block.id}?context_type=${this.contextType}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                if (!response.ok) throw new Error('Failed to delete');
                
                this.blocks[region].splice(index, 1);
                this.hasChanges = true;
            } catch (error) {
                alert('Error deleting block.');
                await this.loadBlocks();
            }
        },

        async saveLayout() {
            await this.processApiQueue();
            this.hasChanges = false;
            alert('Layout saved successfully!');
        },

        async queueApiCall(url, options) {
            // For now, just call directly. A more robust solution would queue and debounce.
            await fetch(url, options);
        },

        async processApiQueue() {
            // All changes are already saved via individual API calls
            this.hasChanges = false;
            alert('Layout saved successfully!');
        },

        selectBlockType(blockType) {
            // Optional: show a region selector
            console.log('Selected block type:', blockType);
        }
    }
}
</script>
@endpush

@section('styles')
<style>
    [x-cloak] { display: none !important; }
    
    .cursor-move {
        cursor: move;
        cursor: grab;
    }
    
    .cursor-move:active {
        cursor: grabbing;
    }
</style>
@endsection
@endsection
