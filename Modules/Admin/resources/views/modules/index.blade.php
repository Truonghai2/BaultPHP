@extends('layouts.admin')

@section('title', 'Module Management')

@section('styles')
<style>
    .table-container {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    thead {
        background: var(--sidebar-bg);
    }

    th {
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
    }

    td {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }

    tr:hover {
        background: var(--sidebar-bg);
    }

    .module-name {
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .module-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .module-description {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.75rem;
        border-radius: 16px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge.enabled {
        background: rgba(40, 167, 69, 0.1);
        color: var(--success);
    }

    .badge.disabled {
        background: rgba(108, 117, 125, 0.1);
        color: #6c757d;
    }

    .badge-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
    }

    .actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }

    .btn-toggle {
        background: transparent;
        border: 1px solid var(--border-color);
        color: var(--text-primary);
    }

    .btn-toggle:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    .btn-delete {
        background: transparent;
        border: 1px solid var(--danger);
        color: var(--danger);
    }

    .btn-delete:hover {
        background: var(--danger);
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-state svg {
        width: 80px;
        height: 80px;
        color: var(--text-secondary);
        margin-bottom: 1rem;
    }

    .empty-state h3 {
        font-size: 1.25rem;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        color: var(--text-secondary);
    }

    .loading {
        text-align: center;
        padding: 3rem;
        color: var(--text-secondary);
    }

    .module-version {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.85rem;
        color: var(--text-secondary);
        background: var(--sidebar-bg);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        align-items: center;
        justify-content: center;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        margin-bottom: 1.5rem;
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .modal-body {
        margin-bottom: 1.5rem;
    }

    .modal-footer {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.9rem;
        outline: none;
        transition: all 0.2s;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(15, 108, 191, 0.1);
    }

    .toast {
        position: fixed;
        top: calc(var(--header-height) + 1rem);
        right: 1rem;
        background: white;
        border-radius: 8px;
        padding: 1rem 1.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: none;
        align-items: center;
        gap: 0.75rem;
        z-index: 2001;
        min-width: 300px;
    }

    .toast.show {
        display: flex;
        animation: slideIn 0.3s ease;
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

    .toast-icon {
        width: 24px;
        height: 24px;
        flex-shrink: 0;
    }

    .toast.success .toast-icon {
        color: var(--success);
    }

    .toast.error .toast-icon {
        color: var(--danger);
    }
</style>
@endsection

@section('content')
<div class="content-header">
    <nav class="breadcrumb">
        <a href="/admin">Admin</a>
        <span class="breadcrumb-separator">/</span>
        <span>Module Management</span>
    </nav>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Module Management</h1>
            <p class="page-description">Install, enable, and manage application modules</p>
        </div>
        <button class="btn btn-primary" onclick="openInstallModal()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Install Module
        </button>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table id="modules-table">
            <thead>
                <tr>
                    <th style="width: 40%;">Module</th>
                    <th style="width: 15%;">Version</th>
                    <th style="width: 15%;">Status</th>
                    <th style="width: 30%;">Actions</th>
                </tr>
            </thead>
            <tbody id="modules-tbody">
                <tr>
                    <td colspan="4" class="loading">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24" style="display: inline; animation: spin 1s linear infinite;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Loading modules...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Install Module Modal -->
<div id="install-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Install New Module</h2>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Module ZIP File</label>
                <input type="file" id="module-file" class="form-control" accept=".zip">
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">
                    Upload a ZIP file containing your module
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeInstallModal()">Cancel</button>
            <button class="btn btn-primary" onclick="installModule()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                Install
        </button>
    </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast">
    <svg class="toast-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
    </svg>
    <div id="toast-message"></div>
</div>

@push('scripts')
<style>
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>
<script>
let modules = [];

// Get CSRF token from meta tag
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

// Fetch modules
async function fetchModules() {
    try {
        const response = await fetch('/api/admin/modules');
        const data = await response.json();
        modules = data.modules || [];
        renderTable();
    } catch (error) {
        showToast('Error loading modules: ' + error.message, 'error');
        document.getElementById('modules-tbody').innerHTML = `
            <tr>
                <td colspan="4" class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3>Error Loading Modules</h3>
                    <p>${error.message}</p>
                </td>
            </tr>
        `;
    }
}

function renderTable() {
    const tbody = document.getElementById('modules-tbody');
    
    if (modules.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <h3>No Modules Found</h3>
                    <p>Install your first module to get started</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = modules.map(module => `
        <tr>
            <td>
                <div class="module-name">
                    <div class="module-icon">${module.name.charAt(0).toUpperCase()}</div>
                    <div>
                        <div>${module.name}</div>
                        <div class="module-description">${module.description || 'No description available'}</div>
                    </div>
                </div>
            </td>
            <td>
                <span class="module-version">${module.version}</span>
            </td>
            <td>
                <span class="badge ${module.enabled ? 'enabled' : 'disabled'}">
                    <span class="badge-dot"></span>
                    ${module.enabled ? 'Enabled' : 'Disabled'}
                </span>
            </td>
            <td>
                <div class="actions">
                    <button class="btn btn-sm btn-outline" onclick="openSettings('${module.name}')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </button>
                    <button class="btn btn-sm btn-toggle" onclick="toggleModule('${module.name}')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        ${module.enabled ? 'Disable' : 'Enable'}
                    </button>
                    <button class="btn btn-sm btn-delete" onclick="deleteModule('${module.name}')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete
                    </button>
</div>
            </td>
        </tr>
    `).join('');
}

async function toggleModule(name) {
    try {
        const response = await fetch(`/api/admin/modules/${name}`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Content-Type': 'application/json'
            }
        });
        const data = await response.json();
        
        if (response.ok) {
            showToast(data.message, 'success');
            fetchModules();
        } else {
            showToast(data.error || 'Failed to toggle module', 'error');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
}

function openSettings(name) {
    window.location.href = `/admin/modules/${name}/settings`;
}

async function deleteModule(name) {
    if (!confirm(`Are you sure you want to delete "${name}" module? This action cannot be undone.`)) {
        return;
    }

    try {
        const response = await fetch(`/api/admin/modules/${name}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            showToast(`Module "${name}" deleted successfully`, 'success');
            fetchModules();
        } else {
            const data = await response.json();
            showToast(data.error || 'Failed to delete module', 'error');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
}

function openInstallModal() {
    document.getElementById('install-modal').classList.add('show');
}

function closeInstallModal() {
    document.getElementById('install-modal').classList.remove('show');
    document.getElementById('module-file').value = '';
}

async function installModule() {
    const fileInput = document.getElementById('module-file');
    const file = fileInput.files[0];
    
    if (!file) {
        showToast('Please select a ZIP file', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('module_zip', file);

    try {
        const response = await fetch('/api/admin/modules', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast(data.message || 'Module installed successfully', 'success');
            closeInstallModal();
            fetchModules();
        } else {
            showToast(data.error || 'Installation failed', 'error');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    
    toast.className = `toast ${type} show`;
    toastMessage.textContent = message;
    
    const icon = toast.querySelector('.toast-icon path');
    if (type === 'success') {
        icon.setAttribute('d', 'M5 13l4 4L19 7');
    } else {
        icon.setAttribute('d', 'M6 18L18 6M6 6l12 12');
    }
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 5000);
}

// Close modal when clicking outside
document.getElementById('install-modal').addEventListener('click', (e) => {
    if (e.target.id === 'install-modal') {
        closeInstallModal();
    }
});

// Initial load
fetchModules();
</script>
@endpush
@endsection
