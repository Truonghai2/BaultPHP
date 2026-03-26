/**
 * Performance Monitoring Dashboard v3.0
 * 
 * Features:
 * - Real-time performance metrics
 * - Core Web Vitals tracking
 * - Resource timing
 * - Long tasks detection
 * - Memory usage
 * - Network performance
 */

export class PerformanceMonitor {
  constructor(options = {}) {
    this.options = {
      enabled: options.enabled !== false,
      sampleRate: options.sampleRate || 1, // 100% by default
      reportInterval: options.reportInterval || 30000, // 30 seconds
      endpoint: options.endpoint || '/api/metrics',
      enableReporting: options.enableReporting !== false,
      ...options
    };

    this.metrics = {
      navigation: {},
      resources: [],
      marks: new Map(),
      measures: new Map(),
      vitals: {},
      longTasks: [],
      memory: {},
      errors: [],
      customMetrics: new Map()
    };

    this.observers = new Map();
    this.reportTimer = null;

    if (this.options.enabled && this.shouldSample()) {
      this.init();
    }
  }

  /**
   * Initialize performance monitoring
   */
  init() {
    // Navigation Timing
    this.captureNavigationTiming();

    // Resource Timing
    this.observeResources();

    // Core Web Vitals
    this.observeWebVitals();

    // Long Tasks
    this.observeLongTasks();

    // Memory Usage
    this.observeMemory();

    // Layout Shifts
    this.observeLayoutShifts();

    // Start periodic reporting
    if (this.options.enableReporting) {
      this.startReporting();
    }

    console.log('Performance monitoring initialized');
  }

  /**
   * Capture Navigation Timing
   */
  captureNavigationTiming() {
    if (!performance.timing) return;

    window.addEventListener('load', () => {
      const timing = performance.timing;
      
      this.metrics.navigation = {
        // Page load metrics
        domContentLoaded: timing.domContentLoadedEventEnd - timing.navigationStart,
        loadComplete: timing.loadEventEnd - timing.navigationStart,
        
        // Network metrics
        dnsLookup: timing.domainLookupEnd - timing.domainLookupStart,
        tcpConnection: timing.connectEnd - timing.connectStart,
        tlsHandshake: timing.connectEnd - timing.secureConnectionStart,
        requestTime: timing.responseStart - timing.requestStart,
        responseTime: timing.responseEnd - timing.responseStart,
        
        // Processing metrics
        domInteractive: timing.domInteractive - timing.navigationStart,
        domProcessing: timing.domComplete - timing.domLoading,
        
        // Total time
        timeToFirstByte: timing.responseStart - timing.navigationStart,
        pageLoadTime: timing.loadEventEnd - timing.fetchStart,
        
        timestamp: Date.now()
      };

      this.emit('navigation', this.metrics.navigation);
    });
  }

