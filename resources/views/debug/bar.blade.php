@if(config('debug.enabled', false))
<style>
    #debug-bar {
        font-family: monospace;
        font-size: 14px;
        color: var(--color-text);
    }

    #debug-bar[data-theme="light"] {
        --color-primary: #007bff;
        --color-text: #212529;
        --color-bg-darkest: #f8f9fa;
        --color-bg-darker: #e9ecef;
        --color-bg-dark: #dee2e6;
        --color-bg-hover: #ced4da;
        --color-pre-bg: #e0e0e0;
        --color-border: #ced4da;
        --color-danger: #c53030;
        --color-danger-dark: #dc3545;
        --color-success: #2f855a;
        --color-info: #2b6cb0;
        --color-warning: #b7791f;
        --color-muted: #6c757d;
        --color-primary-highlight: rgba(0, 123, 255, 0.2);
    }

    #debug-bar[data-theme="dark"] {
        --color-primary: #f80;
        --color-text: #eee;
        --color-bg-darkest: #1a1a1a;
        --color-bg-darker: #222;
        --color-bg-dark: #333;
        --color-bg-hover: #444;
        --color-pre-bg: #282c34;
        --color-border: #444;
        --color-danger: #c53030;
        --color-danger-dark: #8b1a1a;
        --color-success: #2f855a;
        --color-info: #2b6cb0;
        --color-warning: #b7791f;
        --color-muted: #6c757d;
        --color-primary-highlight: rgba(255, 136, 0, 0.3);
    }

    #debug-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        background-color: var(--color-bg-darker);
        border-top: 2px solid var(--color-primary);
        max-height: 90vh;
        min-height: 30px;
        resize: vertical;
    }

    #debug-bar-resize-handle {
        width: 100%;
        height: 5px;
        background-color: var(--color-bg-dark);
        cursor: ns-resize;
        position: absolute;
        top: -3px;
        transition: background-color 0.2s ease-in-out;
    }
    #debug-bar-resize-handle:hover {
        background-color: var(--color-primary);
    }

    #debug-bar-header {
        display: flex;
        padding: 5px 10px;
        background-color: var(--color-bg-dark);
        cursor: pointer;
        align-items: center;
    }
    #debug-bar-header .debug-tab-link {
        padding: 5px 10px;
        margin-right: 5px;
        border-bottom: 2px solid transparent;
        transition: all 0.2s ease-in-out;
        white-space: nowrap;
    }
    #debug-bar-header .debug-tab-link:hover {
        background-color: var(--color-bg-hover);
    }
    #debug-bar-header .debug-tab-link.active {
        background-color: var(--color-bg-darkest);
        border-bottom-color: var(--color-primary);
    }
    #debug-bar-header span {
        margin-right: 20px;
    }
    #debug-bar-header span b {
        color: var(--color-primary);
    }

    /* --- Vùng nội dung --- */
    #debug-bar-content {
        overflow: auto;
        padding: 10px;
        background-color: var(--color-bg-darkest);
        display: none;
        flex-grow: 1;
    }
    #debug-bar-content h3 {
        color: var(--color-primary);
        border-bottom: 1px solid var(--color-border);
        padding-bottom: 5px;
        margin-top: 0;
    }
    #debug-bar-content pre {
        white-space: pre-wrap;
        word-wrap: break-word;
        background: var(--color-pre-bg);
        padding: 10px;
        border-radius: 5px;
        margin-top: 5px;
    }
    #debug-bar-content dl {
        margin: 10px 0;
        padding-left: 20px;
    }
    #debug-bar-content dt {
        font-weight: bold;
        color: var(--color-primary);
    }
    #debug-bar-content dd {
        margin-left: 20px;
        margin-bottom: 10px;
    }

    /* --- Badges --- */
    .debug-badge {
        color: white;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
        display: inline-block;
        text-align: center;
    }
    .debug-duplicate-badge {
        background-color: var(--color-danger-dark);
        margin-left: 10px;
    }
    .debug-cache-badge { /* Lớp này vẫn giữ lại để tương thích với các selector khác */
        margin-right: 10px;
        min-width: 45px;
    }
    .badge-hit { background-color: var(--color-success); }
    .badge-miss { background-color: var(--color-danger); }
    .badge-write { background-color: var(--color-info); }
    .badge-forget { background-color: var(--color-warning); }
    .badge-default { background-color: var(--color-muted); }

    /* Route Badges */
    .badge-route-get { background-color: var(--color-info); }
    .badge-route-post { background-color: var(--color-success); }
    .badge-route-put,
    .badge-route-patch { background-color: var(--color-warning); }
    .badge-route-delete { background-color: var(--color-danger); }
    .badge-route-options,
    .badge-route-head { background-color: var(--color-muted); }

    /* --- Input tìm kiếm --- */
    .debug-search-input {
        width: 100%;
        padding: 5px 8px;
        margin-bottom: 15px;
        background-color: var(--color-bg-darkest);
        color: var(--color-text);
        border: 1px solid var(--color-border);
        border-radius: 4px;
        box-sizing: border-box;
        font-family: monospace;
    }

    /* --- Panel Exceptions --- */
    #debug-content-exceptions .exception-item {
        margin-bottom: 20px;
        border-left: 3px solid var(--color-danger);
        padding-left: 15px;
    }
    #debug-content-exceptions summary {
        cursor: pointer;
        color: var(--color-primary);
    }

    /* --- Timeline Events --- */
    .debug-timeline-container {
        position: relative;
        width: 100%;
        height: 30px;
        background-color: var(--color-bg-dark);
        border-radius: 4px;
        margin: 20px 0;
    }
    .debug-timeline-event {
        position: absolute;
        top: 0;
        height: 100%;
        width: 2px;
        background-color: var(--color-primary);
        cursor: pointer;
        transition: transform 0.2s ease, background-color 0.2s ease;
    }
    .debug-timeline-event:hover {
        transform: scaleY(1.5);
        background-color: #ffc107; /* a brighter color on hover */
    }
    .debug-timeline-tooltip {
        display: none;
        position: absolute;
        bottom: 120%; /* Position above the event marker */
        left: 50%;
        transform: translateX(-50%);
        background-color: var(--color-bg-hover);
        color: var(--color-text);
        padding: 8px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 100000;
        pointer-events: none;
        box-shadow: 0 2px 5px rgba(0,0,0,0.5);
    }
    .debug-timeline-event:hover .debug-timeline-tooltip {
        display: block;
    }

    /* --- Hiệu ứng highlight --- */
    .debug-item-highlight {
        animation: debug-highlight-fade 2s ease-out;
    }

    @keyframes debug-highlight-fade {
        from { background-color: var(--color-primary-highlight); }
        to { background-color: transparent; }
    }

    /* --- Theme Switcher --- */
    #debug-theme-switcher {
        position: relative;
        margin-left: auto;
    }
    #debug-theme-switcher-btn {
        background: none;
        border: 1px solid var(--color-border);
        color: var(--color-text);
        padding: 2px 8px;
        border-radius: 4px;
        cursor: pointer;
    }
    #debug-theme-switcher-menu {
        display: none;
        position: absolute;
        bottom: 100%;
        right: 0;
        background-color: var(--color-bg-darkest);
        border: 1px solid var(--color-border);
        border-radius: 4px;
        padding: 5px;
        min-width: 100px;
        z-index: 100001;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    #debug-theme-switcher-menu a {
        display: block;
        padding: 5px 10px;
        color: var(--color-text);
        text-decoration: none;
        border-radius: 3px;
    }
    #debug-theme-switcher-menu a:hover {
        background-color: var(--color-bg-hover);
    }
    #debug-theme-switcher:hover #debug-theme-switcher-menu {
        display: block;
    }
