@extends('layouts.admin')

@section('title', 'Module Settings - ' . $moduleName)

@section('styles')
<style>
    .settings-groups {
        display: flex;
        gap: 1.5rem;
    }

    .settings-sidebar {
        width: 250px;
        flex-shrink: 0;
    }

    .settings-content {
        flex: 1;
        min-width: 0;
    }

    .group-nav {
        position: sticky;
        top: calc(var(--header-height) + 1rem);
    }

    .group-nav-item {
        display: block;
        padding: 0.75rem 1rem;
        text-decoration: none;
        color: var(--text-primary);
        border-radius: 8px;
        margin-bottom: 0.5rem;
        transition: all 0.2s;
    }

    .group-nav-item:hover {
        background: var(--sidebar-bg);
    }

    .group-nav-item.active {
        background: var(--sidebar-active);
        color: var(--primary-color);
        font-weight: 600;
    }

    .settings-group {
        margin-bottom: 2rem;
    }

    .group-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--border-color);
    }

    .setting-item {
        margin-bottom: 1.5rem;
        padding: 1.25rem;
        background: var(--sidebar-bg);
        border-radius: 8px;
        border-left: 3px solid transparent;
        transition: border-color 0.2s;
    }

    .setting-item:hover {
        border-left-color: var(--primary-color);
    }

    .setting-item.modified {
        border-left-color: var(--warning);
        background: rgba(255, 193, 7, 0.05);
    }

    .setting-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0.75rem;
    }

    .setting-label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .setting-key {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .setting-badges {
        display: flex;
        gap: 0.5rem;
    }

    .setting-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        background: var(--primary-color);
        color: white;
    }

    .setting-badge.encrypted {
        background: var(--danger);
    }

    .setting-badge.public {
        background: var(--success);
    }

    .setting-description {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-bottom: 0.75rem;
    }

    .setting-control {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }

    .setting-input {
        flex: 1;
        padding: 0.625rem;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-size: 0.9rem;
        outline: none;
        transition: all 0.2s;
    }

    .setting-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(15, 108, 191, 0.1);
    }

    .setting-toggle {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
    }

    .setting-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 26px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    .setting-toggle input:checked + .toggle-slider {
        background-color: var(--primary-color);
    }

    .setting-toggle input:checked + .toggle-slider:before {
        transform: translateX(24px);
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
        opacity: 0.5;
    }

    .actions-bar {
        position: sticky;
        bottom: 0;
        background: white;
        border-top: 1px solid var(--border-color);
        padding: 1rem;
        margin: 0 -2rem;
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        box-shadow: 0 -2px 8px rgba(0,0,0,0.05);
    }

    @media (max-width: 768px) {
        .settings-groups {
            flex-direction: column;
        }

        .settings-sidebar {
            width: 100%;
        }

        .group-nav {
            position: static;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 1rem;
        }

        .group-nav-item {
            white-space: nowrap;
        }
    }
</style>
@endsection

@section('content')
<div class="content-header">
    <nav class="breadcrumb">
        <a href="/admin">Admin</a>
        <span class="breadcrumb-separator">/</span>
        <a href="/admin/modules">Modules</a>
        <span class="breadcrumb-separator">/</span>
        <span>{{ $moduleName }} Settings</span>
    </nav>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">{{ $moduleName }} Settings</h1>
            <p class="page-description">Configure module options and behavior</p>
        </div>
        <button class="btn btn-outline" onclick="history.back()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Modules
        </button>
    </div>
</div>

<div class="settings-groups" id="settings-container">
    <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 40px; height: 40px; margin: 0 auto 1rem; animation: spin 1s linear infinite;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <div>Loading settings...</div>
    </div>
</div>

<!-- Toast -->
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
<script>
let settingsData = {};
let originalSettings = {};
let modifiedKeys = new Set();

async function loadSettings() {
    try {
        const response = await fetch('/api/admin/modules/{{ $moduleName }}/settings');
        const data = await response.json();
        
        if (data.success) {
            settingsData = data.data;
            originalSettings = JSON.parse(JSON.stringify(data.data.settings));
            renderSettings();
        } else {
            showError(data.error);
        }
    } catch (error) {
        showError('Failed to load settings: ' + error.message);
    }
}

function renderSettings() {
    const container = document.getElementById('settings-container');
    
    if (!settingsData.settings || settingsData.settings.length === 0) {
        container.innerHTML = renderEmptyState();
        return;
    }

    // Group settings by group
    const grouped = {};
    settingsData.settings.forEach(setting => {
        const group = setting.group || 'general';
        if (!grouped[group]) {
            grouped[group] = [];
        }
        grouped[group].push(setting);
    });

    // Render sidebar and content
    container.innerHTML = `
        <aside class="settings-sidebar">
            <nav class="group-nav">
                ${Object.keys(grouped).map((group, index) => `
                    <a href="#group-${group}" class="group-nav-item ${index === 0 ? 'active' : ''}" 
                       onclick="activateGroup('${group}', event)">
                        ${capitalize(group)}
                    </a>
                `).join('')}
            </nav>
        </aside>
        <div class="settings-content">
            ${Object.entries(grouped).map(([group, settings]) => `
                <div class="settings-group" id="group-${group}">
                    <h2 class="group-title">${capitalize(group)}</h2>
                    ${settings.map(setting => renderSetting(setting)).join('')}
                </div>
            `).join('')}
            <div class="actions-bar">
                <button class="btn btn-outline" onclick="resetSettings()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Reset to Defaults
                </button>
                <button class="btn btn-outline" onclick="clearCache()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Clear Cache
                </button>
                <button class="btn btn-primary" onclick="saveSettings()" id="save-btn" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Save Changes
                </button>
            </div>
        </div>
    `;
}

