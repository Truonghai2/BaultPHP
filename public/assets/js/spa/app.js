/**
 * BaultSPA v3.0 - Main Application
 * 
 * Modern SPA Framework with:
 * - Router with lazy loading
 * - State management
 * - Component system
 * - Animations
 * - Lazy image loading
 * - Service Worker
 * - SEO optimization
 */

import { animator } from './animations.js';
import { Component } from './component.js';
import { lazyLoader } from './lazy-load.js';
import { Router } from './router.js';
import { seo } from './seo.js';
import { swManager } from './service-worker-manager.js';
import { createStore, loggerMiddleware, performanceMiddleware } from './store.js';

export class BaultApp {
  constructor(options = {}) {
    this.options = options;
    this.router = null;
    this.store = null;
    this.components = new Map();
    this.isInitialized = false;
    
    // Performance tracking
    this.performanceMetrics = {
      initTime: 0,
      firstPaint: 0,
      firstContentfulPaint: 0,
      largestContentfulPaint: 0,
      timeToInteractive: 0,
    };
  }

  /**
   * Initialize application
   */
  async init() {
    const startTime = performance.now();

    console.log('🚀 Initializing BaultSPA v3.0...');

    try {
      // Initialize store
      await this.initStore();

      // Initialize router
      await this.initRouter();

      // Initialize lazy loader
      await this.initLazyLoader();

      // Initialize service worker
      if (this.options.enableServiceWorker !== false) {
        await this.initServiceWorker();
      }

      // Initialize SEO
      await this.initSEO();

      // Setup global event listeners
      this.setupGlobalListeners();

      // Track performance
      this.trackPerformance();

      this.isInitialized = true;
      this.performanceMetrics.initTime = performance.now() - startTime;

      console.log(`✅ BaultSPA initialized in ${this.performanceMetrics.initTime.toFixed(2)}ms`);

      // Emit ready event
      window.dispatchEvent(new CustomEvent('app:ready', {
        detail: { metrics: this.performanceMetrics }
      }));

    } catch (error) {
      console.error('❌ Failed to initialize BaultSPA:', error);
      throw error;
    }

    return this;
  }

  /**
   * Initialize state store
   */
  async initStore() {
    const storeOptions = this.options.store || {};

    this.store = createStore({
      state: storeOptions.state || {},
      mutations: storeOptions.mutations || {},
      actions: storeOptions.actions || {},
      modules: storeOptions.modules || {},
      persist: storeOptions.persist || null,
      strict: storeOptions.strict !== false,
    });

    // Add middlewares in development
    if (this.options.debug) {
      this.store.use(loggerMiddleware);
      this.store.use(performanceMiddleware);
      this.store.enableDevTools();
    }

    // Make store globally available
    window.$store = this.store;

    console.log('✓ Store initialized');
  }

  /**
   * Initialize router
   */
  async initRouter() {
    const routerOptions = this.options.router || {};

    this.router = new Router({
      baseUrl: routerOptions.baseUrl || '',
      mode: routerOptions.mode || 'history',
      transition: routerOptions.transition || 'fade',
      beforeEach: routerOptions.beforeEach || null,
      afterEach: routerOptions.afterEach || null,
      onNotFound: routerOptions.onNotFound || null,
    });

    // Register routes
    if (routerOptions.routes) {
      routerOptions.routes.forEach(route => {
        this.router.route(route.path, route);
      });
    }

    // Register route groups
    if (routerOptions.groups) {
      routerOptions.groups.forEach(group => {
        this.router.group(group.prefix, (router) => {
          group.routes.forEach(route => {
            router.route(route.path, route);
          });
        }, group.options);
      });
    }

    // Register guards
    if (routerOptions.guards) {
      Object.entries(routerOptions.guards).forEach(([name, handler]) => {
        this.router.guard(name, handler);
      });
    }

    // Make router globally available
    window.$router = this.router;

    // Initialize router
    this.router.init();

    console.log('✓ Router initialized');
  }

  /**
   * Initialize lazy loader
   */
  async initLazyLoader() {
    const lazyOptions = this.options.lazyLoad || {};

    // Lazy loader is already initialized as singleton
    // Just observe new images
    lazyLoader.observeImages();

    console.log('✓ Lazy loader initialized');
  }

