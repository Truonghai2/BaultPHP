@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="content-header">
    <nav class="breadcrumb">
        <a href="/admin">Admin</a>
        <span class="breadcrumb-separator">/</span>
        <span>Dashboard</span>
    </nav>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-description">Welcome back! Here's an overview of your system.</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Active Modules</div>
            <div class="stat-value" id="active-modules-count">-</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Server Status</div>
            <div class="stat-value" style="font-size: 1.25rem; color: var(--success);">Healthy</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Users</div>
            <div class="stat-value">1,234</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon danger">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">Cache Hit Rate</div>
            <div class="stat-value">94.2%</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Quick Actions</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <a href="/admin/pages" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Manage Pages
            </a>
            <a href="/admin/modules" class="btn btn-outline">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Manage Modules
            </a>
            <a href="/admin/server/health" class="btn btn-outline">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                View Analytics
            </a>
            <a href="/admin/cors" class="btn btn-outline">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                CORS Settings
            </a>
            <button class="btn btn-outline" onclick="location.reload()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Clear Cache
            </button>
        </div>
    </div>
    </div>

<!-- Recent Activity -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Activity</h2>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: flex; align-items: start; gap: 1rem; padding: 1rem; background: var(--sidebar-bg); border-radius: 8px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--success); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
                    </div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 0.25rem;">Module Enabled</div>
                        <div style="font-size: 0.9rem; color: var(--text-secondary);">User module has been enabled successfully</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;">2 minutes ago</div>
                    </div>
            </div>

                <div style="display: flex; align-items: start; gap: 1rem; padding: 1rem; background: var(--sidebar-bg); border-radius: 8px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
                    </div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 0.25rem;">New User Registration</div>
                        <div style="font-size: 0.9rem; color: var(--text-secondary);">John Doe registered a new account</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;">1 hour ago</div>
                    </div>
            </div>

                <div style="display: flex; align-items: start; gap: 1rem; padding: 1rem; background: var(--sidebar-bg); border-radius: 8px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--warning); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
                    </div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 0.25rem;">Cache Warning</div>
                        <div style="font-size: 0.9rem; color: var(--text-secondary);">Cache size is above 80%, consider clearing</div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;">3 hours ago</div>
                    </div>
                </div>
            </div>
        </div>
            </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">System Info</h2>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.25rem;">PHP Version</div>
                    <div style="font-weight: 600;">{{ PHP_VERSION }}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Framework</div>
                    <div style="font-weight: 600;">BaultPHP {{ config('app.version', '1.0.0') }}</div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Environment</div>
                    <div style="font-weight: 600; color: {{ config('app.env') === 'production' ? 'var(--success)' : 'var(--warning)' }}">
                        {{ ucfirst(config('app.env', 'production')) }}
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Debug Mode</div>
                    <div style="font-weight: 600; color: {{ config('app.debug') ? 'var(--warning)' : 'var(--success)' }}">
                        {{ config('app.debug') ? 'Enabled' : 'Disabled' }}
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Timezone</div>
                    <div style="font-weight: 600;">{{ config('app.timezone', 'UTC') }}</div>
                </div>
            </div>
            </div>
    </div>
</div>

@push('scripts')
<script>
// Fetch modules count
fetch('/api/admin/modules')
    .then(res => res.json())
    .then(data => {
        const activeCount = data.modules ? data.modules.filter(m => m.enabled).length : 0;
        document.getElementById('active-modules-count').textContent = activeCount;
    })
    .catch(() => {
        document.getElementById('active-modules-count').textContent = '0';
    });
</script>
@endpush
@endsection
