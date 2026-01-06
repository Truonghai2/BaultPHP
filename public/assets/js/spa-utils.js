/**
 * SPA Utility Functions
 * Helper functions for SPA navigation system
 */

/**
 * Network status checker
 */
export const NetworkStatus = {
  isOnline() {
    return navigator.onLine;
  },

  waitForConnection(timeout = 5000) {
    return new Promise((resolve, reject) => {
      if (this.isOnline()) {
        resolve();
        return;
      }

      const onlineHandler = () => {
        window.removeEventListener("online", onlineHandler);
        clearTimeout(timeoutId);
        resolve();
      };

      const timeoutId = setTimeout(() => {
        window.removeEventListener("online", onlineHandler);
        reject(new Error("Network timeout"));
      }, timeout);

      window.addEventListener("online", onlineHandler);
    });
  },
};

/**
 * CSRF Token Manager
 */
export const CsrfTokenManager = {
  metaSelector: 'meta[name="csrf-token"]',

  getToken() {
    const tokenEl = document.querySelector(this.metaSelector);
    return tokenEl ? tokenEl.getAttribute("content") : null;
  },

  updateToken(newToken) {
    if (!newToken) return;

    let tokenEl = document.querySelector(this.metaSelector);
    if (!tokenEl) {
      tokenEl = document.createElement("meta");
      tokenEl.setAttribute("name", "csrf-token");
      document.head.appendChild(tokenEl);
    }
    tokenEl.setAttribute("content", newToken);

    // Dispatch event for other scripts that might be listening
    document.dispatchEvent(
      new CustomEvent("csrf:token-updated", {
        detail: { token: newToken },
      }),
    );
  },

  extractTokenFromHtml(html) {
    const tempDoc = new DOMParser().parseFromString(html, "text/html");
    const tokenEl = tempDoc.querySelector(this.metaSelector);
    return tokenEl ? tokenEl.getAttribute("content") : null;
  },
};

/**
 * Simple debounce function
 */
export function debounce(fn, delay) {
  let timeoutId;
  return function (...args) {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => fn.apply(this, args), delay);
  };
}

/**
 * Throttle function
 */
export function throttle(fn, delay) {
  let lastCall = 0;
  return function (...args) {
    const now = Date.now();
    if (now - lastCall >= delay) {
      lastCall = now;
      fn.apply(this, args);
    }
  };
}

/**
 * Check if device is mobile
 */
export function isMobileDevice() {
  return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
    navigator.userAgent,
  );
}

/**
 * Get connection speed estimate
 */
export function getConnectionSpeed() {
  if (
    "connection" in navigator &&
    navigator.connection &&
    "effectiveType" in navigator.connection
  ) {
    return navigator.connection.effectiveType; // '4g', '3g', '2g', 'slow-2g'
  }
  return "unknown";
}

/**
 * Check if connection is slow
 */
export function isSlowConnection() {
  const speed = getConnectionSpeed();
  return speed === "slow-2g" || speed === "2g";
}

/**
 * Fetch with timeout
 */
export async function fetchWithTimeout(url, options = {}, timeout = 10000) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeout);

  try {
    const response = await fetch(url, {
      ...options,
      signal: controller.signal,
    });
    clearTimeout(timeoutId);
    return response;
  } catch (error) {
    clearTimeout(timeoutId);
    if (error.name === "AbortError") {
      throw new Error("Request timeout");
    }
    throw error;
  }
}

/**
 * Fetch with retry
 */
export async function fetchWithRetry(
  url,
  options = {},
  maxRetries = 3,
  retryDelay = 1000,
) {
  let lastError;

  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = await fetch(url, options);
      if (response.ok || response.status >= 400) {
        // Don't retry on 4xx errors
        return response;
      }
      throw new Error(`HTTP ${response.status}`);
    } catch (error) {
      lastError = error;
      if (i < maxRetries - 1) {
        // Wait before retrying, with exponential backoff
        await new Promise((resolve) =>
          setTimeout(resolve, retryDelay * Math.pow(2, i)),
        );
      }
    }
  }

  throw lastError;
}

/**
 * Show notification/toast message
 */
export function showNotification(
  message,
  type = "info",
  duration = 3000,
  position = "top-right",
) {
  // Remove existing notification
  const existing = document.querySelector(".spa-notification");
  if (existing) {
    existing.remove();
  }

  const notification = document.createElement("div");
  notification.className = `spa-notification spa-notification-${type} spa-notification-${position}`;
  notification.textContent = message;

  // Add styles if not already present
  if (!document.querySelector("#spa-notification-styles")) {
    const style = document.createElement("style");
    style.id = "spa-notification-styles";
    style.textContent = `
      .spa-notification {
        position: fixed;
        padding: 12px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        animation: spa-notification-slide-in 0.3s ease-out;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      }
      .spa-notification-top-right {
        top: 20px;
        right: 20px;
      }
      .spa-notification-info {
        background: #3b82f6;
        color: white;
      }
      .spa-notification-success {
        background: #10b981;
        color: white;
      }
      .spa-notification-error {
        background: #ef4444;
        color: white;
      }
      .spa-notification-warning {
        background: #f59e0b;
        color: white;
      }
      @keyframes spa-notification-slide-in {
        from {
          transform: translateX(100%);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }
      @keyframes spa-notification-slide-out {
        from {
          transform: translateX(0);
          opacity: 1;
        }
        to {
          transform: translateX(100%);
          opacity: 0;
        }
      }
    `;
    document.head.appendChild(style);
  }

  document.body.appendChild(notification);

  if (duration > 0) {
    setTimeout(() => {
      notification.style.animation = "spa-notification-slide-out 0.3s ease-in";
      setTimeout(() => notification.remove(), 300);
    }, duration);
  }

  return notification;
}

/**
 * Performance monitor
 */
export class PerformanceMonitor {
  constructor() {
    this.metrics = [];
  }

  startTiming(label) {
    return {
      label,
      startTime: performance.now(),
      end: () => {
        const duration = performance.now() - this.startTime;
        this.metrics.push({ label, duration, timestamp: Date.now() });
        return duration;
      },
    };
  }

  getMetrics() {
    return this.metrics;
  }

  getAverageDuration(label) {
    const filtered = this.metrics.filter((m) => m.label === label);
    if (filtered.length === 0) return 0;
    const sum = filtered.reduce((acc, m) => acc + m.duration, 0);
    return sum / filtered.length;
  }

  clear() {
    this.metrics = [];
  }
}
