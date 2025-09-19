@extends('layouts.app')

@section('title', 'Server Health Status')

@section('styles')
<style>
    .health-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
    .health-card { border: 1px solid #e1e4e8; border-radius: 6px; padding: 1.5rem; background-color: #fff; }
    .health-card h3 { margin-top: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1em; }
    .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; }
    .status-up { background-color: #28a745; }
    .status-down { background-color: #dc3545; }
    .status-degraded { background-color: #ffc107; }
    .details { font-size: 0.9em; color: #555; margin-top: 1rem; }
    .details pre { background-color: #f7f7f7; padding: 0.8em; border-radius: 4px; white-space: pre-wrap; word-break: break-all; font-size: 0.85em; }
</style>
@endsection

@section('content')
    <h1>Server Health Status</h1>
    <p style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem;">
        Overall Status: 
        <span class="status-indicator {{ 'status-' . strtolower($healthData['status']) }}"></span> 
        <strong>{{ $healthData['status'] }}</strong>
    </p>

    <div class="health-grid">
        @foreach($healthData['components'] as $name => $component)
            <div class="health-card">
                <h3>
                    <span class="status-indicator {{ 'status-' . strtolower($component['status']) }}"></span>
                    {{ ucfirst($name) }}
                </h3>
                <div class="details">
                    <strong>Status:</strong> {{ $component['status'] }}
                    @if(!empty($component['details']))
                        <pre><code>{{ json_encode($component['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection