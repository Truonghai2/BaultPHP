@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('styles')
<style>
    .dashboard-container {
        padding: 2rem;
        background: #f7f7f7;
    }
    
    .welcome-section {
        background: #fff;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .welcome-section h1 {
        color: #1177d1;
        font-size: 1.8rem;
        margin-bottom: 1rem;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .dashboard-card {
        display: flex;
        flex-direction: column;
        background: #fff;
        border-radius: 8px;
        padding: 1.5rem;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .card-icon {
        width: 48px;
        height: 48px;
        margin-bottom: 1rem;
        color: #1177d1;
    }

    .dashboard-card h3 {
        color: #1177d1;
        font-size: 1.2rem;
        margin: 0 0 0.5rem 0;
        font-weight: 500;
    }

    .dashboard-card p {
        color: #666;
        font-size: 0.9rem;
        margin: 0;
        line-height: 1.5;
    }

    .card-footer {
        margin-top: auto;
        padding-top: 1rem;
        font-size: 0.9rem;
        color: #1177d1;
    }

    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        background: #e8f4ff;
        color: #1177d1;
    }

    .status-badge.coming-soon {
        background: #fff3e0;
        color: #f57c00;
    }
</style>
@endsection

@section('content')
<div class="dashboard-container">
    <div class="welcome-section">
        <h1>Admin Dashboard</h1>
        <p>Welcome to the BaultPHP administration panel. Here you can manage all aspects of your application.</p>
    </div>

    <div class="dashboard-grid">
        <a href="/admin/modules" class="dashboard-card">
            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            <h3>Module Management</h3>
            <p>Install, enable, disable, and manage your application modules. Control which features are available in your system.</p>
            <div class="card-footer">
                Manage Modules →
            </div>
        </a>

        <a href="#" class="dashboard-card">
            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <h3>User Management</h3>
            <p>Manage users, assign roles, and control permissions across your application.</p>
            <div class="card-footer">
                <span class="status-badge coming-soon">Coming Soon</span>
            </div>
        </a>

        <a href="/admin/server/health" class="dashboard-card">
            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <h3>Server Health</h3>
            <p>Monitor your application's vital services, performance metrics, and system status in real-time.</p>
            <div class="card-footer">
                View Status →
            </div>
        </a>

        <a href="#" class="dashboard-card">
            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
            </svg>
            <h3>Appearance</h3>
            <p>Customize themes, layouts, and visual elements of your application interface.</p>
            <div class="card-footer">
                <span class="status-badge coming-soon">Coming Soon</span>
            </div>
        </a>
    </div>
</div>
@endsection