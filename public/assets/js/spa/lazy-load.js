/**
 * Image Lazy Loading & Optimization v3.0
 * 
 * Features:
 * - Intersection Observer API
 * - Progressive image loading
 * - Responsive images (srcset)
 * - WebP support detection
 * - Blur placeholder
 * - Loading states
 * - Error handling
 */

export class LazyLoader {
  constructor(options = {}) {
    this.options = {
      root: null,
      rootMargin: options.rootMargin || '50px',
      threshold: options.threshold || 0.01,
      loadingClass: options.loadingClass || 'lazy-loading',
      loadedClass: options.loadedClass || 'lazy-loaded',
      errorClass: options.errorClass || 'lazy-error',
      enableWebP: options.enableWebP !== false,
      placeholder: options.placeholder || 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E',
    };

    this.observer = null;
    this.supportsWebP = false;
    this.init();
  }

  /**
   * Initialize lazy loader
   */
  async init() {
    // Check WebP support
    if (this.options.enableWebP) {
      this.supportsWebP = await this.checkWebPSupport();
    }

    // Create Intersection Observer
    this.observer = new IntersectionObserver(
      (entries) => this.handleIntersection(entries),
      {
        root: this.options.root,
        rootMargin: this.options.rootMargin,
        threshold: this.options.threshold,
      }
    );

    // Observe existing images
    this.observeImages();
  }

  /**
   * Observe images
   */
  observeImages() {
    const images = document.querySelectorAll('img[data-src], img[data-srcset], [data-bg]');
    images.forEach(img => this.observe(img));
  }

  /**
   * Observe element
   */
  observe(element) {
    if (this.observer) {
      this.observer.observe(element);
    }
  }

  /**
   * Unobserve element
   */
  unobserve(element) {
    if (this.observer) {
      this.observer.unobserve(element);
    }
  }

  /**
   * Handle intersection
   */
  handleIntersection(entries) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        this.loadElement(entry.target);
        this.unobserve(entry.target);
      }
    });
  }

  /**
   * Load element (image or background)
   */
  async loadElement(element) {
    if (element.tagName === 'IMG') {
      await this.loadImage(element);
    } else if (element.hasAttribute('data-bg')) {
      await this.loadBackground(element);
    }
  }

  /**
   * Load image
   */
  async loadImage(img) {
    img.classList.add(this.options.loadingClass);

    try {
      // Get source URL
      let src = img.dataset.src;
      let srcset = img.dataset.srcset;

      // Use WebP if supported
      if (this.supportsWebP && img.dataset.srcWebp) {
        src = img.dataset.srcWebp;
      }

      if (this.supportsWebP && img.dataset.srcsetWebp) {
        srcset = img.dataset.srcsetWebp;
      }

      // Create temp image for loading
      const tempImg = new Image();
      
      // Set srcset first if available
      if (srcset) {
        tempImg.srcset = srcset;
      }
      
      if (src) {
        tempImg.src = src;
      }

      // Wait for image to load
      await new Promise((resolve, reject) => {
        tempImg.onload = resolve;
        tempImg.onerror = reject;
      });

      // Apply loaded image
      if (srcset) {
        img.srcset = srcset;
      }
      
      if (src) {
        img.src = src;
      }

      // Remove data attributes
      delete img.dataset.src;
      delete img.dataset.srcset;
      delete img.dataset.srcWebp;
      delete img.dataset.srcsetWebp;

      // Update classes
      img.classList.remove(this.options.loadingClass);
      img.classList.add(this.options.loadedClass);

      // Dispatch loaded event
      img.dispatchEvent(new CustomEvent('lazyloaded', { bubbles: true }));

    } catch (error) {
      console.error('Image load error:', error);
      
      img.classList.remove(this.options.loadingClass);
      img.classList.add(this.options.errorClass);
      
      // Use fallback if available
      if (img.dataset.srcFallback) {
        img.src = img.dataset.srcFallback;
      }

      img.dispatchEvent(new CustomEvent('lazyerror', { 
        bubbles: true,
        detail: { error }
      }));
    }
  }

  /**
   * Load background image
   */
  async loadBackground(element) {
    element.classList.add(this.options.loadingClass);

    try {
      let bg = element.dataset.bg;

      // Use WebP if supported
      if (this.supportsWebP && element.dataset.bgWebp) {
        bg = element.dataset.bgWebp;
      }

      // Create temp image to preload
      const tempImg = new Image();
      tempImg.src = bg;

      await new Promise((resolve, reject) => {
        tempImg.onload = resolve;
        tempImg.onerror = reject;
      });

      // Apply background
      element.style.backgroundImage = `url('${bg}')`;

      // Remove data attributes
      delete element.dataset.bg;
      delete element.dataset.bgWebp;

      // Update classes
      element.classList.remove(this.options.loadingClass);
      element.classList.add(this.options.loadedClass);

      element.dispatchEvent(new CustomEvent('lazyloaded', { bubbles: true }));

    } catch (error) {
      console.error('Background load error:', error);
      
      element.classList.remove(this.options.loadingClass);
      element.classList.add(this.options.errorClass);

      element.dispatchEvent(new CustomEvent('lazyerror', {
        bubbles: true,
        detail: { error }
      }));
    }
  }

  /**
   * Check WebP support
   */
  async checkWebPSupport() {
    return new Promise(resolve => {
      const webP = new Image();
      webP.onload = webP.onerror = () => {
        resolve(webP.height === 2);
      };
      webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
    });
  }

  /**
   * Force load all images
   */
  loadAll() {
    const images = document.querySelectorAll('img[data-src], [data-bg]');
    images.forEach(img => this.loadElement(img));
  }

  /**
   * Destroy lazy loader
   */
  destroy() {
    if (this.observer) {
      this.observer.disconnect();
      this.observer = null;
    }
  }
}

/**
 * Image optimizer helper
 */
export class ImageOptimizer {
  /**
   * Generate responsive srcset
   */
  static generateSrcset(baseUrl, widths = [320, 640, 960, 1280, 1920]) {
    return widths.map(width => {
      const url = this.resizeUrl(baseUrl, width);
      return `${url} ${width}w`;
    }).join(', ');
  }

  /**
   * Generate resize URL (depends on your image service)
   */
  static resizeUrl(url, width, height = null) {
    // Example for Cloudflare Images
    // return `${url}?width=${width}${height ? `&height=${height}` : ''}`;
    
    // Example for custom service
    return url.replace(/(\.[^.]+)$/, `_${width}w$1`);
  }

  /**
   * Create blur placeholder data URL
   */
  static createBlurPlaceholder(width = 20, height = 20, color = '#f0f0f0') {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = color;
    ctx.fillRect(0, 0, width, height);
    
    return canvas.toDataURL('image/jpeg', 0.1);
  }

  /**
   * Generate WebP URL
   */
  static toWebP(url) {
    return url.replace(/\.(jpg|jpeg|png)$/i, '.webp');
  }
}

// Export singleton instance
export const lazyLoader = new LazyLoader();

export default lazyLoader;