  /**
   * Observe resources
   */
  observeResources() {
    if (!PerformanceObserver) return;

    const observer = new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        this.metrics.resources.push({
          name: entry.name,
          type: entry.initiatorType,
          duration: entry.duration,
          size: entry.transferSize || 0,
          cached: entry.transferSize === 0 && entry.decodedBodySize > 0,
          timestamp: entry.startTime
        });
      }
    });

    observer.observe({ entryTypes: ['resource'] });
    this.observers.set('resource', observer);
  }

  /**
   * Observe Web Vitals
   */
  observeWebVitals() {
    if (!PerformanceObserver) return;

    // Largest Contentful Paint (LCP)
    const lcpObserver = new PerformanceObserver((list) => {
      const entries = list.getEntries();
      const lastEntry = entries[entries.length - 1];
      
      this.metrics.vitals.lcp = {
        value: lastEntry.startTime,
        rating: this.rateLCP(lastEntry.startTime),
        element: lastEntry.element?.tagName || 'unknown',
        timestamp: Date.now()
      };

      this.emit('lcp', this.metrics.vitals.lcp);
    });

    try {
      lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
      this.observers.set('lcp', lcpObserver);
    } catch (e) {
      console.warn('LCP observation not supported');
    }

    // First Input Delay (FID)
    const fidObserver = new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        this.metrics.vitals.fid = {
          value: entry.processingStart - entry.startTime,
          rating: this.rateFID(entry.processingStart - entry.startTime),
          timestamp: Date.now()
        };

        this.emit('fid', this.metrics.vitals.fid);
      }
    });

    try {
      fidObserver.observe({ entryTypes: ['first-input'] });
      this.observers.set('fid', fidObserver);
    } catch (e) {
      console.warn('FID observation not supported');
    }

    // Cumulative Layout Shift (CLS) is observed separately
  }

  /**
   * Observe long tasks (> 50ms)
   */
  observeLongTasks() {
    if (!PerformanceObserver) return;

    try {
      const observer = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          this.metrics.longTasks.push({
            duration: entry.duration,
            startTime: entry.startTime,
            attribution: entry.attribution?.[0]?.name || 'unknown',
            timestamp: Date.now()
          });

          if (entry.duration > 100) {
            console.warn(`Long task detected: ${entry.duration.toFixed(2)}ms`);
          }

          this.emit('longtask', {
            duration: entry.duration,
            startTime: entry.startTime
          });
        }
      });

      observer.observe({ entryTypes: ['longtask'] });
      this.observers.set('longtask', observer);
    } catch (e) {
      console.warn('Long task observation not supported');
    }
  }

  /**
   * Observe memory usage
   */
  observeMemory() {
    if (!performance.memory) return;

    const captureMemory = () => {
      this.metrics.memory = {
        usedJSHeapSize: performance.memory.usedJSHeapSize,
        totalJSHeapSize: performance.memory.totalJSHeapSize,
        jsHeapSizeLimit: performance.memory.jsHeapSizeLimit,
        usage: (performance.memory.usedJSHeapSize / performance.memory.jsHeapSizeLimit) * 100,
        timestamp: Date.now()
      };

      this.emit('memory', this.metrics.memory);
    };

    // Capture every 10 seconds
    setInterval(captureMemory, 10000);
    captureMemory();
  }

  /**
   * Observe layout shifts for CLS
   */
  observeLayoutShifts() {
    if (!PerformanceObserver) return;

    let clsValue = 0;

    try {
      const observer = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (!entry.hadRecentInput) {
            clsValue += entry.value;
            
            this.metrics.vitals.cls = {
              value: clsValue,
              rating: this.rateCLS(clsValue),
              timestamp: Date.now()
            };

            this.emit('cls', this.metrics.vitals.cls);
          }
        }
      });

      observer.observe({ entryTypes: ['layout-shift'] });
      this.observers.set('layout-shift', observer);
    } catch (e) {
      console.warn('Layout shift observation not supported');
    }
  }

  /**
   * Rate Web Vitals
   */
  rateLCP(value) {
    return value <= 2500 ? 'good' : value <= 4000 ? 'needs-improvement' : 'poor';
  }

  rateFID(value) {
    return value <= 100 ? 'good' : value <= 300 ? 'needs-improvement' : 'poor';
  }

  rateCLS(value) {
    return value <= 0.1 ? 'good' : value <= 0.25 ? 'needs-improvement' : 'poor';
  }

  /**
   * Custom performance marks
   */
  mark(name) {
    performance.mark(name);
    this.metrics.marks.set(name, performance.now());
  }

  /**
   * Custom performance measures
   */
  measure(name, startMark, endMark) {
    try {
      performance.measure(name, startMark, endMark);
      
      const entries = performance.getEntriesByName(name, 'measure');
      if (entries.length > 0) {
        const duration = entries[entries.length - 1].duration;
        this.metrics.measures.set(name, duration);
        
        this.emit('measure', { name, duration });
        
        return duration;
      }
    } catch (e) {
      console.error('Measure error:', e);
    }
    
    return null;
  }

  /**
   * Track custom metric
   */
  trackMetric(name, value, metadata = {}) {
    this.metrics.customMetrics.set(name, {
      value,
      metadata,
      timestamp: Date.now()
    });

    this.emit('metric', { name, value, metadata });
  }

  /**
   * Get all metrics
   */
  getMetrics() {
    return {
      ...this.metrics,
      marks: Object.fromEntries(this.metrics.marks),
      measures: Object.fromEntries(this.metrics.measures),
      customMetrics: Object.fromEntries(this.metrics.customMetrics)
    };
  }

  /**
   * Get summary
   */
  getSummary() {
    const metrics = this.getMetrics();
    
    return {
      performance: {
        score: this.calculatePerformanceScore(),
        ttfb: metrics.navigation.timeToFirstByte,
        fcp: this.getFCP(),
        lcp: metrics.vitals.lcp?.value,
        fid: metrics.vitals.fid?.value,
        cls: metrics.vitals.cls?.value,
        rating: {
          lcp: metrics.vitals.lcp?.rating,
          fid: metrics.vitals.fid?.rating,
          cls: metrics.vitals.cls?.rating
        }
      },
      resources: {
        total: metrics.resources.length,
        cached: metrics.resources.filter(r => r.cached).length,
        totalSize: metrics.resources.reduce((sum, r) => sum + r.size, 0),
        slowest: metrics.resources.sort((a, b) => b.duration - a.duration)[0]
      },
      issues: {
        longTasks: metrics.longTasks.length,
        errors: metrics.errors.length
      },
      memory: metrics.memory
    };
  }

  /**
   * Calculate performance score (0-100)
   */
  calculatePerformanceScore() {
    const vitals = this.metrics.vitals;
    let score = 100;

    // LCP (25%)
    if (vitals.lcp) {
      if (vitals.lcp.rating === 'poor') score -= 25;
      else if (vitals.lcp.rating === 'needs-improvement') score -= 12;
    }

    // FID (25%)
    if (vitals.fid) {
      if (vitals.fid.rating === 'poor') score -= 25;
      else if (vitals.fid.rating === 'needs-improvement') score -= 12;
    }

    // CLS (25%)
    if (vitals.cls) {
      if (vitals.cls.rating === 'poor') score -= 25;
      else if (vitals.cls.rating === 'needs-improvement') score -= 12;
    }

    // Long tasks (25%)
    const longTaskCount = this.metrics.longTasks.length;
    if (longTaskCount > 10) score -= 25;
    else if (longTaskCount > 5) score -= 12;

    return Math.max(0, score);
  }

  /**
   * Get First Contentful Paint
   */
  getFCP() {
    const fcpEntry = performance.getEntriesByName('first-contentful-paint')[0];
    return fcpEntry ? fcpEntry.startTime : null;
  }

  /**
   * Start periodic reporting
   */
  startReporting() {
    this.reportTimer = setInterval(() => {
      this.report();
    }, this.options.reportInterval);
  }

  /**
   * Stop reporting
   */
  stopReporting() {
    if (this.reportTimer) {
      clearInterval(this.reportTimer);
      this.reportTimer = null;
    }
  }

  /**
   * Report metrics to server
   */
  async report() {
    if (!this.options.endpoint) return;

    try {
      const summary = this.getSummary();
      
      await fetch(this.options.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          url: window.location.href,
          userAgent: navigator.userAgent,
          timestamp: Date.now(),
          metrics: summary
        })
      });

      console.log('Performance metrics reported');
    } catch (error) {
      console.error('Failed to report metrics:', error);
    }
  }

  /**
   * Check if should sample
   */
  shouldSample() {
    return Math.random() < this.options.sampleRate;
  }

  /**
   * Event emitter
   */
  on(event, callback) {
    if (!this._events) this._events = {};
    if (!this._events[event]) this._events[event] = [];
    this._events[event].push(callback);
  }

  emit(event, data) {
    if (!this._events || !this._events[event]) return;
    this._events[event].forEach(callback => callback(data));
  }

  /**
   * Cleanup
   */
  destroy() {
    this.stopReporting();
    
    this.observers.forEach(observer => observer.disconnect());
    this.observers.clear();
    
    this.metrics = {
      navigation: {},
      resources: [],
      marks: new Map(),
      measures: new Map(),
      vitals: {},
      longTasks: [],
      memory: {},
      errors: [],
      customMetrics: new Map()
    };
  }

  /**
   * Create dashboard UI
   */
  createDashboard() {
    const container = document.createElement('div');
    container.id = 'performance-dashboard';
    container.style.cssText = `
      position: fixed;
      bottom: 20px;
      left: 20px;
      background: rgba(0, 0, 0, 0.9);
      color: white;
      padding: 16px;
      border-radius: 8px;
      font-family: monospace;
      font-size: 12px;
      z-index: 999999;
      max-width: 400px;
      max-height: 500px;
      overflow-y: auto;
    `;

    const update = () => {
      const summary = this.getSummary();
      const score = summary.performance.score;
      
      container.innerHTML = `
        <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
          <strong style="font-size: 14px;">Performance Monitor</strong>
          <button id="close-dashboard" style="background: transparent; border: none; color: white; cursor: pointer; font-size: 16px;">×</button>
        </div>
        
        <div style="margin-bottom: 12px;">
          <div style="font-weight: bold;">Score: ${score}/100</div>
          <div style="background: #333; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 4px;">
            <div style="background: ${score > 80 ? '#4caf50' : score > 50 ? '#ff9800' : '#f44336'}; height: 100%; width: ${score}%;"></div>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;">
          <div>
            <div style="opacity: 0.7;">LCP</div>
            <div style="color: ${this.getRatingColor(summary.performance.rating.lcp)}">${summary.performance.lcp ? (summary.performance.lcp / 1000).toFixed(2) + 's' : 'N/A'}</div>
          </div>
          <div>
            <div style="opacity: 0.7;">FID</div>
            <div style="color: ${this.getRatingColor(summary.performance.rating.fid)}">${summary.performance.fid ? summary.performance.fid.toFixed(0) + 'ms' : 'N/A'}</div>
          </div>
          <div>
            <div style="opacity: 0.7;">CLS</div>
            <div style="color: ${this.getRatingColor(summary.performance.rating.cls)}">${summary.performance.cls ? summary.performance.cls.toFixed(3) : 'N/A'}</div>
          </div>
          <div>
            <div style="opacity: 0.7;">TTFB</div>
            <div>${summary.performance.ttfb ? (summary.performance.ttfb / 1000).toFixed(2) + 's' : 'N/A'}</div>
          </div>
        </div>

        <div style="margin-bottom: 12px;">
          <div style="opacity: 0.7; margin-bottom: 4px;">Resources</div>
          <div>Total: ${summary.resources.total} (${(summary.resources.totalSize / 1024).toFixed(0)} KB)</div>
          <div>Cached: ${summary.resources.cached}</div>
        </div>

        <div>
          <div style="opacity: 0.7; margin-bottom: 4px;">Issues</div>
          <div>Long Tasks: ${summary.issues.longTasks}</div>
          <div>Errors: ${summary.issues.errors}</div>
        </div>
      `;

      const closeBtn = container.querySelector('#close-dashboard');
      closeBtn.addEventListener('click', () => container.remove());
    };

    update();
    setInterval(update, 1000);

    document.body.appendChild(container);
  }

  /**
   * Get rating color
   */
  getRatingColor(rating) {
    switch (rating) {
      case 'good': return '#4caf50';
      case 'needs-improvement': return '#ff9800';
      case 'poor': return '#f44336';
      default: return '#ffffff';
    }
  }
}

// Export singleton instance
export const performanceMonitor = new PerformanceMonitor();

export default performanceMonitor;
