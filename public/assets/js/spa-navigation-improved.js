/**
 * BaultSPA v2.0: Enhanced SPA navigation helper for BaultPHP.
 *
 * Improvements:
 * - Network status checking
 * - CSRF token auto-refresh
 * - Better error handling with retry
 * - Progress indicator
 * - Optimized prefetching
 * - bfcache support
 * - Better memory management
 */

import { progress } from "./spa-progress.js";
import {
  CsrfTokenManager,
  debounce,
  fetchWithRetry,
  fetchWithTimeout,
  isMobileDevice,
  isSlowConnection,
  NetworkStatus,
  PerformanceMonitor,
  showNotification,
} from "./spa-utils.js";

const BaultSPA = {
  // --- CONFIGURATION ---
  contentSelectors: ["#app-content", ".admin-main"],
  ignoreSelector:
    '[data-no-spa], [target="_blank"], a[href^="#"], a[href$=".pdf"], a[href$=".zip"], form[method="POST"]',

  // Adaptive cache size based on device
  get cacheSize() {
    return isMobileDevice() ? 20 : 50;
  },

  // --- STATE ---
  pageCache: null,
  prefetched: new Set(),
  currentContentSelector: null,
  performanceMonitor: new PerformanceMonitor(),
  isNavigating: false,

  /**
   * LRU Cache implementation with size management
   */
  createLRUCache(capacity) {
    const cache = new Map();
    return {
      has: (key) => cache.has(key),
      get: (key) => {
        if (!cache.has(key)) return undefined;
        const value = cache.get(key);
        cache.delete(key);
        cache.set(key, value);
        return value;
      },
      set: (key, value) => {
        if (cache.has(key)) cache.delete(key);
        else if (cache.size >= capacity) {
          const oldestKey = cache.keys().next().value;
          cache.delete(oldestKey);
        }
        cache.set(key, value);
      },
      delete: (key) => cache.delete(key),
      clear: () => cache.clear(),
      size: () => cache.size,
    };
  },

  init() {
    this.currentContentSelector = this.detectContentSelector();

    if (!this.currentContentSelector) {
      console.warn(
        `BaultSPA: No content container found. SPA navigation disabled.`,
      );
      return;
    }

    const contentContainer = document.querySelector(
      this.currentContentSelector,
    );
    if (!contentContainer) {
      console.warn(
        `BaultSPA: Content container "${this.currentContentSelector}" not found.`,
      );
      return;
    }

    // Initialize cache
    this.pageCache = this.createLRUCache(this.cacheSize);

    // Setup event listeners
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => {
        this.initializeFirstPage(contentContainer);
      });
    } else {
      this.initializeFirstPage(contentContainer);
    }

    this.setupEventListeners();
    this.setupNetworkMonitoring();
    this.setupBfcacheSupport();

    console.log("BaultSPA v2.0 initialized with enhanced features.");
  },

  /**
   * Setup all event listeners
   */
  setupEventListeners() {
    document.addEventListener("click", this.handleLinkClick.bind(this));

    // Throttled/debounced hover handlers based on connection
    const hoverHandler = isSlowConnection()
      ? debounce(this.handleLinkHover.bind(this), 500)
      : this.handleLinkHover.bind(this);

    document.addEventListener("mouseover", hoverHandler);
    document.addEventListener("touchstart", hoverHandler, { passive: true });

    window.addEventListener("popstate", this.handlePopState.bind(this));
    document.addEventListener("submit", this.handleFormSubmit.bind(this));
  },

  /**
   * Setup network status monitoring
   */
  setupNetworkMonitoring() {
    window.addEventListener("online", () => {
      showNotification("Connection restored", "success", 2000);
    });

    window.addEventListener("offline", () => {
      showNotification(
        "You are offline. Some features may not work.",
        "warning",
        5000,
      );
    });
  },

  /**
   * Setup bfcache (back/forward cache) support
   */
  setupBfcacheSupport() {
    // Handle page restoration from bfcache
    window.addEventListener("pageshow", (event) => {
      if (event.persisted) {
        // Page was restored from bfcache
        console.log("BaultSPA: Page restored from bfcache");

        // Refresh CSRF token
        this.refreshCsrfToken();

        // Re-initialize if needed
        document.dispatchEvent(
          new CustomEvent("bault:restored", { bubbles: true }),
        );
      }
    });

    // Prepare page for bfcache
    window.addEventListener("pagehide", (event) => {
      if (event.persisted) {
        console.log("BaultSPA: Page being cached");
      }
    });
  },

  detectContentSelector() {
    for (const selector of this.contentSelectors) {
      if (document.querySelector(selector)) {
        return selector;
      }
    }
    return null;
  },

  getContentContainer() {
    return document.querySelector(this.currentContentSelector);
  },

  initializeFirstPage(contentContainer) {
    if (contentContainer.innerHTML.trim()) {
      this.pageCache.set(window.location.href, {
        content: contentContainer.innerHTML,
        title: document.title,
        layout: this.currentContentSelector,
        timestamp: Date.now(),
      });
      history.replaceState(
        {
          path: window.location.href,
          layout: this.currentContentSelector,
          timestamp: Date.now(),
        },
        document.title,
        window.location.href,
      );
    } else {
      this.fetchAndUpdatePage(window.location.href, contentContainer, false);
    }
  },

  /**
   * Refresh CSRF token from server
   */
  async refreshCsrfToken() {
    try {
      const response = await fetch(window.location.href, {
        method: "GET",
        headers: { "X-SPA-NAVIGATE": "true" },
        credentials: "include",
      });

      if (response.ok) {
        const html = await response.text();
        const newToken = CsrfTokenManager.extractTokenFromHtml(html);
        if (newToken) {
          CsrfTokenManager.updateToken(newToken);
          console.log("BaultSPA: CSRF token refreshed");
        }
      }
    } catch (error) {
      console.warn("BaultSPA: Failed to refresh CSRF token", error);
    }
  },

  /**
   * Enhanced fetch with error handling and retry
   */
  async fetchAndUpdatePage(url, contentContainer, pushState = true) {
    const timing = this.performanceMonitor.startTiming("page-fetch");

    try {
      // Check network status first
      if (!NetworkStatus.isOnline()) {
        showNotification(
          "No internet connection. Please check your network.",
          "error",
          5000,
        );
        return false;
      }

      // Fetch with retry and timeout
      const response = await fetchWithRetry(
        url,
        {
          headers: {
            "X-SPA-NAVIGATE": "true",
            "X-Requested-With": "XMLHttpRequest",
            Accept: "text/html, application/xhtml+xml, application/xml;q=0.9",
          },
          credentials: "include",
        },
        2,
        1000,
      );

      // Handle errors
      if (!response.ok) {
        if (response.status === 500) {
          const errorHtml = await response.text();
          if (
            errorHtml.trim().toLowerCase().startsWith("<!doctype html") ||
            errorHtml.trim().toLowerCase().startsWith("<html")
          ) {
            document.documentElement.innerHTML = errorHtml;
          }
          return false;
        }

        if (response.status === 404) {
          showNotification("Page not found", "error", 3000);
          return false;
        }

        if (response.status >= 400) {
          showNotification(
            "An error occurred while loading the page",
            "error",
            3000,
          );
          return false;
        }

        throw new Error(`HTTP ${response.status}`);
      }

      const newPageHtml = await response.text();
      const tempDoc = new DOMParser().parseFromString(newPageHtml, "text/html");

      // Extract CSRF token and update
      const newToken = CsrfTokenManager.extractTokenFromHtml(newPageHtml);
      if (newToken) {
        CsrfTokenManager.updateToken(newToken);
      }

      // Detect content selector in the new page
      let newContentSelector = null;
      let newContent = null;

      for (const selector of this.contentSelectors) {
        const content = tempDoc.querySelector(selector)?.innerHTML;
        if (content) {
          newContentSelector = selector;
          newContent = content;
          break;
        }
      }

      if (!newContent) {
        throw new Error("No content found in response");
      }

      const newTitle =
        tempDoc.querySelector("title")?.innerText || document.title;

      // Handle layout switching
      if (newContentSelector !== this.currentContentSelector) {
        console.log(
          `BaultSPA: Layout switch detected (${this.currentContentSelector} → ${newContentSelector}). Performing full page load...`,
        );
        window.location.href = url;
        return false;
      }

      // Update content
      contentContainer.innerHTML = newContent;
      document.title = newTitle;

      if (pushState) {
        history.pushState(
          {
            path: url,
            layout: newContentSelector,
            timestamp: Date.now(),
          },
          newTitle,
          url,
        );
      }

      // Cache the page
      this.pageCache.set(url, {
        content: newContent,
        title: newTitle,
        layout: newContentSelector,
        timestamp: Date.now(),
      });

      // Execute scripts
      await this.executeScripts(contentContainer);

      // Dispatch navigation event
      document.dispatchEvent(
        new CustomEvent("bault:navigated", {
          bubbles: true,
          detail: { url, layout: newContentSelector },
        }),
      );

      const duration = timing.end();
      console.log(`BaultSPA: Page loaded in ${duration.toFixed(2)}ms`);

      return true;
    } catch (error) {
      console.error("BaultSPA Error:", error);

      // Show user-friendly error
      if (error.message === "Request timeout") {
        showNotification("Request timeout. Please try again.", "error", 5000);
      } else if (error.message.includes("Failed to fetch")) {
        showNotification(
          "Network error. Please check your connection.",
          "error",
          5000,
        );
      } else {
        showNotification(
          "An error occurred while loading the page.",
          "error",
          3000,
        );
      }

      return false;
    }
  },

  /**
   * Enhanced form submission handler
   */
  async handleFormSubmit(event) {
    const form = event.target;
    if (form.matches(this.ignoreSelector)) {
      return;
    }

    event.preventDefault();

    // Check network
    if (!NetworkStatus.isOnline()) {
      showNotification("Cannot submit form while offline", "error", 3000);
      return;
    }

    const formData = new FormData(form);
    const url = form.action || window.location.href;
    const method = form.method.toUpperCase();

    progress.start();

    try {
      const response = await fetchWithTimeout(
        url,
        {
          method: method,
          body: formData,
          headers: {
            "X-SPA-NAVIGATE": "true",
            "X-Requested-With": "XMLHttpRequest",
            Accept: "text/html, application/xhtml+xml, application/xml;q=0.9",
          },
          credentials: "include",
          redirect: "follow",
        },
        15000, // 15 second timeout for form submissions
      );

      progress.done();

      // Handle redirect
      if (response.redirected && response.url) {
        this.navigateTo(response.url, true);
        return;
      }

      if (
        response.status >= 300 &&
        response.status < 400 &&
        response.headers.has("Location")
      ) {
        this.navigateTo(response.headers.get("Location"));
        return;
      }

      // Handle errors
      if (response.status === 500) {
        const errorHtml = await response.text();
        if (
          errorHtml.trim().toLowerCase().startsWith("<!doctype html") ||
          errorHtml.trim().toLowerCase().startsWith("<html")
        ) {
          document.documentElement.innerHTML = errorHtml;
          return;
        }
      }

      // Re-render content with response (validation errors, etc)
      const newPageHtml = await response.text();
      const tempDoc = new DOMParser().parseFromString(newPageHtml, "text/html");

      // Update CSRF token
      const newToken = CsrfTokenManager.extractTokenFromHtml(newPageHtml);
      if (newToken) {
        CsrfTokenManager.updateToken(newToken);
      }

      let newContentSelector = null;
      let newContent = null;

      for (const selector of this.contentSelectors) {
        const content = tempDoc.querySelector(selector)?.innerHTML;
        if (content) {
          newContentSelector = selector;
          newContent = content;
          break;
        }
      }

      const newTitle =
        tempDoc.querySelector("title")?.innerText || document.title;

      // Handle layout switching
      if (
        newContentSelector &&
        newContentSelector !== this.currentContentSelector
      ) {
        console.log(
          `BaultSPA: Layout switch in form (${this.currentContentSelector} → ${newContentSelector}).`,
        );
        window.location.href = response.url;
        return;
      }

      const contentContainer = this.getContentContainer();
      if (contentContainer && newContent) {
        contentContainer.innerHTML = newContent;
        document.title = newTitle;
        history.pushState(
          {
            path: response.url,
            layout: newContentSelector,
            timestamp: Date.now(),
          },
          newTitle,
          response.url,
        );
        await this.executeScripts(contentContainer);
        document.dispatchEvent(
          new CustomEvent("bault:navigated", {
            bubbles: true,
            detail: { url: response.url, layout: newContentSelector },
          }),
        );
      }
    } catch (error) {
      console.error("BaultSPA Form Error:", error);
      progress.done(true);

      if (error.message === "Request timeout") {
        showNotification(
          "Form submission timeout. Please try again.",
          "error",
          5000,
        );
      } else {
        showNotification(
          "Error submitting form. Please try again.",
          "error",
          3000,
        );
      }

      // Fallback to normal submission
      form.submit();
    }
  },

  /**
   * Smart prefetch with throttling
   */
  handleLinkHover(event) {
    const link = event.target.closest("a");

    if (
      !link ||
      link.hostname !== window.location.hostname ||
      link.matches(this.ignoreSelector)
    ) {
      return;
    }

    // Don't prefetch on slow connections or mobile with data saver
    if (isSlowConnection()) {
      return;
    }

    this.prefetch(link.href);
  },

  /**
   * Prefetch page
   */
  async prefetch(url) {
    if (this.pageCache.has(url) || this.prefetched.has(url)) {
      return;
    }

    // Check if we're already navigating
    if (this.isNavigating) {
      return;
    }

    this.prefetched.add(url);

    try {
      const response = await fetch(url, {
        headers: {
          "X-SPA-NAVIGATE": "true",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "include",
        priority: "low", // Use low priority for prefetch
      });

      if (!response.ok) {
        this.prefetched.delete(url);
        return;
      }

      const html = await response.text();
      const tempDoc = new DOMParser().parseFromString(html, "text/html");

      let foundLayout = null;
      let content = null;
      for (const selector of this.contentSelectors) {
        const selectorContent = tempDoc.querySelector(selector)?.innerHTML;
        if (selectorContent) {
          foundLayout = selector;
          content = selectorContent;
          break;
        }
      }

      const title = tempDoc.querySelector("title")?.innerText || "";
      if (content) {
        this.pageCache.set(url, {
          content,
          title,
          layout: foundLayout,
          timestamp: Date.now(),
        });
      }
    } catch (error) {
      // Silently fail for prefetch
      console.debug("BaultSPA: Prefetch failed for", url);
    } finally {
      this.prefetched.delete(url);
    }
  },

  handleLinkClick(event) {
    const link = event.target.closest("a");

    if (
      !link ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    ) {
      return;
    }

    if (link.matches(this.ignoreSelector)) {
      return;
    }
    if (link.hostname !== window.location.hostname) {
      return;
    }

    event.preventDefault();
    this.navigateTo(link.href);
  },

  /**
   * Navigate to URL
   */
  async navigateTo(url, pushState = true) {
    // Prevent redundant navigation
    if (window.location.href === url && !pushState) {
      return;
    }

    // Prevent concurrent navigation
    if (this.isNavigating) {
      console.log("BaultSPA: Navigation already in progress");
      return;
    }

    this.isNavigating = true;
    progress.start();

    let contentContainer = this.getContentContainer();
    if (!contentContainer) {
      this.isNavigating = false;
      return;
    }

    document.body.classList.add("spa-loading");
    contentContainer.classList.add("spa-content-exiting");

    try {
      // Check cache first
      if (this.pageCache.has(url)) {
        const cachedData = this.pageCache.get(url);
        const { content, title, layout, timestamp } = cachedData;

        // Check if cache is stale (older than 5 minutes)
        const cacheAge = Date.now() - timestamp;
        const maxCacheAge = 5 * 60 * 1000; // 5 minutes

        if (cacheAge > maxCacheAge) {
          console.log("BaultSPA: Cache stale, fetching fresh content");
          this.pageCache.delete(url);
        } else {
          // Use cached content
          if (layout && layout !== this.currentContentSelector) {
            console.log(`BaultSPA: Layout switch from cache`);
            this.currentContentSelector = layout;
            contentContainer = this.getContentContainer();
          }

          if (content && content.trim()) {
            contentContainer.innerHTML = content;
            document.title = title;
            if (pushState) {
              history.pushState(
                { path: url, layout: layout, timestamp: Date.now() },
                title,
                url,
              );
            }

            contentContainer.classList.remove("spa-content-exiting");
            contentContainer.classList.add("spa-content-entering");

            await this.executeScripts(contentContainer);

            contentContainer.addEventListener(
              "animationend",
              () => {
                contentContainer.classList.remove("spa-content-entering");
              },
              { once: true },
            );

            document.dispatchEvent(
              new CustomEvent("bault:navigated", {
                bubbles: true,
                detail: { url, layout: layout },
              }),
            );

            progress.done();
            this.isNavigating = false;
            document.body.classList.remove("spa-loading");
            return;
          }

          this.pageCache.delete(url);
        }
      }

      // Fetch fresh content
      const success = await this.fetchAndUpdatePage(
        url,
        contentContainer,
        pushState,
      );

      if (!success) {
        // Fallback to full page load on error
        window.location.href = url;
        return;
      }

      contentContainer = this.getContentContainer();
      contentContainer.classList.remove("spa-content-exiting");
      contentContainer.classList.add("spa-content-entering");

      contentContainer.addEventListener(
        "animationend",
        () => {
          contentContainer.classList.remove("spa-content-entering");
        },
        { once: true },
      );

      progress.done();
    } catch (error) {
      console.error("BaultSPA Navigation Error:", error);
      progress.done(true);
      window.location.href = url;
    } finally {
      this.isNavigating = false;
      document.body.classList.remove("spa-loading");
    }
  },

  handlePopState(event) {
    if (event.state && event.state.path) {
      this.navigateTo(location.href, false);
    }
  },

  /**
   * Enhanced script execution with better error handling
   */
  async executeScripts(container) {
    const scripts = Array.from(container.querySelectorAll("script"));

    // Separate inline and external scripts
    const inlineScripts = scripts.filter((s) => !s.src);
    const externalScripts = scripts.filter((s) => s.src);

    // Execute inline scripts first (synchronously)
    for (const oldScript of inlineScripts) {
      try {
        const newScript = document.createElement("script");
        for (const attr of oldScript.attributes) {
          newScript.setAttribute(attr.name, attr.value);
        }
        newScript.textContent = oldScript.textContent;
        oldScript.parentNode.replaceChild(newScript, oldScript);
      } catch (error) {
        console.error("BaultSPA: Error executing inline script", error);
      }
    }

    // Execute external scripts (can be parallel for async scripts)
    const scriptPromises = externalScripts.map((oldScript) => {
      return new Promise((resolve, reject) => {
        try {
          const newScript = document.createElement("script");
          for (const attr of oldScript.attributes) {
            newScript.setAttribute(attr.name, attr.value);
          }

          newScript.onload = () => resolve();
          newScript.onerror = () => {
            console.warn(`BaultSPA: Failed to load script: ${oldScript.src}`);
            resolve(); // Don't reject, just log warning
          };

          oldScript.parentNode.replaceChild(newScript, oldScript);

          // If script doesn't have src, resolve immediately
          if (!newScript.src) {
            resolve();
          }
        } catch (error) {
          console.error("BaultSPA: Error loading external script", error);
          resolve(); // Don't reject
        }
      });
    });

    // Wait for all scripts with a timeout
    try {
      await Promise.race([
        Promise.all(scriptPromises),
        new Promise((_, reject) =>
          setTimeout(() => reject(new Error("Script loading timeout")), 10000),
        ),
      ]);
    } catch (error) {
      console.warn("BaultSPA: Some scripts took too long to load");
    }
  },
};

export function initializeSPA() {
  console.log("BaultSPA v2.0 initializing...");
  BaultSPA.init();
}
