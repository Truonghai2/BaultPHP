@extends('layouts.admin')

@section('title', 'CORS Origins Management')

@section('content')
<div class="content-header">
    <nav class="breadcrumb">
        <a href="/admin">Admin</a>
        <span class="breadcrumb-separator">/</span>
        <span>CORS Origins</span>
    </nav>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">CORS Origins Management</h1>
            <p class="page-description">Manage allowed cross-origin request sources</p>
        </div>
        <button class="btn btn-primary" onclick="window.location.reload()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Refresh
        </button>
    </div>
</div>

<!-- CORS Info Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">CORS Configuration</h2>
    </div>
    <div class="card-body" id="cors-info-content">
        <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
            Loading configuration...
        </div>
    </div>
</div>

<!-- Allowed Origins Card -->
<div class="card">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2 class="card-title">Allowed Origins</h2>
            <button class="btn btn-outline btn-sm" onclick="clearCache()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Clear Cache
            </button>
        </div>
    </div>
    <div class="card-body">
        <div id="origins-list" style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                Loading origins...
            </div>
        </div>
    </div>
</div>

<!-- Test Origin Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Test Origin</h2>
    </div>
    <div class="card-body">
        <div class="form-group" style="margin-bottom: 1rem;">
            <label class="form-label">Enter origin URL to test</label>
            <div style="display: flex; gap: 1rem;">
                <input 
                    type="text" 
                    id="test-origin-input" 
                    class="form-control" 
                    placeholder="https://example.com"
                    style="flex: 1;"
                >
                <button class="btn btn-primary" onclick="testOrigin()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    Test
                </button>
            </div>
        </div>
        <div id="test-result" style="display: none;"></div>
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
    .origin-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--sidebar-bg);
        border-radius: 8px;
        border-left: 4px solid var(--primary-color);
    }

    .origin-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .origin-text {
        flex: 1;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.9rem;
        color: var(--text-primary);
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .info-item {
        display: flex;
        flex-direction: column;
    }

    .info-label {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .info-value {
        font-size: 1rem;
        color: var(--text-primary);
        font-weight: 600;
    }

    .test-result-box {
        padding: 1rem;
        border-radius: 8px;
        margin-top: 1rem;
    }

    .test-result-box.success {
        background: rgba(40, 167, 69, 0.1);
        border-left: 4px solid var(--success);
        color: var(--success);
    }

    .test-result-box.error {
        background: rgba(220, 53, 69, 0.1);
        border-left: 4px solid var(--danger);
        color: var(--danger);
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
// Fetch CORS info
async function fetchCorsInfo() {
    try {
        const response = await fetch('/api/admin/cors/info');
        const data = await response.json();
        
        if (data.success) {
            renderCorsInfo(data.data);
        }
    } catch (error) {
        document.getElementById('cors-info-content').innerHTML = `
            <div class="alert alert-danger">Failed to load CORS configuration</div>
        `;
    }
}

function renderCorsInfo(data) {
    const { cors, origins } = data;
    
    document.getElementById('cors-info-content').innerHTML = `
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Credentials Support</div>
                <div class="info-value" style="color: ${cors.supports_credentials ? 'var(--success)' : 'var(--danger)'}">
                    ${cors.supports_credentials ? '✓ Enabled' : '✗ Disabled'}
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Max Age (Preflight Cache)</div>
                <div class="info-value">${cors.max_age}s</div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Origins</div>
                <div class="info-value">${origins.count}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Cache Status</div>
                <div class="info-value" style="color: ${origins.cache_enabled ? 'var(--success)' : 'var(--text-secondary)'}">
                    ${origins.cache_enabled ? 'Enabled' : 'Disabled'}
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Allowed Methods</div>
                <div class="info-value" style="font-size: 0.85rem; font-family: monospace;">
                    ${cors.allowed_methods.join(', ')}
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Environment</div>
                <div class="info-value" style="color: ${data.environment === 'production' ? 'var(--success)' : 'var(--warning)'}">
                    ${data.environment.toUpperCase()}
                </div>
            </div>
        </div>
    `;
}

// Fetch origins list
async function fetchOrigins() {
    try {
        const response = await fetch('/api/admin/cors/origins');
        const data = await response.json();
        
        if (data.success) {
            renderOrigins(data.data.origins);
        }
    } catch (error) {
        document.getElementById('origins-list').innerHTML = `
            <div class="alert alert-danger">Failed to load origins</div>
        `;
    }
}

function renderOrigins(origins) {
    const container = document.getElementById('origins-list');
    
    if (origins.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="48" height="48" style="margin: 0 auto 1rem; opacity: 0.5;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p>No origins configured</p>
            </div>
        `;
        return;
    }

    container.innerHTML = origins.map(origin => `
        <div class="origin-item">
            <div class="origin-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="origin-text">${origin}</div>
        </div>
    `).join('');
}

// Test origin
async function testOrigin() {
    const input = document.getElementById('test-origin-input');
    const origin = input.value.trim();
    const resultDiv = document.getElementById('test-result');
    
    if (!origin) {
        showToast('Please enter an origin URL', 'error');
        return;
    }

    try {
        const response = await fetch('/api/admin/cors/origins/check', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ origin })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const isAllowed = data.data.is_allowed;
            resultDiv.style.display = 'block';
            resultDiv.className = `test-result-box ${isAllowed ? 'success' : 'error'}`;
            resultDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isAllowed ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12'}"/>
                    </svg>
                    <div>
                        <strong>${isAllowed ? 'Origin Allowed' : 'Origin Blocked'}</strong>
                        <div style="font-size: 0.9rem; margin-top: 0.25rem; opacity: 0.9;">
                            ${origin} is ${isAllowed ? 'allowed' : 'not allowed'} to make cross-origin requests
                        </div>
                    </div>
                </div>
            `;
        }
    } catch (error) {
        showToast('Error testing origin: ' + error.message, 'error');
    }
}

// Clear cache
async function clearCache() {
    try {
        const response = await fetch('/api/admin/cors/origins/cache/clear', {
            method: 'POST'
        });
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            fetchOrigins(); // Refresh origins list
        }
    } catch (error) {
        showToast('Error clearing cache: ' + error.message, 'error');
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

// Allow testing with Enter key
document.getElementById('test-origin-input').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        testOrigin();
    }
});

// Initial load
fetchCorsInfo();
fetchOrigins();
</script>
@endpush
@endsection

