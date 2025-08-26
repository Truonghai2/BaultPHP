@if(config('debug.enabled', false))
<style>
    #debug-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background-color: #222;
        color: #eee;
        font-family: monospace;
        font-size: 14px;
        z-index: 99999;
        border-top: 2px solid #f80;
        max-height: 50vh;
        display: flex;
        flex-direction: column;
    }
    #debug-bar-header {
        display: flex;
        padding: 5px 10px;
        background-color: #333;
        cursor: pointer;
    }
    #debug-bar-header span {
        margin-right: 20px;
    }
    #debug-bar-header span b {
        color: #f80;
    }
    #debug-bar-content {
        overflow: auto;
        padding: 10px;
        background-color: #1a1a1a;
        display: none;
    }
    #debug-bar-content h3 {
        color: #f80;
        border-bottom: 1px solid #444;
        padding-bottom: 5px;
    }
    #debug-bar-content pre {
        white-space: pre-wrap;
        word-wrap: break-word;
        background: #282c34;
        padding: 10px;
        border-radius: 5px;
    }
    #debug-bar-content dl {
        margin: 10px 0;
        padding-left: 20px;
    }
    #debug-bar-content dt {
        font-weight: bold;
        color: #f80;
    }
    #debug-bar-content dd {
        margin-left: 20px;
        margin-bottom: 10px;
    }
    #debug-bar-content dd pre { margin-top: 5px; }
</style>

<div id="debug-bar">
    <div id="debug-bar-header">
        <span>Bault Debug</span>
        <span id="debug-info-duration"></span>
        <span id="debug-info-memory"></span>
        <span id="debug-info-queries"></span>
        <span id="debug-info-events"></span>
        <span id="debug-info-config"></span>
    </div>
    <div id="debug-bar-content">
        <div id="debug-content-queries"></div>
        <div id="debug-content-events"></div>
        <div id="debug-content-info"></div>
        <div id="debug-content-config"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        const promise = originalFetch.apply(this, args);
        promise.then(response => {
            if (response.headers.has('X-Debug-ID')) {
                const debugId = response.headers.get('X-Debug-ID');
                fetchDebugData(debugId);
            }
        });
        return promise;
    };

    function fetchDebugData(id) {
        fetch(`/_debug/${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    console.warn('DebugBar:', data.error);
                    return;
                }
                updateDebugBar(data);
            })
            .catch(err => console.error('Failed to fetch debug data:', err));
    }

    function updateDebugBar(data) {
        const durationEl = document.getElementById('debug-info-duration');
        const memoryEl = document.getElementById('debug-info-memory');
        const queriesEl = document.getElementById('debug-info-queries');
        const eventsEl = document.getElementById('debug-info-events');
        const configEl = document.getElementById('debug-info-config');
        const queriesContentEl = document.getElementById('debug-content-queries');
        const eventsContentEl = document.getElementById('debug-content-events');
        const infoContentEl = document.getElementById('debug-content-info');
        const configContentEl = document.getElementById('debug-content-config');

        durationEl.innerHTML = `Time: <b>${data.info.duration_ms || '?'}ms</b>`;
        memoryEl.innerHTML = `Memory: <b>${data.info.memory_peak || '?'}</b>`;
        queriesEl.innerHTML = `Queries: <b>${data.queries.length || 0}</b>`;
        eventsEl.innerHTML = `Events: <b>${(data.events || []).length}</b>`;

        let queriesHtml = '<h3>SQL Queries</h3>';
        if (data.queries && data.queries.length > 0) {
            data.queries.forEach(q => {
                queriesHtml += `<pre><code>[${q.duration_ms}ms] ${q.sql}</code></pre>`;
            });
        } else {
            queriesHtml += '<p>No queries recorded.</p>';
        }
        queriesContentEl.innerHTML = queriesHtml;

        let eventsHtml = '<h3>Dispatched Events</h3>';
        if (data.events && data.events.length > 0) {
            eventsHtml += '<dl>';
            data.events.forEach(e => {
                // Safely parse and format the payload
                let payloadHtml = '';
                try {
                    // The payload is a JSON string, so we parse and re-stringify it for pretty printing.
                    payloadHtml = e.payload && e.payload !== 'null' ? `<pre><code>${JSON.stringify(JSON.parse(e.payload), null, 2)}</code></pre>` : '';
                } catch (err) { payloadHtml = `<pre><code>${e.payload || 'Not available'}</code></pre>`; } // Fallback for non-JSON payloads
                eventsHtml += `<dt>${e.name}</dt><dd>${payloadHtml}</dd>`;
            });
            eventsHtml += '</dl>';
        } else {
            eventsHtml += '<p>No events recorded.</p>';
        }
        eventsContentEl.innerHTML = eventsHtml;

        configEl.innerHTML = `Config: <b>Loaded</b>`;
        let configHtml = '<h3>Application Configuration</h3>';
        if (data.config && Object.keys(data.config).length > 0) {
            // Pretty print the JSON object
            configHtml += `<pre><code>${JSON.stringify(data.config, null, 2)}</code></pre>`;
        } else {
            configHtml += '<p>No configuration data recorded.</p>';
        }
        configContentEl.innerHTML = configHtml;

        infoContentEl.innerHTML = `<h3>Request Info</h3><pre>${JSON.stringify(data.info, null, 2)}</pre>`;
    }

    const header = document.getElementById('debug-bar-header');
    const content = document.getElementById('debug-bar-content');
    header.addEventListener('click', () => {
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
        } else {
            content.style.display = 'none';
        }
    });

    const initialDebugId = '{{ app("request_id") ?? "" }}';
    if (initialDebugId) {
        setTimeout(() => fetchDebugData(initialDebugId), 100);
    }
});
</script>
@endif
