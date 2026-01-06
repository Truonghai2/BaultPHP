<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache Management - BaultFrame CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8" x-data="cacheManager">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Block Cache Management</h1>
            <p class="text-gray-600">Manage caching for the block rendering system</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-600 mb-1">Cached Instances</div>
                <div class="text-3xl font-bold text-blue-600">
                    <?= $stats['registry']['cached_instances'] ?>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-600 mb-1">Valid Classes</div>
                <div class="text-3xl font-bold text-green-600">
                    <?= $stats['registry']['valid_classes'] ?>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-600 mb-1">Invalid Classes</div>
                <div class="text-3xl font-bold text-red-600">
                    <?= $stats['registry']['invalid_classes'] ?>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-600 mb-1">Cache Driver</div>
                <div class="text-xl font-semibold text-gray-800">
                    <?= basename(str_replace('\\', '/', $stats['cache_driver'])) ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Clear Cache -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Clear Cache</h2>
                
                <div class="space-y-4">
                    <button 
                        @click="clearAll"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200"
                    >
                        🗑️ Clear All Block Caches
                    </button>

                    <div class="border-t pt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Clear Specific Region
                        </label>
                        <div class="flex gap-2">
                            <select 
                                x-model="selectedRegion"
                                class="flex-1 border border-gray-300 rounded-lg px-4 py-2"
                            >
                                <option value="">Select region...</option>
                                <option value="header">Header</option>
                                <option value="hero">Hero</option>
                                <option value="content">Content</option>
                                <option value="sidebar-left">Sidebar Left</option>
                                <option value="sidebar">Sidebar Right</option>
                                <option value="footer">Footer</option>
                            </select>
                            <button 
                                @click="clearRegion"
                                :disabled="!selectedRegion"
                                class="bg-orange-600 hover:bg-orange-700 disabled:bg-gray-300 text-white font-semibold py-2 px-6 rounded-lg transition duration-200"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warm Up Cache -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Warm Up Cache</h2>
                
                <div class="space-y-4">
                    <button 
                        @click="warmupAll"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200"
                    >
                        🔥 Warm Up Popular Pages
                    </button>

                    <div class="border-t pt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Warm Up Specific Page
                        </label>
                        <div class="flex gap-2">
                            <input 
                                type="number" 
                                x-model="pageId"
                                placeholder="Page ID"
                                class="flex-1 border border-gray-300 rounded-lg px-4 py-2"
                            />
                            <button 
                                @click="warmupPage"
                                :disabled="!pageId"
                                class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white font-semibold py-2 px-6 rounded-lg transition duration-200"
                            >
                                Warm Up
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div 
            x-show="message" 
            x-transition
            class="mt-6"
        >
            <div 
                :class="messageType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
                class="border rounded-lg p-4"
            >
                <p x-text="message"></p>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div 
            x-show="loading" 
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-lg p-8 text-center">
                <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-700 font-semibold">Processing...</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('cacheManager', () => ({
                loading: false,
                message: '',
                messageType: 'success',
                selectedRegion: '',
                pageId: '',

                async clearAll() {
                    if (!confirm('Are you sure you want to clear ALL block caches?')) {
                        return;
                    }

                    this.loading = true;
                    try {
                        const response = await fetch('/admin/cache/clear-all', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                        });

                        const data = await response.json();
                        this.showMessage(data.message, data.success ? 'success' : 'error');

                        if (data.success) {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } catch (error) {
                        this.showMessage('Failed to clear cache: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async clearRegion() {
                    if (!this.selectedRegion) return;

                    this.loading = true;
                    try {
                        const response = await fetch('/admin/cache/clear-region', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                region: this.selectedRegion,
                            }),
                        });

                        const data = await response.json();
                        this.showMessage(data.message, data.success ? 'success' : 'error');

                        if (data.success) {
                            this.selectedRegion = '';
                        }
                    } catch (error) {
                        this.showMessage('Failed to clear region cache: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async warmupAll() {
                    this.loading = true;
                    try {
                        const response = await fetch('/admin/cache/warmup-all', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                        });

                        const data = await response.json();
                        this.showMessage(data.message, data.success ? 'success' : 'error');
                    } catch (error) {
                        this.showMessage('Failed to warm up cache: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async warmupPage() {
                    if (!this.pageId) return;

                    this.loading = true;
                    try {
                        const response = await fetch(`/admin/cache/warmup-page/${this.pageId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                        });

                        const data = await response.json();
                        this.showMessage(data.message, data.success ? 'success' : 'error');

                        if (data.success) {
                            this.pageId = '';
                        }
                    } catch (error) {
                        this.showMessage('Failed to warm up page: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                showMessage(msg, type = 'success') {
                    this.message = msg;
                    this.messageType = type;
                    setTimeout(() => {
                        this.message = '';
                    }, 5000);
                },
            }));
        });
    </script>
</body>
</html>

