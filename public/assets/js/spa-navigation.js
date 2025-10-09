/**
 * BaultSPA: A lightweight SPA navigation helper for BaultPHP.
 * This script intercepts internal link clicks to provide a faster, app-like navigation experience
 * without full page reloads.
 */
const BaultSPA = {
  // --- CONFIGURATION ---
  contentSelector: "#app-content",
  ignoreSelector:
    '[data-no-spa], [target="_blank"], a[href^="#"], a[href$=".pdf"], a[href$=".zip"], form[method="POST"]',
  cacheSize: 50,

  // --- STATE ---
  pageCache: null,
  prefetched: new Set(),

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
    const contentContainer = document.querySelector(this.contentSelector);
    if (!contentContainer) {
      console.warn(
        `BaultSPA: Main content container with selector "${this.contentSelector}" not found. SPA navigation disabled.`,
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

  initializeFirstPage(contentContainer) {
    // Chỉ cache khi có nội dung thực sự
    if (contentContainer.innerHTML.trim()) {
      this.pageCache.set(window.location.href, {
        content: contentContainer.innerHTML,
        title: document.title,
      });
      history.replaceState(
        { path: window.location.href },
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
      });

      if (!response.ok) {
        throw new Error("Network response was not ok");
      }

      const newPageHtml = await response.text();
      const tempDoc = new DOMParser().parseFromString(newPageHtml, "text/html");
      const newContent = tempDoc.querySelector(this.contentSelector)?.innerHTML;
      const newTitle =
        tempDoc.querySelector("title")?.innerText || document.title;

      if (!newContent) {
        throw new Error("No content found in response");
      }

      contentContainer.innerHTML = newContent;
      document.title = newTitle;

      if (pushState) {
        history.pushState({ path: url }, newTitle, url);
      }

      this.pageCache.set(url, { content: newContent, title: newTitle });
      this.executeScripts(contentContainer);

      document.dispatchEvent(
        new CustomEvent("bault:navigated", { bubbles: true }),
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
        redirect: "manual", // We handle redirects manually
      });

      if (
        response.status >= 300 &&
        response.status < 400 &&
        response.headers.has("Location")
      ) {
        // Handle redirect
        this.navigateTo(response.headers.get("Location"));
      } else {
        // Re-render the content with the response (e.g., for validation errors)
        const newPageHtml = await response.text();
        const tempDoc = new DOMParser().parseFromString(
          newPageHtml,
          "text/html",
        );
        const newContent = tempDoc.querySelector(
          this.contentSelector,
        )?.innerHTML;
        const newTitle =
          tempDoc.querySelector("title")?.innerText || document.title;

        const contentContainer = document.querySelector(this.contentSelector);
        if (contentContainer && newContent) {
          contentContainer.innerHTML = newContent;
          document.title = newTitle;
          history.pushState({ path: response.url }, newTitle, response.url);
          this.executeScripts(contentContainer);
          document.dispatchEvent(
            new CustomEvent("bault:navigated", { bubbles: true }),
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

    fetch(url, { headers: { "X-SPA-NAVIGATE": "true" } })
      .then((response) => (response.ok ? response.text() : Promise.reject()))
      .then((html) => {
        const tempDoc = new DOMParser().parseFromString(html, "text/html");
        const content = tempDoc.querySelector(this.contentSelector)?.innerHTML;
        const title = tempDoc.querySelector("title")?.innerText || "";
        if (content) {
          this.pageCache.set(url, { content, title });
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

    const contentContainer = document.querySelector(this.contentSelector);
    if (!contentContainer) return;

    document.body.classList.add("spa-loading");
    contentContainer.classList.add("spa-content-exiting");

    try {
      if (this.pageCache.has(url)) {
        const { content, title } = this.pageCache.get(url);

        // Verify cached content
        if (content.trim()) {
          await new Promise((resolve) => setTimeout(resolve, 150));

          contentContainer.innerHTML = content;
          document.title = title;
          if (pushState) {
            history.pushState({ path: url }, title, url);
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
            new CustomEvent("bault:navigated", { bubbles: true }),
          );
          return;
        }
        // If cached content is empty, remove it and fetch fresh
        this.pageCache.delete(url);
      }

      // Fetch fresh content
      await this.fetchAndUpdatePage(url, contentContainer, pushState);

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