function renderSetting(setting) {
    const isModified = modifiedKeys.has(setting.key);
    
    return `
        <div class="setting-item ${isModified ? 'modified' : ''}" id="setting-${setting.key}">
            <div class="setting-header">
                <div>
                    <div class="setting-label">${getLabel(setting)}</div>
                    <div class="setting-key">${setting.key}</div>
                </div>
                <div class="setting-badges">
                    ${setting.is_encrypted ? '<span class="setting-badge encrypted">🔒 Encrypted</span>' : ''}
                    ${setting.is_public ? '<span class="setting-badge public">Public</span>' : ''}
                </div>
            </div>
            ${setting.description ? `<div class="setting-description">${setting.description}</div>` : ''}
            <div class="setting-control">
                ${renderControl(setting)}
            </div>
        </div>
    `;
}

function renderControl(setting) {
    const value = setting.value !== null ? setting.value : '';
    
    switch (setting.type) {
        case 'boolean':
            return `
                <label class="setting-toggle">
                    <input type="checkbox" 
                           ${value ? 'checked' : ''} 
                           onchange="updateSetting('${setting.key}', this.checked, '${setting.type}')">
                    <span class="toggle-slider"></span>
                </label>
                <span>${value ? 'Enabled' : 'Disabled'}</span>
            `;
            
        case 'integer':
        case 'float':
            return `
                <input type="number" 
                       class="setting-input" 
                       value="${value}" 
                       onchange="updateSetting('${setting.key}', this.value, '${setting.type}')">
            `;
            
        case 'json':
        case 'array':
            return `
                <textarea class="setting-input" 
                          rows="4" 
                          onchange="updateSetting('${setting.key}', this.value, '${setting.type}')">${typeof value === 'object' ? JSON.stringify(value, null, 2) : value}</textarea>
            `;
            
        default:
            return `
                <input type="text" 
                       class="setting-input" 
                       value="${value}" 
                       onchange="updateSetting('${setting.key}', this.value, '${setting.type}')">
            `;
    }
}

function renderEmptyState() {
    return `
        <div class="empty-state" style="flex: 1;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <h3>No Settings Defined</h3>
            <p>This module doesn't have any configurable settings yet.</p>
            <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                Create a <code>settings.php</code> file in the module directory to define settings.
            </p>
        </div>
    `;
}

function updateSetting(key, value, type) {
    modifiedKeys.add(key);
    
    // Update visual indication
    const settingEl = document.getElementById(`setting-${key}`);
    if (settingEl) {
        settingEl.classList.add('modified');
    }
    
    // Enable save button
    document.getElementById('save-btn').disabled = false;
}

async function saveSettings() {
    const settings = [];
    
    modifiedKeys.forEach(key => {
        const setting = settingsData.settings.find(s => s.key === key);
        if (setting) {
            let value;
            const input = document.querySelector(`#setting-${key} input, #setting-${key} textarea`);
            
            if (input) {
                if (input.type === 'checkbox') {
                    value = input.checked;
                } else {
                    value = input.value;
                }
            }
            
            settings.push({
                key: setting.key,
                value: value,
                type: setting.type,
                description: setting.description,
                group: setting.group,
                encrypted: setting.is_encrypted,
                public: setting.is_public,
                order: setting.order,
            });
        }
    });

    if (settings.length === 0) {
        showToast('No changes to save', 'info');
        return;
    }

    try {
        const response = await fetch('/api/admin/modules/{{ $moduleName }}/settings/bulk', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ settings })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            modifiedKeys.clear();
            document.getElementById('save-btn').disabled = true;
            
            // Remove modified indicators
            document.querySelectorAll('.setting-item.modified').forEach(el => {
                el.classList.remove('modified');
            });
        } else {
            showToast(data.error, 'error');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
}

async function resetSettings() {
    if (!confirm('Are you sure you want to reset all settings to their default values?')) {
        return;
    }

    try {
        const response = await fetch('/api/admin/modules/{{ $moduleName }}/settings/reset', {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(data.error, 'error');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
}

async function clearCache() {
    try {
        const response = await fetch('/api/admin/modules/{{ $moduleName }}/settings/cache/clear', {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
        } else {
            showToast(data.error, 'error');
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
}

function activateGroup(group, event) {
    event.preventDefault();
    
    // Update nav
    document.querySelectorAll('.group-nav-item').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
    
    // Scroll to group
    document.getElementById(`group-${group}`).scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function getLabel(setting) {
    // Get label from schema if exists
    if (settingsData.schema) {
        for (const [group, settings] of Object.entries(settingsData.schema)) {
            if (settings[setting.key] && settings[setting.key].label) {
                return settings[setting.key].label;
            }
        }
    }
    
    // Fallback to beautified key
    return setting.key.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
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

function showError(message) {
    const container = document.getElementById('settings-container');
    container.innerHTML = `
        <div class="alert alert-danger" style="flex: 1;">
            ${message}
        </div>
    `;
}

// Initial load
loadSettings();
</script>
@endpush
@endsection

