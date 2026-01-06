/**
 * BaultSPA: A lightweight SPA navigation helper for BaultPHP.
 * This script intercepts internal link clicks to provide a faster, app-like navigation experience
 * without full page reloads.
 */
const BaultSPA = {
  // --- CONFIGURATION ---
  contentSelectors: ["#app-content", ".admin-main"], // Support multiple layouts
  ignoreSelector:
    '[data-no-spa], [target="_blank"], a[href^="#"], a[href$=".pdf"], a[href$=".zip"], form[method="POST"]',
  cacheSize: 50,

  // --- STATE ---
  pageCache: null,
  prefetched: new Set(),
  currentContentSelector: null, // Track current layout

  /**
   * A simple LRU (Least Recently Used) Cache implementation.
   * When the cache is full, it removes the least recently used item.
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
    };
  },
  init() {
    // Find which content selector is present on current page
    this.currentContentSelector = this.detectContentSelector();

    if (!this.currentContentSelector) {
      console.warn(
        `BaultSPA: No content container found with selectors: ${this.contentSelectors.join(", ")}. SPA navigation disabled.`,
      );
      return;
    }

    const contentContainer = document.querySelector(
      this.currentContentSelector,
    );
    if (!contentContainer) {
      console.warn(
        `BaultSPA: Content container "${this.currentContentSelector}" not found. SPA navigation disabled.`,
      );
      return;
    }

    // Initialize the cache
    this.pageCache = this.createLRUCache(this.cacheSize);

    // Đợi DOMContentLoaded để đảm bảo content đã được render đầy đủ
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => {
        this.initializeFirstPage(contentContainer);
      });
    } else {
      this.initializeFirstPage(contentContainer);
    }

    document.addEventListener("click", this.handleLinkClick.bind(this));
    document.addEventListener("mouseover", this.handleLinkHover.bind(this));
    document.addEventListener("touchstart", this.handleLinkHover.bind(this), {
      passive: true,
    });
    window.addEventListener("popstate", this.handlePopState.bind(this));
    document.addEventListener("submit", this.handleFormSubmit.bind(this));
  },

  /**
   * Detect which content selector is present on the current page.
   */
  detectContentSelector() {
    for (const selector of this.contentSelectors) {
      if (document.querySelector(selector)) {
        return selector;
      }
    }
    return null;
  },

  /**
   * Get current content container.
   */
  getContentContainer() {
    return document.querySelector(this.currentContentSelector);
  },

  initializeFirstPage(contentContainer) {
    // Chỉ cache khi có nội dung thực sự
    if (contentContainer.innerHTML.trim()) {
      this.pageCache.set(window.location.href, {
        content: contentContainer.innerHTML,
        title: document.title,
        layout: this.currentContentSelector,
      });
      history.replaceState(
        { path: window.location.href, layout: this.currentContentSelector },
        document.title,
        window.location.href,
      );
    } else {
      this.fetchAndUpdatePage(window.location.href, contentContainer, false);
    }
  },

  async fetchAndUpdatePage(url, contentContainer, pushState = true) {
    try {
      const response = await fetch(url, {
        headers: { "X-SPA-NAVIGATE": "true" },
        credentials: "include",
      });

      if (!response.ok) {
        if (response.status === 500) {
          const errorHtml = await response.text();
          if (
            errorHtml.trim().toLowerCase().startsWith("<!doctype html") ||
            errorHtml.trim().toLowerCase().startsWith("<html")
          ) {
            document.documentElement.innerHTML = errorHtml;
          }
        }
        throw new Error(`Network response was not ok: ${response.status}`);
      }

      const newPageHtml = await response.text();
      const tempDoc = new DOMParser().parseFromString(newPageHtml, "text/html");

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

      if (newContentSelector !== this.currentContentSelector) {
        console.log(
          `BaultSPA: Layout switch detected (${this.currentContentSelector} → ${newContentSelector}). Performing full page load...`,
        );
        window.location.href = url;
        return;
      }

      contentContainer.innerHTML = newContent;
      document.title = newTitle;

      if (pushState) {
        history.pushState(
          { path: url, layout: newContentSelector },
          newTitle,
          url,
        );
      }

      this.pageCache.set(url, {
        content: newContent,
        title: newTitle,
        layout: newContentSelector,
      });
      this.executeScripts(contentContainer);

      document.dispatchEvent(
        new CustomEvent("bault:navigated", {
          bubbles: true,
          detail: { url, layout: newContentSelector },
        }),
      );

      return true;
    } catch (error) {
      console.error("BaultSPA Error:", error);
      return false;
    }
  },

  async handleFormSubmit(event) {
    const form = event.target;
    if (form.matches(this.ignoreSelector)) {
      return;
    }

    event.preventDefault();
    const formData = new FormData(form);
    const url = form.action || window.location.href;
    const method = form.method.toUpperCase();

    try {
      const response = await fetch(url, {
        method: method,
        body: formData,
        headers: {
          "X-SPA-NAVIGATE": "true",
          Accept:
            "text/html, application/xhtml+xml, application/xml;q=0.9, */*;q=0.8",
        },
        credentials: "include",
        redirect: "follow",
      });

      if (response.redirected && response.url) {
        this.navigateTo(response.url, true);
        return;
      }

      if (
        response.status >= 300 &&
        response.status < 400 &&
        response.headers.has("Location")
      ) {
        // Fallback manual redirect (shouldn't happen with redirect: "follow")
        this.navigateTo(response.headers.get("Location"));
      } else {
        // Xử lý lỗi 500 khi debug mode được bật
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

        // Re-render the content with the response (e.g., for validation errors)
        const newPageHtml = await response.text();
        const tempDoc = new DOMParser().parseFromString(
          newPageHtml,
          "text/html",
        );
        // Detect content in the new page
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

        // Handle layout switching in form response - full reload for different layouts
        if (
          newContentSelector &&
          newContentSelector !== this.currentContentSelector
        ) {
          console.log(
            `BaultSPA: Layout switch in form (${this.currentContentSelector} → ${newContentSelector}). Performing full page load...`,
          );
          window.location.href = response.url;
          return;
        }

        const contentContainer = this.getContentContainer();
        if (contentContainer && newContent) {
          contentContainer.innerHTML = newContent;
          document.title = newTitle;
          history.pushState(
            { path: response.url, layout: newContentSelector },
            newTitle,
            response.url,
          );
          this.executeScripts(contentContainer);
          document.dispatchEvent(
            new CustomEvent("bault:navigated", {
              bubbles: true,
              detail: { url: response.url, layout: newContentSelector },
            }),
          );
        } else {
          // window.location.href = url;
        }
      }
    } catch (error) {
      console.error("BaultSPA Form Error:", error);
      form.submit();
    }
  },

  handleLinkHover(event) {
    const link = event.target.closest("a");

    if (
      !link ||
      link.hostname !== window.location.hostname ||
      link.matches(this.ignoreSelector)
    ) {
      return;
    }

    this.prefetch(link.href);
  },

  prefetch(url) {
    if (this.pageCache.has(url) || this.prefetched.has(url)) {
      return;
    }

    this.prefetched.add(url);

    fetch(url, {
      headers: { "X-SPA-NAVIGATE": "true" },
      credentials: "include",
    })
      .then((response) => (response.ok ? response.text() : Promise.reject()))
      .then((html) => {
        const tempDoc = new DOMParser().parseFromString(html, "text/html");

        // Try to find content in any supported layout
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
          this.pageCache.set(url, { content, title, layout: foundLayout });
        }
      })
      .catch(() => {})
      .finally(() => {
        this.prefetched.delete(url);
      });
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

  async navigateTo(url, pushState = true) {
    // Prevent unnecessary reloads on popstate events for the current page
    if (window.location.href === url && !pushState) {
      return;
    }

    let contentContainer = this.getContentContainer();
    if (!contentContainer) return;

    document.body.classList.add("spa-loading");
    contentContainer.classList.add("spa-content-exiting");

    try {
      if (this.pageCache.has(url)) {
        const cachedData = this.pageCache.get(url);
        const { content, title, layout } = cachedData;

        // Handle layout switching from cache
        if (layout && layout !== this.currentContentSelector) {
          console.log(
            `BaultSPA: Layout switch from cache: ${this.currentContentSelector} → ${layout}`,
          );
          this.currentContentSelector = layout;
          contentContainer = this.getContentContainer();
        }

        // Verify cached content
        if (content && content.trim()) {
          contentContainer.innerHTML = content;
          document.title = title;
          if (pushState) {
            history.pushState({ path: url, layout: layout }, title, url);
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
          return;
        }
        // If cached content is empty, remove it and fetch fresh
        this.pageCache.delete(url);
      }

      // Fetch fresh content
      await this.fetchAndUpdatePage(url, contentContainer, pushState);

      contentContainer = this.getContentContainer(); // Refresh reference after possible layout switch
      contentContainer.classList.remove("spa-content-exiting");
      contentContainer.classList.add("spa-content-entering");

      contentContainer.addEventListener(
        "animationend",
        () => {
          contentContainer.classList.remove("spa-content-entering");
        },
        { once: true },
      );
    } catch (error) {
      console.error("BaultSPA Navigation Error:", error);
      window.location.href = url;
    } finally {
      document.body.classList.remove("spa-loading");
    }
  },

  handlePopState(event) {
    if (event.state && event.state.path) {
      this.navigateTo(location.href, false);
    }
  },

  async executeScripts(container) {
    const scripts = Array.from(container.querySelectorAll("script"));

    for (const oldScript of scripts) {
      const newScript = document.createElement("script");

      for (const attr of oldScript.attributes) {
        newScript.setAttribute(attr.name, attr.value);
      }

      if (oldScript.textContent) {
        newScript.textContent = oldScript.textContent;
      }

      oldScript.parentNode.replaceChild(newScript, oldScript);

      if (newScript.src) {
        await new Promise((resolve, reject) => {
          newScript.onload = resolve;
          newScript.onerror = reject;
        });
      }
    }
  },
};

export function initializeSPA() {
  console.log("BaultSPA initialized.");
  BaultSPA.init();
}