  /**
   * Initialize service worker
   */
  async initServiceWorker() {
    const swOptions = this.options.serviceWorker || {};

    const registered = await swManager.register();

    if (registered) {
      console.log('✓ Service Worker registered');

      // Precache critical resources
      if (swOptions.precache) {
        await swManager.precache(swOptions.precache);
      }
    }
  }

  /**
   * Initialize SEO
   */
  async initSEO() {
    const seoOptions = this.options.seo || {};

    // Set default meta
    if (seoOptions.default) {
      seo.updateMeta(seoOptions.default);
    }

    // Add structured data
    if (seoOptions.structuredData) {
      seo.addStructuredData(seoOptions.structuredData);
    }

    console.log('✓ SEO initialized');
  }

  /**
   * Setup global event listeners
   */
  setupGlobalListeners() {
    // Router navigation events
    window.addEventListener('router:navigated', (event) => {
      const { route } = event.detail;

      // Update SEO
      if (route.meta) {
        seo.updateMeta({
          title: route.meta.title,
          description: route.meta.description,
          url: window.location.href,
          ...route.meta
        });
      }

      // Track page view
      seo.trackPageView(window.location.href, document.title);

      // Re-observe lazy images
      lazyLoader.observeImages();
    });

    // Store mutation events
    this.store.on('mutation', (data) => {
      console.log('Store mutation:', data);
    });

    // Handle errors
    window.addEventListener('error', (event) => {
      this.handleError(event.error);
    });

    window.addEventListener('unhandledrejection', (event) => {
      this.handleError(event.reason);
    });
  }

  /**
   * Track performance metrics
   */
  trackPerformance() {
    // Get Web Vitals
    if ('PerformanceObserver' in window) {
      // First Paint
      const paintObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (entry.name === 'first-paint') {
            this.performanceMetrics.firstPaint = entry.startTime;
          }
          if (entry.name === 'first-contentful-paint') {
            this.performanceMetrics.firstContentfulPaint = entry.startTime;
          }
        }
      });
      paintObserver.observe({ entryTypes: ['paint'] });

      // Largest Contentful Paint
      const lcpObserver = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        const lastEntry = entries[entries.length - 1];
        this.performanceMetrics.largestContentfulPaint = lastEntry.startTime;
      });
      lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });

      // Time to Interactive (approximate)
      if (document.readyState === 'complete') {
        this.performanceMetrics.timeToInteractive = performance.now();
      } else {
        window.addEventListener('load', () => {
          this.performanceMetrics.timeToInteractive = performance.now();
        });
      }
    }
  }

  /**
   * Register component
   */
  component(name, component) {
    this.components.set(name, component);
    return this;
  }

  /**
   * Get component
   */
  getComponent(name) {
    return this.components.get(name);
  }

  /**
   * Handle errors
   */
  handleError(error) {
    console.error('Application error:', error);

    // Emit error event
    window.dispatchEvent(new CustomEvent('app:error', {
      detail: { error }
    }));

    // Show error notification in development
    if (this.options.debug) {
      this.showErrorNotification(error);
    }
  }

  /**
   * Show error notification
   */
  showErrorNotification(error) {
    const notification = document.createElement('div');
    notification.className = 'app-error-notification';
    notification.innerHTML = `
      <div class="error-content">
        <strong>Error:</strong>
        <p>${error.message || error}</p>
        <button class="error-close">×</button>
      </div>
    `;

    document.body.appendChild(notification);

    notification.querySelector('.error-close').addEventListener('click', () => {
      notification.remove();
    });

    setTimeout(() => {
      notification.remove();
    }, 10000);
  }

  /**
   * Get performance metrics
   */
  getMetrics() {
    return { ...this.performanceMetrics };
  }

  /**
   * Destroy application
   */
  destroy() {
    // Destroy router
    if (this.router) {
      this.router = null;
    }

    // Destroy store
    if (this.store) {
      this.store.reset();
      this.store = null;
    }

    // Destroy lazy loader
    lazyLoader.destroy();

    // Unregister service worker
    if (this.options.enableServiceWorker !== false) {
      swManager.unregister();
    }

    this.isInitialized = false;
  }
}

/**
 * Create application instance
 */
export function createApp(options) {
  return new BaultApp(options);
}

// Export all modules
export {
    animator, Component, createStore, lazyLoader, Router, seo, swManager
};

// Make available globally
window.BaultApp = BaultApp;
window.createApp = createApp;

export default { BaultApp, createApp };
