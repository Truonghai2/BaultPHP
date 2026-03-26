/**
 * Service Worker Manager v3.0
 * 
 * Features:
 * - Offline support
 * - Cache strategies (CacheFirst, NetworkFirst, StaleWhileRevalidate)
 * - Background sync
 * - Push notifications
 * - Update notifications
 * - Resource precaching
 */

export class ServiceWorkerManager {
  constructor(options = {}) {
    this.swPath = options.swPath || '/sw.js';
    this.scope = options.scope || '/';
    this.registration = null;
    this.updateAvailable = false;
    this.strategies = options.strategies || this.getDefaultStrategies();
    this.onUpdateFound = options.onUpdateFound || null;
    this.onUpdateReady = options.onUpdateReady || null;
    this.onOffline = options.onOffline || null;
    this.onOnline = options.onOnline || null;
  }

  /**
   * Register service worker
   */
  async register() {
    if (!('serviceWorker' in navigator)) {
      console.warn('Service Worker not supported');
      return false;
    }

    try {
      this.registration = await navigator.serviceWorker.register(this.swPath, {
        scope: this.scope
      });

      console.log('Service Worker registered:', this.registration);

      // Check for updates
      this.checkForUpdates();

      // Listen for updates
      this.registration.addEventListener('updatefound', () => {
        this.handleUpdateFound();
      });

      // Listen for controller change
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        this.handleControllerChange();
      });

      // Listen for messages from SW
      navigator.serviceWorker.addEventListener('message', (event) => {
        this.handleMessage(event);
      });

      // Monitor online/offline status
      this.monitorNetworkStatus();

      return true;

    } catch (error) {
      console.error('Service Worker registration failed:', error);
      return false;
    }
  }

  /**
   * Unregister service worker
   */
  async unregister() {
    if (this.registration) {
      await this.registration.unregister();
      this.registration = null;
      console.log('Service Worker unregistered');
    }
  }

  /**
   * Check for updates
   */
  async checkForUpdates() {
    if (this.registration) {
      await this.registration.update();
    }
  }

  /**
   * Handle update found
   */
  handleUpdateFound() {
    const installingWorker = this.registration.installing;

    if (!installingWorker) return;

    installingWorker.addEventListener('statechange', () => {
      if (installingWorker.state === 'installed') {
        if (navigator.serviceWorker.controller) {
          // New update available
          this.updateAvailable = true;
          
          if (this.onUpdateReady) {
            this.onUpdateReady();
          } else {
            this.showUpdateNotification();
          }
        }
      }
    });

    if (this.onUpdateFound) {
      this.onUpdateFound();
    }
  }

  /**
   * Handle controller change (new SW activated)
   */
  handleControllerChange() {
    console.log('New Service Worker activated');
    
    // Reload page to use new SW
    if (this.updateAvailable) {
      window.location.reload();
    }
  }

  /**
   * Handle messages from service worker
   */
  handleMessage(event) {
    const { type, payload } = event.data;

    switch (type) {
      case 'CACHE_UPDATED':
        console.log('Cache updated:', payload);
        break;
        
      case 'OFFLINE_MODE':
        console.log('Offline mode activated');
        if (this.onOffline) this.onOffline();
        break;
        
      case 'ONLINE_MODE':
        console.log('Online mode activated');
        if (this.onOnline) this.onOnline();
        break;
        
      default:
        console.log('SW message:', type, payload);
    }

    // Dispatch custom event
    window.dispatchEvent(new CustomEvent('sw:message', {
      detail: event.data
    }));
  }

  /**
   * Send message to service worker
   */
  async sendMessage(message) {
    if (!this.registration || !this.registration.active) {
      console.warn('No active service worker');
      return;
    }

    this.registration.active.postMessage(message);
  }

  /**
   * Precache resources
   */
  async precache(urls) {
    await this.sendMessage({
      type: 'PRECACHE',
      payload: { urls }
    });
  }

  /**
   * Clear cache
   */
  async clearCache(cacheName) {
    await this.sendMessage({
      type: 'CLEAR_CACHE',
      payload: { cacheName }
    });
  }

  /**
   * Show update notification
   */
  showUpdateNotification() {
    const notification = document.createElement('div');
    notification.className = 'sw-update-notification';
    notification.innerHTML = `
      <div class="sw-update-content">
        <p>A new version is available!</p>
        <button class="sw-update-btn">Update Now</button>
        <button class="sw-dismiss-btn">Later</button>
      </div>
    `;

    document.body.appendChild(notification);

    notification.querySelector('.sw-update-btn').addEventListener('click', () => {
      this.skipWaiting();
      notification.remove();
    });

    notification.querySelector('.sw-dismiss-btn').addEventListener('click', () => {
      notification.remove();
    });
  }

  /**
   * Skip waiting and activate new SW
   */
  async skipWaiting() {
    if (this.registration && this.registration.waiting) {
      this.registration.waiting.postMessage({ type: 'SKIP_WAITING' });
    }
  }

  /**
   * Monitor network status
   */
  monitorNetworkStatus() {
    window.addEventListener('online', () => {
      console.log('Back online');
      if (this.onOnline) this.onOnline();
      this.showNetworkStatus('online');
    });

    window.addEventListener('offline', () => {
      console.log('Gone offline');
      if (this.onOffline) this.onOffline();
      this.showNetworkStatus('offline');
    });
  }

  /**
   * Show network status notification
   */
  showNetworkStatus(status) {
    const notification = document.createElement('div');
    notification.className = `network-status network-status-${status}`;
    notification.textContent = status === 'online' ? 
      'Back online!' : 
      'You are offline. Some features may be limited.';

    document.body.appendChild(notification);

    setTimeout(() => {
      notification.classList.add('show');
    }, 10);

    setTimeout(() => {
      notification.classList.remove('show');
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  /**
   * Request push notification permission
   */
  async requestNotificationPermission() {
    if (!('Notification' in window)) {
      console.warn('Notifications not supported');
      return false;
    }

    const permission = await Notification.requestPermission();
    return permission === 'granted';
  }

  /**
   * Subscribe to push notifications
   */
  async subscribeToPush(publicKey) {
    if (!this.registration) {
      console.error('No service worker registration');
      return null;
    }

    try {
      const subscription = await this.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.urlBase64ToUint8Array(publicKey)
      });

      return subscription;

    } catch (error) {
      console.error('Push subscription failed:', error);
      return null;
    }
  }

  /**
   * Unsubscribe from push notifications
   */
  async unsubscribeFromPush() {
    if (!this.registration) return;

    const subscription = await this.registration.pushManager.getSubscription();
    if (subscription) {
      await subscription.unsubscribe();
    }
  }

  /**
   * Get default cache strategies
   */
  getDefaultStrategies() {
    return {
      html: 'NetworkFirst',
      css: 'CacheFirst',
      js: 'CacheFirst',
      images: 'CacheFirst',
      fonts: 'CacheFirst',
      api: 'NetworkFirst',
      default: 'NetworkFirst'
    };
  }

  /**
   * Utility: Convert base64 to Uint8Array
   */
  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
      .replace(/-/g, '+')
      .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    
    return outputArray;
  }

  /**
   * Check if SW is active
   */
  isActive() {
    return this.registration && navigator.serviceWorker.controller;
  }

  /**
   * Get SW state
   */
  getState() {
    if (!this.registration) return 'unregistered';
    if (this.registration.installing) return 'installing';
    if (this.registration.waiting) return 'waiting';
    if (this.registration.active) return 'active';
    return 'unknown';
  }
}

// Export singleton instance
export const swManager = new ServiceWorkerManager();

export default swManager;