</style>

<div id="debug-bar" data-theme="light">
    <div id="debug-bar-resize-handle"></div>
    <div id="debug-bar-header">
        <span class="debug-tab-link" data-tab="info" id="debug-info-request">Bault</span>
        <span id="debug-route-info" style="margin-right: 15px; font-size: 12px; color: var(--color-muted);"></span>
        <span class="debug-tab-link" data-tab="queries" id="debug-info-queries"></span>
        <span class="debug-tab-link" data-tab="events" id="debug-info-events"></span>
        <span class="debug-tab-link" data-tab="cache" id="debug-info-cache"></span>
        <span class="debug-tab-link" data-tab="session" id="debug-info-session"></span>
        <span class="debug-tab-link" data-tab="cookies" id="debug-info-cookies"></span>
        <span class="debug-tab-link" data-tab="exceptions" id="debug-info-exceptions"></span>
        <span class="debug-tab-link" data-tab="routes" id="debug-info-routes"></span>
        <span class="debug-tab-link" data-tab="config" id="debug-info-config"></span>
        <span class="debug-tab-link" data-tab="auth" id="debug-info-auth"></span>
        <span id="debug-info-duration"></span>
        <span id="debug-info-memory"></span>
        <div id="debug-theme-switcher">
            <button id="debug-theme-switcher-btn">Theme</button>
            <div id="debug-theme-switcher-menu">
                <a href="#" data-theme="light">Light</a>
                <a href="#" data-theme="dark">Dark</a>
            </div>
        </div>
    </div>
    <div id="debug-bar-content">
        <div class="debug-content-panel" id="debug-content-info"></div>
        <div class="debug-content-panel" id="debug-content-queries" style="display: none;"></div>
        <div class="debug-content-panel" id="debug-content-events" style="display: none;"></div>
        <div class="debug-content-panel" id="debug-content-cache" style="display: none;"></div>
        <div class="debug-content-panel" id="debug-content-session" style="display: none;"></div>
        <div class="debug-content-panel" id="debug-content-cookies" style="display: none;"></div>
        <div class="debug-content-panel" id="debug-content-exceptions" style="display: none;"></div>
        <div class="debug-content-panel" id="debug-content-routes" style="display: none;"></div>
        <div class="debug-content-panel" id="debug-content-config" style="display: none;"></div>
        <div class="debug-content-panel" id="debug-content-auth" style="display: none;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const BaultDebugBar = {
        elements: {},
        state: {
            startY: 0,
            startHeight: 0,
            lastRequestId: null,
            isFetching: false,
        },

        init() {
            this.cacheElements();
            this.state.lastRequestId = sessionStorage.getItem('bault-debug-last-id');
            
            const loadedFromSession = this.loadFromSession();
            if (!loadedFromSession) {
                this.fetchDebugData();
            }

            this.initEventListeners();
            this.initTheme();
            this.initWebSocket();
        },

        loadFromSession() {
            try {
                const lastData = sessionStorage.getItem('bault-debug-bar-data');
                if (lastData) {
                    const parsedData = JSON.parse(lastData);
                    this.update(parsedData);
                    this.state.lastRequestId = parsedData?.info?.id || null;
                    return true; // Báo hiệu đã tải thành công
                }
            } catch (err) {
                // ignore corrupted session storage
                console.warn('BaultDebugBar: invalid session data', err);
            }
            return false; // Báo hiệu không tải được dữ liệu
        },

        cacheElements() {
            const ids = [
                'debug-bar', 'debug-bar-header', 'debug-bar-content', 'debug-bar-resize-handle',
                'debug-info-request', 'debug-info-duration', 'debug-info-memory', 'debug-info-queries', 'debug-info-events',
                'debug-info-config', 'debug-info-cache', 'debug-info-routes', 'debug-info-session', 'debug-info-cookies', 'debug-info-exceptions', 'debug-info-auth',
                'debug-content-queries', 'debug-content-events', 'debug-content-info', 'debug-content-config', 
                'debug-content-cache', 'debug-content-routes', 'debug-content-session', 'debug-content-cookies', 'debug-content-exceptions', 'debug-content-auth'
            ];
            ids.forEach(id => {
                const camelCaseId = id.replace(/-(\w)/g, (_, c) => c.toUpperCase());
                this.elements[camelCaseId] = document.getElementById(id);
            });
        },

        initEventListeners() {
            const header = this.elements.debugBarHeader;
            if (header) {
                header.addEventListener('click', (e) => {
                    const target = e.target.closest('.debug-tab-link');
                    if (target && target.dataset && target.dataset.tab) {
                        this.switchTab(target.dataset.tab);
                    }
                });
            }

            if (this.elements.debugInfoRequest) {
                this.elements.debugInfoRequest.addEventListener('dblclick', () => this.toggleContent());
            }
            if (this.elements.debugBarResizeHandle) {
                this.elements.debugBarResizeHandle.addEventListener('mousedown', (e) => this.initResize(e));
            }

            // Open content when click any tab link (safe guard if elements are missing)
            document.querySelectorAll('.debug-tab-link').forEach(tab => {
                tab.addEventListener('click', () => this.openContent());
            });

            document.addEventListener('bault:debug-data-updated', (e) => {
                const data = e.detail;
                if (data) {
                    this.processNewData(data);
                }
            });

            document.addEventListener('bault:spa-navigated', () => {
                this.fetchDebugData();
            });

            // Setup search filters một lần duy nhất
            this.setupSearchFilter('debug-search-queries', '#debug-content-queries dl dt, #debug-content-queries dl dd');
            this.setupSearchFilter('debug-search-events', '#debug-content-events dl dt, #debug-content-events dl dd');
            this.setupSearchFilter('debug-search-cache', '#debug-content-cache dl dt, #debug-content-cache dl dd');
            this.setupSearchFilter('debug-search-routes', '#debug-content-routes dl dt, #debug-content-routes dl dd');
            this.setupSearchFilter('debug-search-session', '#debug-content-session dl dt, #debug-content-session dl dd');
            this.setupSearchFilter('debug-search-cookies', '#debug-content-cookies dl dt, #debug-content-cookies dl dd');
            this.setupSearchFilter('debug-search-auth', '#debug-content-auth dl dt, #debug-content-auth dl dd');
            this.setupSearchFilter('debug-search-exceptions', '#debug-content-exceptions .exception-item');
        },

        fetchDebugData() {
            if (this.state.isFetching) return;
            this.state.isFetching = true;
            
            const requestId = this.state.lastRequestId;
            if (!requestId) {
                this.state.isFetching = false;
                return;
            }

            fetch(`/_debug/${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.info && data.info.id !== this.state.lastRequestId) {
                        this.processNewData(data);
                    }
                })
                .catch(err => console.warn('BaultDebugBar: failed to fetch debug data', err))
                .finally(() => {
                    this.state.isFetching = false;
                });
        },

        processNewData(data) {
            if (!data || !data.info || !data.info.id) return;
            this.state.lastRequestId = data.info.id;
            try {
                sessionStorage.setItem('bault-debug-bar-data', JSON.stringify(data));
                sessionStorage.setItem('bault-debug-last-id', data.info.id);
            } catch (err) {
                console.warn('BaultDebugBar: failed to save session', err);
            }
            this.update(data);
        },
        
        initTheme() {
            const savedTheme = localStorage.getItem('bault-debug-theme') || 'light';
            this.applyTheme(savedTheme);

            document.getElementById('debug-theme-switcher-menu').addEventListener('click', (e) => {
                e.preventDefault();
                const target = e.target.closest('[data-theme]');
                if (target) {
                    this.applyTheme(target.dataset.theme);
                }
            });
        },

        applyTheme(themeName) {
            if (this.elements.debugBar) {
                this.elements.debugBar.dataset.theme = themeName;
            }
            localStorage.setItem('bault-debug-theme', themeName);
        },

        update(data) {
            if (!data) return;
            const { info = {}, queries = [], events = [], config = {}, cache = null, routes = [], session = {}, cookies = {}, exceptions = [], auth = null, browser_events = [] } = data;
            const el = this.elements;

            if (el.debugInfoDuration) el.debugInfoDuration.innerHTML = `Time: <b>${this.escapeHtml(String(info.duration_ms || '?'))}ms</b>`;
            if (el.debugInfoMemory) el.debugInfoMemory.innerHTML = `Memory: <b>${this.escapeHtml(info.memory_peak || '?')}</b>`;
            if (el.debugInfoQueries) el.debugInfoQueries.innerHTML = `Queries: <b>${queries.length || 0}</b>`;
            if (el.debugInfoEvents) el.debugInfoEvents.innerHTML = `Events: <b>${(events || []).length}</b>`;
            if (el.debugInfoSession) el.debugInfoSession.innerHTML = `Session: <b>${Object.keys(session || {}).length}</b>`;
            if (el.debugInfoCookies) el.debugInfoCookies.innerHTML = `Cookies: <b>${Object.keys(cookies || {}).length}</b>`;
            if (el.debugInfoExceptions) el.debugInfoExceptions.innerHTML = `Exceptions: <b style="color: ${exceptions.length > 0 ? '#c53030' : '#f80'}">${exceptions.length}</b>`;
            if (el.debugInfoConfig) el.debugInfoConfig.textContent = `Config: Loaded`;
            if (el.debugInfoRoutes) el.debugInfoRoutes.innerHTML = `Routes: <b>${(routes || []).length}</b>`;

            if (el.debugContentInfo) el.debugContentInfo.innerHTML = `<h3>Request Info</h3><pre><code>${this.escapeHtml(this.safeStringify(info))}</code></pre>`;
            if (el.debugContentConfig) el.debugContentConfig.innerHTML = `<h3>Application Configuration</h3><pre><code>${this.safeStringify(config)}</code></pre>`;

            if (el.debugContentQueries) this.renderQueries(queries, el.debugContentQueries);
            if (el.debugContentEvents) this.renderEvents(events, el.debugContentEvents, info);
            if (el.debugContentCache) this.renderCache(cache, el.debugInfoCache, el.debugContentCache);
            if (el.debugContentRoutes) this.renderRoutes(routes, el.debugContentRoutes);
            if (el.debugContentSession) this.renderSession(session, el.debugContentSession);
            if (el.debugContentCookies) this.renderCookies(cookies, el.debugContentCookies);
            if (el.debugContentAuth) this.renderAuth(auth, el.debugInfoAuth, el.debugContentAuth);
            if (el.debugContentExceptions) this.renderExceptions(exceptions, el.debugContentExceptions);

            // Dispatch browser events if any
            this.dispatchBrowserEvents(browser_events);
        },

        safeStringify(obj) {
            try {
                return JSON.stringify(obj, null, 2);
            } catch (err) {
                return String(obj);
            }
        },

        dispatchBrowserEvents(browserEvents) {
            if (!Array.isArray(browserEvents) || browserEvents.length === 0) {
                return;
            }
            browserEvents.forEach(event => {
                try {
                    console.log(`BaultDebugBar: Dispatching event '${event.event}'`, event.payload);
                    document.dispatchEvent(new CustomEvent(event.event, { detail: event.payload }));
                } catch (err) {
                    console.warn('BaultDebugBar: failed dispatch event', err);
                }
            });
        },

        renderQueries(queries, container) {
            container.innerHTML = '<h3>SQL Queries</h3>';

            if (queries && queries.length > 0) {
                const queryCounts = queries.reduce((acc, q) => {
                    const normalized = (q.sql || '').trim();
                    acc[normalized] = (acc[normalized] || 0) + 1;
                    return acc;
                }, {});
        
                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.id = 'debug-search-queries';
                searchInput.className = 'debug-search-input';
                searchInput.placeholder = 'Search queries (e.g., SELECT, users, DUPLICATE)...';
                container.appendChild(searchInput);

                const dl = document.createElement('dl');
                const fragment = document.createDocumentFragment();

                queries.forEach(q => {
                    const normalizedSql = (q.sql || '').trim();
                    const count = queryCounts[normalizedSql] || 0;
                    const duplicateBadge = count > 1 ? `<span class="debug-duplicate-badge">DUPLICATE x${count}</span>` : '';
                    dl.innerHTML += `<dt>[${q.duration_ms || 0}ms]${duplicateBadge}</dt><dd><pre><code>${this.escapeHtml(normalizedSql)}</code></pre></dd>`;
                });
                container.appendChild(dl);
            } else {
                container.innerHTML += '<p>No queries recorded.</p>';
            }
        },

        renderEvents(events, container, requestInfo) {
            let html = '<h3>Dispatched Events</h3>';

            // Timeline Chart
            if (events && events.length > 0 && requestInfo && requestInfo.start_time && requestInfo.duration_ms) {
                html += '<h4>Events Timeline</h4>';
                html += '<div class="debug-timeline-container">';
                const startTime = requestInfo.start_time;
                const totalDuration = requestInfo.duration_ms;

                events.forEach((event, index) => {
                    if (!event.timestamp) return;
                    const timeOffset = (event.timestamp - startTime) * 1000; // in ms (assumes timestamp in seconds)
                    const leftPercentage = (timeOffset / totalDuration) * 100;
                    const targetId = `debug-event-item-${index}`;

                    if (leftPercentage >= 0 && leftPercentage <= 100) {
                        html += `
                            <div class="debug-timeline-event" data-target-id="${targetId}" style="left: ${leftPercentage.toFixed(2)}%;"><div class="debug-timeline-tooltip"><strong>${this.escapeHtml(event.name)}</strong><br>Time: ${timeOffset.toFixed(2)} ms</div></div>
                        `;
                    }
                });
                html += '</div>';
            }

            if (events && events.length > 0) {
                html += '<input type="text" id="debug-search-events" class="debug-search-input" placeholder="Search event names or payload...">';
                html += '<dl>';
                events.forEach((e, index) => {
                    const itemId = `debug-event-item-${index}`;
                    let payloadHtml = '';
                    if (e.payload && typeof e.payload === 'string') {
                        try {
                            const parsed = JSON.parse(e.payload);
                            payloadHtml = `<pre><code>${this.escapeHtml(JSON.stringify(parsed, null, 2))}</code></pre>`;
                        } catch (err) {
                            payloadHtml = `<pre><code>${this.escapeHtml(e.payload)}</code></pre>`;
                        }
                    } else if (e.payload) {
                        payloadHtml = `<pre><code>${this.escapeHtml(this.safeStringify(e.payload))}</code></pre>`;
                    } else {
                        payloadHtml = `<pre><code>Not available</code></pre>`;
                    }
                    html += `<dt id="${itemId}">${this.escapeHtml(e.name)}</dt><dd>${payloadHtml}</dd>`;
                });
                html += '</dl>';
            } else {
                html += '<p>No events recorded.</p>';
            }
            container.innerHTML = html;
        },

        renderCache(cacheData, headerEl, contentEl) {
            if (!headerEl || !contentEl) return;

            if (!cacheData) {
                headerEl.innerHTML = 'Cache: <b>—</b>';
                contentEl.innerHTML = '<h3>Cache Events</h3><p>No cache data available.</p>';
                return;
            }

            const { hits = 0, misses = 0, writes = 0, events = [] } = cacheData;
            headerEl.innerHTML = `Cache: <b>H:${hits} M:${misses} W:${writes}</b>`;

            let html = '<h3>Cache Events</h3>';
            html += '<input type="text" id="debug-search-cache" class="debug-search-input" placeholder="Search cache keys or event type (HIT, MISS)...">';

            if (events.length > 0) {
                html += '<dl>';
                events.forEach(c => {
                    const eventType = (c.event || 'default').toString().toUpperCase();
                    const badgeClass = `badge-${eventType.toLowerCase()}`;
                    const badge = `<span class="debug-cache-badge ${badgeClass}">${eventType}</span>`;
                    html += `<dt>${badge}${this.escapeHtml(c.key || '')}</dt>`;

                    let valueHtml = '<em>Value not shown.</em>';
                    if (c.value !== undefined && c.value !== null && eventType !== 'MISS' && eventType !== 'FORGET') {
                        const safeValue = typeof c.value === 'string' ? this.escapeHtml(c.value) : this.escapeHtml(this.safeStringify(c.value));
                        valueHtml = `<pre><code>${safeValue}</code></pre>`;
                    }
                    html += `<dd>${valueHtml}</dd>`;
                });
                html += '</dl>';
            } else {
                html += '<p>No cache events recorded for this request.</p>';
            }
            contentEl.innerHTML = html;
        },

        renderRoutes(routes, container) {
            let html = '<h3>Registered Routes</h3>';
            if (routes && routes.length > 0) {
                html += '<input type="text" id="debug-search-routes" class="debug-search-input" placeholder="Search routes by URI or action...">';
                html += '<dl>';
                routes.forEach(r => {
                    const methodClass = `badge-route-${(r.method || '').toString().toLowerCase()}`;
                    const methodBadge = `<span class="debug-cache-badge ${methodClass}">${this.escapeHtml(r.method || '')}</span>`;
                    html += `<dt>${methodBadge}${this.escapeHtml(r.uri || '')}</dt>`;
                    html += `<dd><pre><code>${this.escapeHtml(r.action || '')}</code></pre></dd>`;
                });
                html += '</dl>';
            } else {
                html += '<p>No routes to display.</p>';
            }
            container.innerHTML = html;
        },

        renderSession(session, container) {
            let html = '<h3>Session Data</h3>';
            if (session && Object.keys(session).length > 0) {
                html += '<input type="text" id="debug-search-session" class="debug-search-input" placeholder="Search session keys or values...">';
                html += '<dl>';
                for (const key in session) {
                    html += `<dt>${this.escapeHtml(key)}</dt>`;
                    const value = typeof session[key] === 'object' ? this.safeStringify(session[key]) : String(session[key]);
                    html += `<dd><pre><code>${this.escapeHtml(value)}</code></pre></dd>`;
                }
                html += '</dl>';
            } else {
                html += '<p>No session data for this request.</p>';
            }
            container.innerHTML = html;
        },

        renderCookies(cookies, container) {
            let html = '<h3>Cookie Data</h3>';
            if (cookies && Object.keys(cookies).length > 0) {
                html += '<input type="text" id="debug-search-cookies" class="debug-search-input" placeholder="Search cookie names or values...">';
                html += '<dl>';
                for (const key in cookies) {
                    html += `<dt>${this.escapeHtml(key)}</dt>`;
                    html += `<dd><pre><code>${this.escapeHtml(String(cookies[key]))}</code></pre></dd>`;
                }
                html += '</dl>';
            } else {
                html += '<p>No cookies for this request.</p>';
            }
            container.innerHTML = html;
        },

        renderAuth(authData, headerEl, contentEl) {
            if (!headerEl || !contentEl) return;

            headerEl.style.display = '';
            if (!authData) {
                headerEl.style.display = 'none';
                return;
            }

            if (authData.authenticated) {
                headerEl.innerHTML = `Auth: <b>ID ${this.escapeHtml(String(authData.id || ''))}</b>`;
                let html = '<h3>Authenticated User</h3>';
                html += '<input type="text" id="debug-search-auth" class="debug-search-input" placeholder="Search user attributes...">';
                html += '<dl>';
                html += `<dt>Guard</dt><dd><pre><code>${this.escapeHtml(String(authData.guard || ''))}</code></pre></dd>`;
                html += `<dt>User Class</dt><dd><pre><code>${this.escapeHtml(String(authData.class || ''))}</code></pre></dd>`;
                html += `<dt>User Data</dt><dd><pre><code>${this.escapeHtml(this.safeStringify(authData.user || {}))}</code></pre></dd>`;
                html += '</dl>';
                contentEl.innerHTML = html;
            } else {
                headerEl.innerHTML = `Auth: <b>Guest</b>`;
                contentEl.innerHTML = '<h3>Authentication</h3><p>No user is authenticated for this request.</p>';
            }
        },

        renderExceptions(exceptions, container) {
            let html = '<h3>Exceptions</h3>';
            if (exceptions && exceptions.length > 0) {
                html += '<input type="text" id="debug-search-exceptions" class="debug-search-input" placeholder="Search exception message, file...">';
                exceptions.forEach((ex) => {
                    html += `<div class="exception-item">`;
                    html += `<h4>${this.escapeHtml(ex.class || 'Exception')}</h4>`;
                    html += `<p><strong>Message:</strong> ${this.escapeHtml(ex.message || '')}</p>`;
                    html += `<p><strong>File:</strong> ${this.escapeHtml((ex.file || '') + ':' + (ex.line || ''))}</p>`;
                    html += `<details><summary>Stack Trace</summary><pre><code>${this.escapeHtml(ex.trace || '')}</code></pre></details>`;
                    html += `</div>`;
                });
            } else {
                html += '<p>No exceptions recorded for this request.</p>';
            }
            container.innerHTML = html;
        },

        setupSearchFilter(inputId, itemSelector) {
            const searchInput = document.getElementById(inputId);
            if (!searchInput) return;

            // Gán sự kiện một lần duy nhất
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const items = document.querySelectorAll(itemSelector);
                
                // Handle paired dt/dd items (assumes selector returns dt then dd pairs)
                if (itemSelector.includes('dl')) {
                    for (let i = 0; i < items.length; i += 2) {
                        const dt = items[i];
                        const dd = items[i + 1];
                        const combinedText = (dt ? dt.textContent : '') + (dd ? dd.textContent : '');
                        const isVisible = combinedText.toLowerCase().includes(searchTerm);
                        if (dt) dt.style.display = isVisible ? '' : 'none';
                        if (dd) dd.style.display = isVisible ? '' : 'none';
                    }
                } else {
                    items.forEach(item => {
                        const isVisible = item.textContent.toLowerCase().includes(searchTerm);
                        item.style.display = isVisible ? '' : 'none';
                    });
                }
            });
        },

        switchTab(tabId) {
            // hide all panels
            document.querySelectorAll('.debug-content-panel').forEach(panel => {
                panel.style.display = 'none';
            });
            // deactivate all tabs
            document.querySelectorAll('.debug-tab-link').forEach(link => {
                link.classList.remove('active');
            });

            const panel = document.getElementById(`debug-content-${tabId}`);
            const tab = document.querySelector(`[data-tab="${tabId}"]`);
            if (panel) panel.style.display = 'block';
            if (tab) tab.classList.add('active');
        },

        toggleContent() {
            const content = this.elements.debugBarContent;
            if (!content) return;
            // Toggle display
            content.style.display = (content.style.display && content.style.display !== 'none') ? 'none' : 'block';
        },

        openContent() {
            if (this.elements.debugBarContent) this.elements.debugBarContent.style.display = 'block';
        },

        initResize(e) {
            e.preventDefault();
            this.state.startY = e.clientY;
            this.state.startHeight = parseInt(document.defaultView.getComputedStyle(this.elements.debugBar).height, 10) || 200;
            
            // Bind `this` to ensure correct context in event handlers
            this.boundResize = this.resize.bind(this);
            this.boundStopResize = this.stopResize.bind(this);

            document.documentElement.addEventListener('mousemove', this.boundResize);
            document.documentElement.addEventListener('mouseup', this.boundStopResize);
        },

        resize(e) {
            const newHeight = this.state.startHeight - (e.clientY - this.state.startY);
            const minHeight = 30;
            const maxHeight = window.innerHeight * 0.9;

            if (this.elements.debugBar && newHeight > minHeight && newHeight < maxHeight) {
                this.elements.debugBar.style.height = newHeight + 'px';
            }
        },

        stopResize() {
            document.documentElement.removeEventListener('mousemove', this.boundResize);
            document.documentElement.removeEventListener('mouseup', this.boundStopResize);
        },

        scrollToAndHighlight(elementId) {
            const targetElement = document.getElementById(elementId);
            if (!targetElement) return;

            targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Thêm class highlight và xóa sau 2 giây
            targetElement.classList.add('debug-item-highlight');
            setTimeout(() => {
                targetElement.classList.remove('debug-item-highlight');
            }, 2000);
        },

        escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        },

        // --- WebSocket Real-time updates ---
        getWebSocketUrl() {
            // Lấy URL từ config, với giá trị mặc định.
            // Giá trị này được inject từ InjectDebugbarMiddleware
            return "{{ config('app.websocket_url', 'ws://127.0.0.1:9502') }}";
        },

        initWebSocket() {
            let heartbeatInterval = null;

            const connect = () => {
                const ws = new WebSocket(this.getWebSocketUrl());

                ws.onopen = () => {
                    console.log('BaultDebugBar: WebSocket connection established.');
                };

                ws.onopen = () => {
                    console.log('BaultDebugBar: WebSocket connection established.');
                    heartbeatInterval = setInterval(() => ws.send(JSON.stringify({ type: 'ping' })), 25000);
                };

                ws.onmessage = (event) => {
                    try {
                        this.handleWebSocketMessage(JSON.parse(event.data));
                    } catch (e) {
                        console.error('BaultDebugBar: Error handling WebSocket message.', e);
                    }
                };

                ws.onclose = (event) => {
                    console.log('BaultDebugBar: WebSocket connection closed. Reconnecting in 5 seconds...', event.reason);
                    clearInterval(heartbeatInterval);
                    setTimeout(connect, 5000);
                };

                ws.onerror = (error) => {
                    console.error('BaultDebugBar: WebSocket error.', error);
                };
            };

            connect();
        },

        handleWebSocketMessage(message) {
            // Handle pong message for heartbeat
            if (message.type === 'pong') {
                return;
            }

            // Handle debug_realtime messages
            if (message.type === 'debug_realtime') {
                const payload = message.payload;
                if (!payload || !payload.type) return;

                switch(payload.type) {
                    case 'query':
                        this.handleQueryUpdate(payload.data);
                        break;
                    case 'log':
                        this.handleLogUpdate(payload.data);
                        break;
                    case 'event':
                        this.handleEventUpdate(payload.data);
                        break;
                    case 'cache':
                        this.handleCacheUpdate(payload.data);
                        break;
                    case 'session':
                        this.handleSessionUpdate(payload.data);
                        break;
                    case 'cookie':
                        this.handleCookieUpdate(payload.data);
                        break;
                    case 'queue':
                        this.handleQueueUpdate(payload.data);
                        break;
                    case 'route':
                        this.handleRouteUpdate(payload.data);
                        break;
                    case 'metrics':
                        this.handleMetricsUpdate(payload.data);
                        break;
                }
                return;
            }

            // Handle legacy format
            const payload = message.payload;
            if (!payload || !payload.type) return;

            switch(payload.type) {
                case 'query':
                    this.handleQueryUpdate(payload.data);
                    break;
                case 'log':
                    this.handleLogUpdate(payload.data);
                    break;
                case 'event':
                    this.handleEventUpdate(payload.data);
                    break;
                case 'cache':
                    this.handleCacheUpdate(payload.data);
                    break;
            }
        },

        handleQueryUpdate(data) {
            const container = this.elements.debugContentQueries;
            if (!container) return;

            let dl = container.querySelector('dl');
            if (!dl) {
                dl = document.createElement('dl');
                container.appendChild(dl);
            }

            const dt = document.createElement('dt');
            dt.innerHTML = `[${data.duration_ms || 0}ms]`;
            const dd = document.createElement('dd');
            dd.innerHTML = `<pre><code>${this.escapeHtml((data.sql || '').trim())}</code></pre>`;
            
            dl.prepend(dd);
            dl.prepend(dt);
            dt.classList.add('debug-item-highlight');

            const count = container.querySelectorAll('dt').length;
            this.elements.debugInfoQueries.innerHTML = `Queries: <b>${count}</b>`;
        },

        handleLogUpdate(data) {
            console.log('BaultDebugBar [Log]:', data);
        },

        handleEventUpdate(data) {
            const container = this.elements.debugContentEvents;
            if (!container) return;

            let dl = container.querySelector('dl');
            if (!dl) {
                dl = document.createElement('dl');
                container.appendChild(dl);
            }

            const dt = document.createElement('dt');
            dt.textContent = data.name;
            const dd = document.createElement('dd');
            dd.innerHTML = `<pre><code>${this.escapeHtml(this.safeStringify(data.payload || {}))}</code></pre>`;

            dl.prepend(dd);
            dl.prepend(dt);
            dt.classList.add('debug-item-highlight');

            const count = container.querySelectorAll('dt').length;
            this.elements.debugInfoEvents.innerHTML = `Events: <b>${count}</b>`;
        },

        handleCacheUpdate(data) {
            const container = this.elements.debugContentCache;
            if (!container) return;

            let dl = container.querySelector('dl');
            if (!dl) {
                // Tạo mới container nếu chưa có
                container.innerHTML = '<h3>Cache Events</h3>';
                dl = document.createElement('dl');
                container.appendChild(dl);
            }

            const operation = (data.operation || 'UNKNOWN').toUpperCase();
            const badgeClass = `badge-${operation.toLowerCase()}`;
            const badge = `<span class="debug-cache-badge ${badgeClass}">${operation}</span>`;
            
            const dt = document.createElement('dt');
            dt.innerHTML = badge + this.escapeHtml(data.key || '');
            
            const dd = document.createElement('dd');
            if (data.value !== undefined && data.value !== null && operation !== 'MISS' && operation !== 'DELETE') {
                const safeValue = typeof data.value === 'string' 
                    ? this.escapeHtml(data.value) 
                    : this.escapeHtml(this.safeStringify(data.value));
                dd.innerHTML = `<pre><code>${safeValue}</code></pre>`;
            } else {
                dd.innerHTML = '<em>Value not shown.</em>';
            }
            
            dl.prepend(dd);
            dl.prepend(dt);
            dt.classList.add('debug-item-highlight');

            // Update badge counters (simplified)
            const cacheInfo = this.elements.debugInfoCache;
            if (cacheInfo) {
                const hits = dl.querySelectorAll('.badge-hit').length;
                const misses = dl.querySelectorAll('.badge-miss').length;
                const writes = dl.querySelectorAll('.badge-write').length;
                cacheInfo.innerHTML = `Cache: <b>H:${hits} M:${misses} W:${writes}</b>`;
            }
        },

        handleSessionUpdate(data) {
            const container = this.elements.debugContentSession;
            if (!container) return;

            let dl = container.querySelector('dl');
            if (!dl) {
                container.innerHTML = '<h3>Session Operations</h3>';
                dl = document.createElement('dl');
                container.appendChild(dl);
            }

            const operation = (data.operation || 'UNKNOWN').toUpperCase();
            const badge = `<span class="debug-badge badge-${operation === 'SET' || operation === 'FLASH' ? 'write' : operation === 'GET' ? 'hit' : 'forget'}">${operation}</span>`;
            
            const dt = document.createElement('dt');
            dt.innerHTML = badge + ' ' + this.escapeHtml(data.key || '');
            
            const dd = document.createElement('dd');
            if (data.value !== undefined && data.value !== null && operation !== 'REMOVE' && operation !== 'FORGET') {
                const safeValue = typeof data.value === 'string' 
                    ? this.escapeHtml(data.value) 
                    : this.escapeHtml(this.safeStringify(data.value));
                dd.innerHTML = `<pre><code>${safeValue}</code></pre>`;
            } else {
                dd.innerHTML = '<em>No value</em>';
            }
            
            dl.prepend(dd);
            dl.prepend(dt);
            dt.classList.add('debug-item-highlight');

            // Update counter
            const count = dl.querySelectorAll('dt').length;
            if (this.elements.debugInfoSession) {
                this.elements.debugInfoSession.innerHTML = `Session: <b>${count}</b>`;
            }
        },

        handleCookieUpdate(data) {
            const container = this.elements.debugContentCookies;
            if (!container) return;

            let dl = container.querySelector('dl');
            if (!dl) {
                container.innerHTML = '<h3>Cookie Operations</h3>';
                dl = document.createElement('dl');
                container.appendChild(dl);
            }

            const operation = (data.operation || 'UNKNOWN').toUpperCase();
            const badge = `<span class="debug-badge badge-${operation === 'QUEUE' ? 'write' : operation === 'EXPIRE' ? 'forget' : 'default'}">${operation}</span>`;
            
            const dt = document.createElement('dt');
            dt.innerHTML = badge + ' ' + this.escapeHtml(data.name || '');
            
            const dd = document.createElement('dd');
            if (data.value !== undefined && data.value !== null) {
                dd.innerHTML = `<pre><code>${this.escapeHtml(String(data.value))}</code></pre>`;
            } else if (data.options && Object.keys(data.options).length > 0) {
                dd.innerHTML = `<pre><code>${this.escapeHtml(this.safeStringify(data.options))}</code></pre>`;
            } else {
                dd.innerHTML = '<em>No data</em>';
            }
            
            dl.prepend(dd);
            dl.prepend(dt);
            dt.classList.add('debug-item-highlight');

            // Update counter
            const count = dl.querySelectorAll('dt').length;
            if (this.elements.debugInfoCookies) {
                this.elements.debugInfoCookies.innerHTML = `Cookies: <b>${count}</b>`;
            }
        },

        handleQueueUpdate(data) {
            // Add to events or create new queue tab
            console.log('BaultDebugBar [Queue]:', data);
            
            // For now, show in events
            const container = this.elements.debugContentEvents;
            if (!container) return;

            let dl = container.querySelector('dl');
            if (!dl) {
                dl = document.createElement('dl');
                container.appendChild(dl);
            }

            const dt = document.createElement('dt');
            dt.innerHTML = `<span class="debug-badge badge-info">QUEUE</span> ${this.escapeHtml(data.job || '')}`;
            
            const dd = document.createElement('dd');
            dd.innerHTML = `<pre><code>Queue: ${this.escapeHtml(data.queue || 'default')}\nData: ${this.escapeHtml(this.safeStringify(data.data || {}))}</code></pre>`;
            
            dl.prepend(dd);
            dl.prepend(dt);
            dt.classList.add('debug-item-highlight');
        },

        handleRouteUpdate(data) {
            // Update route info in header
            const routeInfo = document.getElementById('debug-route-info');
            if (routeInfo) {
                const methodClass = `badge-route-${(data.method || '').toLowerCase()}`;
                routeInfo.innerHTML = `<span class="debug-badge ${methodClass}">${this.escapeHtml(data.method || '')}</span> ${this.escapeHtml(data.uri || '')}`;
            }

            // Also show in routes tab
            const container = this.elements.debugContentRoutes;
            if (container && !container.querySelector('.current-route')) {
                const currentRoute = document.createElement('div');
                currentRoute.className = 'current-route';
                currentRoute.innerHTML = `
                    <h4 style="color: var(--color-primary);">Current Route</h4>
                    <dl>
                        <dt><span class="debug-badge ${`badge-route-${(data.method || '').toLowerCase()}`}">${this.escapeHtml(data.method || '')}</span>${this.escapeHtml(data.uri || '')}</dt>
                        <dd><pre><code>Action: ${this.escapeHtml(data.action || '')}\nMiddleware: ${this.escapeHtml((data.middleware || []).join(', '))}</code></pre></dd>
                    </dl>
                `;
                container.insertBefore(currentRoute, container.firstChild);
            }
        },

        handleMetricsUpdate(data) {
            // Update time and memory in header
            if (this.elements.debugInfoDuration) {
                this.elements.debugInfoDuration.innerHTML = `Time: <b>${data.time_ms || 0}ms</b>`;
            }
            if (this.elements.debugInfoMemory) {
                this.elements.debugInfoMemory.innerHTML = `Memory: <b>${data.memory_mb || 0} MB</b> (Peak: <b>${data.memory_peak_mb || 0} MB</b>)`;
            }
        }
    };

    BaultDebugBar.init();

    @php
        // Lấy request ID từ application container
        $requestId = app()->has('request_id') ? app('request_id') : null;
        
        // Lấy debug data nếu có (cho render ngay lập tức)
        $debugData = app()->has('debug_manager') ? app('debug_manager')->getData() : null;
    @endphp

    // Inject request ID vào debug bar
    if ('{{ $requestId }}') {
        BaultDebugBar.state.lastRequestId = '{{ $requestId }}';
        sessionStorage.setItem('bault-debug-last-id', '{{ $requestId }}');
        
        // Fetch debug data sau khi page load (sau khi middleware đã collect xong)
        setTimeout(() => {
            BaultDebugBar.fetchDebugData();
        }, 500);
    }

    try {
        const initialDebugData = {!! json_encode($debugData) !!};
        if (initialDebugData) {
            // Chỉ dispatch nếu có dữ liệu thực sự, tránh ghi đè dữ liệu từ sessionStorage
            if (initialDebugData.info && initialDebugData.info.id) {
                document.dispatchEvent(new CustomEvent('bault:debug-data-updated', { detail: initialDebugData }));
            }
        }
    } catch (err) {
        console.warn('BaultDebugBar: initial debug data invalid', err);
    }
});
</script>
@endif
