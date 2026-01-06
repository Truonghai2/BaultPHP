<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Page Block Editor - {{ $page->name }} - BaultFrame Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f7;
            color: #1d1d1f;
        }

        .container {
            display: grid;
            grid-template-columns: 300px 1fr;
            height: 100vh;
            gap: 0;
        }

        .sidebar {
            background: white;
            border-right: 1px solid #d2d2d7;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid #e8e8ed;
        }

        .sidebar-header h2 {
            font-size: 18px;
            margin-bottom: 8px;
        }

        .sidebar-header p {
            font-size: 12px;
            color: #86868b;
        }

        .block-types-list {
            padding: 16px;
        }

        .block-type-item {
            background: #f5f5f7;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 8px;
            cursor: grab;
            transition: all 0.2s;
        }

        .block-type-item:hover {
            background: #e8e8ed;
        }

        .block-type-item.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }

        .block-type-item .icon {
            font-size: 20px;
            margin-right: 8px;
        }

        .block-type-item .name {
            font-size: 14px;
            font-weight: 500;
        }

        .block-type-item .desc {
            font-size: 12px;
            color: #86868b;
            margin-top: 4px;
        }

        .main-content {
            overflow-y: auto;
            padding: 24px;
        }

        .header {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .header .actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #0071e3;
            color: white;
        }

        .btn-secondary {
            background: #e8e8ed;
            color: #1d1d1f;
        }

        .btn-danger {
            background: #ff3b30;
            color: white;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .regions-grid {
            display: grid;
            gap: 24px;
        }

        .region-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .region-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e8e8ed;
        }

        .region-header h3 {
            font-size: 18px;
            color: #0071e3;
        }

        .region-header .badge {
            background: #f5f5f7;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .blocks-container {
            min-height: 100px;
            position: relative;
        }

        .blocks-container.empty::before {
            content: 'Drag blocks here';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #86868b;
            font-size: 14px;
        }

        .blocks-container.drag-over {
            background: #f5f5f7;
            border: 2px dashed #0071e3;
            border-radius: 8px;
        }

        .block-item {
            background: #f5f5f7;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 8px;
            border: 2px solid transparent;
            cursor: move;
            transition: all 0.2s;
        }

        .block-item:hover {
            border-color: #0071e3;
        }

        .block-item.dragging {
            opacity: 0.5;
        }

        .block-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .block-item-title {
            font-size: 15px;
            font-weight: 500;
        }

        .block-item-actions {
            display: flex;
            gap: 4px;
        }

        .block-item-meta {
            font-size: 12px;
            color: #86868b;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #86868b;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            z-index: 2000;
            animation: slideIn 0.3s ease;
        }

        .notification.success {
            border-left: 4px solid #34c759;
        }

        .notification.error {
            border-left: 4px solid #ff3b30;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d2d2d7;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .preview-container {
            background: #f5f5f7;
            padding: 20px;
            border-radius: 8px;
            margin-top: 16px;
            max-height: 400px;
            overflow-y: auto;
        }

        .preview-label {
            font-size: 12px;
            font-weight: 500;
            color: #86868b;
            margin-bottom: 8px;
        }

        .block-config-editor {
            margin-top: 16px;
        }

        .config-field {
            margin-bottom: 12px;
        }

        .config-field label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .preview-button {
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>📦 Block Types</h2>
                <p>Drag to add blocks</p>
            </div>
            <div class="block-types-list" id="block-types-list">
                <div class="loading">Loading...</div>
            </div>
        </div>

        <div class="main-content">
            <div class="header">
                <div>
                    <h1>✏️ {{ $page->name }}</h1>
                </div>
                <div class="actions">
                    <button class="btn btn-secondary" onclick="blockEditor.loadPage()">
                        🔄 Refresh
                    </button>
                    <button class="btn btn-secondary" onclick="window.location.href='{{ url('/admin/pages') }}'">
                        ← Back to Pages
                    </button>
                </div>
            </div>

            <div class="regions-grid" id="regions-container">
                <div class="loading">Loading regions...</div>
            </div>
        </div>
    </div>

    <!-- Add Block Modal -->
    <div id="add-block-modal" class="modal">
        <div class="modal-content">
            <h2>Add Block</h2>
            <form id="add-block-form" onsubmit="blockEditor.addBlock(event)">
                <input type="hidden" id="block-type-id">
                <input type="hidden" id="block-region">
                
                <div class="form-group">
                    <label for="block-title">Block Title</label>
                    <input type="text" id="block-title" required>
                </div>

                <div class="block-config-editor" id="block-config-editor">
                    <!-- Dynamic config fields -->
                </div>

                <button type="button" class="btn btn-secondary preview-button" onclick="blockEditor.previewBlock()">
                    👁️ Preview Block
                </button>

                <div class="preview-container" id="preview-container" style="display: none;">
                    <div class="preview-label">Preview:</div>
                    <div id="preview-content"></div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="blockEditor.closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Add Block
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const pageId = {{ $page->id }};

        const blockEditor = {
            pageId: pageId,
            page: null,
            blockTypes: [],
            regions: {},
            blocks: {},

            init() {
                this.loadBlockTypes();
                this.loadPage();
            },

            async loadBlockTypes() {
                try {
                    const response = await fetch('/admin/blocks/types', {
                        headers: {'X-CSRF-TOKEN': csrfToken}
                    });
                    const data = await response.json();
                    this.blockTypes = data.block_types || [];
                    this.renderBlockTypes();
                } catch (error) {
                    console.error('Failed to load block types:', error);
                }
            },

            renderBlockTypes() {
                const container = document.getElementById('block-types-list');
                container.innerHTML = this.blockTypes.map(type => `
                    <div class="block-type-item" draggable="true" data-type-id="${type.id}" data-type-name="${type.name}">
                        <div class="icon">${type.icon || '📦'}</div>
                        <div class="name">${type.title}</div>
                        <div class="desc">${type.description || ''}</div>
                    </div>
                `).join('');

                document.querySelectorAll('.block-type-item').forEach(item => {
                    item.addEventListener('dragstart', this.handleDragStart.bind(this));
                    item.addEventListener('dragend', this.handleDragEnd.bind(this));
                });
            },

            async loadPage() {
                try {
                    const response = await fetch(`/admin/pages/${this.pageId}`, {
                        headers: {'X-CSRF-TOKEN': csrfToken}
                    });
                    const data = await response.json();
                    
                    this.page = data.page;
                    this.regions = data.regions;
                    this.blocks = data.blocks;
                    
                    this.renderRegions();
                } catch (error) {
                    console.error('Failed to load page:', error);
                    this.showNotification('Failed to load page', 'error');
                }
            },

            renderRegions() {
                const container = document.getElementById('regions-container');
                
                container.innerHTML = Object.entries(this.regions).map(([key, regionName]) => {
                    const regionBlocks = this.blocks[key] || [];
                    
                    return `
                        <div class="region-section">
                            <div class="region-header">
                                <h3>${key.charAt(0).toUpperCase() + key.slice(1)}</h3>
                                <span class="badge">${regionBlocks.length} blocks</span>
                            </div>
                            <div class="blocks-container ${regionBlocks.length === 0 ? 'empty' : ''}" 
                                 data-region="${key}" 
                                 ondragover="blockEditor.handleDragOver(event)"
                                 ondrop="blockEditor.handleDrop(event)">
                                ${regionBlocks.map(block => `
                                    <div class="block-item" draggable="true" data-block-id="${block.id}">
                                        <div class="block-item-header">
                                            <div class="block-item-title">
                                                ${block.block_type.icon || '📦'} ${block.title}
                                            </div>
                                            <div class="block-item-actions">
                                                <button class="btn btn-danger btn-small" onclick="blockEditor.removeBlock(${block.id})">
                                                    🗑️
                                                </button>
                                            </div>
                                        </div>
                                        <div class="block-item-meta">
                                            Type: ${block.block_type.title} • Weight: ${block.weight}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }).join('');

                document.querySelectorAll('.block-item').forEach(item => {
                    item.addEventListener('dragstart', this.handleBlockDragStart.bind(this));
                    item.addEventListener('dragend', this.handleDragEnd.bind(this));
                });
            },

            handleDragStart(e) {
                e.target.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('blockTypeId', e.target.dataset.typeId);
                e.dataTransfer.setData('blockTypeName', e.target.dataset.typeName);
            },

            handleBlockDragStart(e) {
                e.target.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('blockId', e.target.dataset.blockId);
            },

            handleDragEnd(e) {
                e.target.classList.remove('dragging');
            },

            handleDragOver(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
                const container = e.currentTarget;
                container.classList.add('drag-over');
                
                setTimeout(() => container.classList.remove('drag-over'), 100);
            },

            handleDrop(e) {
                e.preventDefault();
                const container = e.currentTarget;
                container.classList.remove('drag-over');
                
                const blockTypeId = e.dataTransfer.getData('blockTypeId');
                const blockTypeName = e.dataTransfer.getData('blockTypeName');
                const region = container.dataset.region;

                if (blockTypeId) {
                    this.showAddBlockModal(blockTypeId, blockTypeName, region);
                }
            },

            async showAddBlockModal(typeId, typeName, region) {
                const blockType = this.blockTypes.find(t => t.id == typeId);
                
                document.getElementById('block-type-id').value = typeId;
                document.getElementById('block-region').value = region;
                document.getElementById('block-title').value = blockType?.title || '';
                
                await this.loadBlockSchema(typeName);
                
                document.getElementById('add-block-modal').classList.add('active');
            },

            async loadBlockSchema(blockTypeName) {
                try {
                    const response = await fetch(`/admin/blocks/${blockTypeName}/schema`, {
                        headers: {'X-CSRF-TOKEN': csrfToken}
                    });
                    const schema = await response.json();
                    
                    this.currentBlockSchema = schema;
                    this.renderConfigEditor(schema.default_config || {});
                } catch (error) {
                    console.error('Failed to load block schema:', error);
                }
            },

            renderConfigEditor(defaultConfig) {
                const editor = document.getElementById('block-config-editor');
                
                if (!defaultConfig || Object.keys(defaultConfig).length === 0) {
                    editor.innerHTML = '<p style="color: #86868b; font-size: 13px;">No configuration options for this block type.</p>';
                    return;
                }

                editor.innerHTML = Object.entries(defaultConfig).map(([key, value]) => `
                    <div class="config-field">
                        <label for="config-${key}">${this.formatLabel(key)}</label>
                        <input type="text" 
                               id="config-${key}" 
                               name="${key}" 
                               value="${typeof value === 'object' ? JSON.stringify(value) : value}"
                               placeholder="Enter ${key}">
                    </div>
                `).join('');
            },

            formatLabel(key) {
                return key
                    .split('_')
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                    .join(' ');
            },

            getBlockConfig() {
                const config = {};
                const fields = document.querySelectorAll('#block-config-editor input, #block-config-editor textarea');
                
                fields.forEach(field => {
                    const key = field.name;
                    let value = field.value;
                    
                    try {
                        value = JSON.parse(value);
                    } catch (e) {
                        // Keep as string
                    }
                    
                    config[key] = value;
                });
                
                return config;
            },

            async previewBlock() {
                const typeId = document.getElementById('block-type-id').value;
                const blockType = this.blockTypes.find(t => t.id == typeId);
                const config = this.getBlockConfig();
                
                try {
                    const response = await fetch('/admin/blocks/preview', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            block_type_name: blockType.name,
                            config: config
                        })
                    });

                    const data = await response.json();

                    if (response.ok) {
                        document.getElementById('preview-content').innerHTML = data.html;
                        document.getElementById('preview-container').style.display = 'block';
                    } else {
                        this.showNotification(data.error || 'Failed to preview', 'error');
                    }
                } catch (error) {
                    console.error('Failed to preview block:', error);
                    this.showNotification('Failed to preview block', 'error');
                }
            },

            async addBlock(e) {
                e.preventDefault();
                
                const typeId = document.getElementById('block-type-id').value;
                const region = document.getElementById('block-region').value;
                const title = document.getElementById('block-title').value;
                const config = this.getBlockConfig();

                try {
                    const response = await fetch(`/admin/pages/${this.pageId}/blocks`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            block_type_id: parseInt(typeId),
                            region: region,
                            title: title,
                            config: config
                        })
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.showNotification(data.message, 'success');
                        this.closeModal();
                        this.loadPage();
                    } else {
                        this.showNotification(data.error || 'Failed to add block', 'error');
                    }
                } catch (error) {
                    console.error('Failed to add block:', error);
                    this.showNotification('Failed to add block', 'error');
                }
            },

            async removeBlock(blockId) {
                if (!confirm('Remove this block?')) return;

                try {
                    const response = await fetch(`/admin/pages/${this.pageId}/blocks/${blockId}`, {
                        method: 'DELETE',
                        headers: {'X-CSRF-TOKEN': csrfToken}
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.showNotification(data.message, 'success');
                        this.loadPage();
                    } else {
                        this.showNotification(data.error || 'Failed to remove block', 'error');
                    }
                } catch (error) {
                    console.error('Failed to remove block:', error);
                    this.showNotification('Failed to remove block', 'error');
                }
            },

            closeModal() {
                document.getElementById('add-block-modal').classList.remove('active');
                document.getElementById('preview-container').style.display = 'none';
                document.getElementById('preview-content').innerHTML = '';
            },

            showNotification(message, type = 'success') {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.textContent = message;
                document.body.appendChild(notification);

                setTimeout(() => notification.remove(), 3000);
            }
        };

        document.getElementById('add-block-modal')?.addEventListener('click', (e) => {
            if (e.target.id === 'add-block-modal') {
                blockEditor.closeModal();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            blockEditor.init();
        });
    </script>
</body>
</html>

