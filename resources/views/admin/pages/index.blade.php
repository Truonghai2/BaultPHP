<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Page Management - BaultFrame Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f7;
            color: #1d1d1f;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .header h1 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .header p {
            color: #86868b;
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
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

        .btn-primary:hover {
            background: #0077ed;
        }

        .btn-secondary {
            background: #e8e8ed;
            color: #1d1d1f;
        }

        .btn-secondary:hover {
            background: #d2d2d7;
        }

        .btn-danger {
            background: #ff3b30;
            color: white;
        }

        .btn-success {
            background: #34c759;
            color: white;
        }

        .pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .page-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.2s;
            cursor: pointer;
        }

        .page-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .page-card h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .page-card .slug {
            color: #0071e3;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .page-card .meta {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: #86868b;
            margin-bottom: 16px;
        }

        .page-card .actions {
            display: flex;
            gap: 8px;
            margin: 0;
        }

        .page-card .btn {
            flex: 1;
            padding: 8px 16px;
            font-size: 13px;
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

        .modal-content h2 {
            font-size: 24px;
            margin-bottom: 24px;
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

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0071e3;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #86868b;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }

        .empty-state h2 {
            font-size: 24px;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #86868b;
            margin-bottom: 24px;
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

        .block-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #f5f5f7;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📄 Page Management</h1>
            <p>Manage pages and their content blocks</p>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="pageManager.showCreateModal()">
                ➕ Create New Page
            </button>
            <button class="btn btn-secondary" onclick="pageManager.loadPages()">
                🔄 Refresh
            </button>
        </div>

        <div id="pages-container">
            <div class="loading">Loading pages...</div>
        </div>
    </div>

    <!-- Create/Edit Page Modal -->
    <div id="page-modal" class="modal">
        <div class="modal-content">
            <h2 id="modal-title">Create New Page</h2>
            <form id="page-form" onsubmit="pageManager.savePage(event)">
                <input type="hidden" id="page-id">
                
                <div class="form-group">
                    <label for="page-name">Page Name *</label>
                    <input type="text" id="page-name" required placeholder="e.g., About Us">
                </div>

                <div class="form-group">
                    <label for="page-slug">Slug *</label>
                    <input type="text" id="page-slug" required placeholder="e.g., about-us" pattern="[a-z0-9-]+">
                    <small style="color: #86868b; font-size: 12px; display: block; margin-top: 4px;">
                        Only lowercase letters, numbers, and hyphens
                    </small>
                </div>

                <div class="form-group" id="template-selection" style="display: none;">
                    <label for="page-template">Template (Optional)</label>
                    <select id="page-template">
                        <option value="">Blank Page</option>
                    </select>
                    <small style="color: #86868b; font-size: 12px; display: block; margin-top: 4px;">
                        Select a template to pre-populate blocks
                    </small>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="pageManager.closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Save Page
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        const pageManager = {
            apiBase: '/admin/pages',
            pages: [],
            templates: [],

            async loadPages() {
                try {
                    const response = await fetch(`${this.apiBase}/api`, {
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    const data = await response.json();
                    
                    this.pages = data.pages || [];
                    this.renderPages();
                } catch (error) {
                    console.error('Failed to load pages:', error);
                    this.showNotification('Failed to load pages', 'error');
                }
            },

            async loadTemplates() {
                try {
                    const response = await fetch(`${this.apiBase}/templates`, {
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    const data = await response.json();
                    
                    this.templates = data.templates || [];
                    this.renderTemplateOptions();
                } catch (error) {
                    console.error('Failed to load templates:', error);
                }
            },

            renderTemplateOptions() {
                const select = document.getElementById('page-template');
                select.innerHTML = '<option value="">Blank Page</option>' +
                    this.templates.map(template => `
                        <option value="${template.key}">
                            ${template.name} (${template.block_count} blocks)
                        </option>
                    `).join('');
            },

            renderPages() {
                const container = document.getElementById('pages-container');
                
                if (this.pages.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <h2>No pages yet</h2>
                            <p>Create your first page to get started</p>
                            <button class="btn btn-primary" onclick="pageManager.showCreateModal()">
                                ➕ Create Page
                            </button>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="pages-grid">
                        ${this.pages.map(page => `
                            <div class="page-card" onclick="pageManager.viewPage(${page.id})">
                                <h3>${page.name}</h3>
                                <div class="slug">/${page.slug}</div>
                                <div class="meta">
                                    <span class="block-badge">${page.block_count} blocks</span>
                                    <span>Created ${this.formatDate(page.created_at)}</span>
                                </div>
                                <div class="actions" onclick="event.stopPropagation()">
                                    <button class="btn btn-secondary" onclick="pageManager.editPage(${page.id})">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-primary" onclick="window.location.href='/admin/pages/${page.id}/editor'">
                                        🎨 Blocks
                                    </button>
                                    <button class="btn btn-danger" onclick="pageManager.deletePage(${page.id})">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            },

            async showCreateModal() {
                document.getElementById('modal-title').textContent = 'Create New Page';
                document.getElementById('page-form').reset();
                document.getElementById('page-id').value = '';
                
                await this.loadTemplates();
                document.getElementById('template-selection').style.display = 'block';
                
                document.getElementById('page-modal').classList.add('active');
            },

            async editPage(id) {
                try {
                    const response = await fetch(`${this.apiBase}/${id}`, {
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    const data = await response.json();
                    
                    document.getElementById('modal-title').textContent = 'Edit Page';
                    document.getElementById('page-id').value = data.page.id;
                    document.getElementById('page-name').value = data.page.name;
                    document.getElementById('page-slug').value = data.page.slug;
                    
                    document.getElementById('template-selection').style.display = 'none';
                    
                    document.getElementById('page-modal').classList.add('active');
                } catch (error) {
                    console.error('Failed to load page:', error);
                    this.showNotification('Failed to load page', 'error');
                }
            },

            async savePage(event) {
                event.preventDefault();
                
                const id = document.getElementById('page-id').value;
                const name = document.getElementById('page-name').value;
                const slug = document.getElementById('page-slug').value;
                const template = document.getElementById('page-template').value;

                const url = id ? `${this.apiBase}/${id}` : this.apiBase;
                const method = id ? 'PUT' : 'POST';

                const payload = { name, slug };
                if (!id && template) {
                    payload.template = template;
                }

                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.showNotification(data.message + (template ? ' with template applied!' : ''), 'success');
                        this.closeModal();
                        this.loadPages();
                    } else {
                        this.showNotification(data.error || 'Failed to save page', 'error');
                    }
                } catch (error) {
                    console.error('Failed to save page:', error);
                    this.showNotification('Failed to save page', 'error');
                }
            },

            async deletePage(id) {
                if (!confirm('Are you sure you want to delete this page? All blocks will be removed.')) {
                    return;
                }

                try {
                    const response = await fetch(`${this.apiBase}/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.showNotification(data.message, 'success');
                        this.loadPages();
                    } else {
                        this.showNotification(data.error || 'Failed to delete page', 'error');
                    }
                } catch (error) {
                    console.error('Failed to delete page:', error);
                    this.showNotification('Failed to delete page', 'error');
                }
            },

            viewPage(id) {
                window.location.href = `/admin/pages/${id}/editor`;
            },

            closeModal() {
                document.getElementById('page-modal').classList.remove('active');
            },

            showNotification(message, type = 'success') {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.textContent = message;
                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.remove();
                }, 3000);
            },

            formatDate(dateString) {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleDateString();
            }
        };

        // Auto-generate slug from name
        document.getElementById('page-name')?.addEventListener('input', (e) => {
            if (!document.getElementById('page-id').value) {
                const slug = e.target.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                document.getElementById('page-slug').value = slug;
            }
        });

        // Close modal on outside click
        document.getElementById('page-modal')?.addEventListener('click', (e) => {
            if (e.target.id === 'page-modal') {
                pageManager.closeModal();
            }
        });

        // Load pages on page load
        document.addEventListener('DOMContentLoaded', () => {
            pageManager.loadPages();
        });
    </script>
</body>
</html>

