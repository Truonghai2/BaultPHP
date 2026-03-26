/**
 * Modern SPA Router v3.0
 * 
 * Features:
 * - Dynamic route matching with parameters
 * - Lazy loading of route components
 * - Route guards (auth, permissions)
 * - Nested routes support
 * - Query parameters handling
 * - Route transitions
 * - History management
 * - Middleware support
 */

export class Router {
  constructor(options = {}) {
    this.routes = new Map();
    this.middlewares = [];
    this.currentRoute = null;
    this.guards = new Map();
    this.baseUrl = options.baseUrl || '';
    this.mode = options.mode || 'history'; // 'history' or 'hash'
    this.defaultTransition = options.transition || 'fade';
    this.onNotFound = options.onNotFound || this.handleNotFound.bind(this);
    this.beforeEach = options.beforeEach || null;
    this.afterEach = options.afterEach || null;
    
    // Route cache for lazy loaded components
    this.componentCache = new Map();
    
    // Navigation state
    this.isNavigating = false;
    this.navigationQueue = [];
  }

  /**
   * Define a route
   */
  route(path, config) {
    const route = {
      path: this.normalizePath(path),
      pattern: this.createRoutePattern(path),
      component: config.component,
      lazy: config.lazy || null,
      meta: config.meta || {},
      guards: config.guards || [],
      children: config.children || [],
      transition: config.transition || this.defaultTransition,
      name: config.name || null,
    };

    this.routes.set(route.path, route);
    
    // Register named route
    if (route.name) {
      this.routes.set(`name:${route.name}`, route);
    }

    return this;
  }

  /**
   * Route group with shared prefix and middleware
   */
  group(prefix, callback, options = {}) {
    const previousBase = this.baseUrl;
    this.baseUrl = this.normalizePath(previousBase + '/' + prefix);
    
    if (options.middleware) {
      this.middlewares.push(...options.middleware);
    }
    
    callback(this);
    
    this.baseUrl = previousBase;
    if (options.middleware) {
      this.middlewares.splice(-options.middleware.length);
    }
    
    return this;
  }

  /**
   * Register global middleware
   */
  use(middleware) {
    this.middlewares.push(middleware);
    return this;
  }

  /**
   * Register route guard
   */
  guard(name, handler) {
    this.guards.set(name, handler);
    return this;
  }

  /**
   * Navigate to a URL
   */
  async push(path, options = {}) {
    if (this.isNavigating && !options.force) {
      this.navigationQueue.push({ path, options });
      return;
    }

    this.isNavigating = true;

    try {
      const url = this.resolveUrl(path);
      const route = this.matchRoute(url);

      if (!route) {
        await this.onNotFound(url);
        return;
      }

      // Run beforeEach hook
      if (this.beforeEach) {
        const result = await this.beforeEach(route, this.currentRoute);
        if (result === false) {
          this.isNavigating = false;
          return;
        }
      }

      // Run route guards
      const guardResult = await this.runGuards(route);
      if (guardResult === false) {
        this.isNavigating = false;
        return;
      }

      // Run middlewares
      const middlewareResult = await this.runMiddlewares(route);
      if (middlewareResult === false) {
        this.isNavigating = false;
        return;
      }

      // Load component (lazy or eager)
      const component = await this.loadComponent(route);

      // Update history
      if (!options.replace) {
        history.pushState(
          { path: url, route: route.path },
          '',
          url
        );
      } else {
        history.replaceState(
          { path: url, route: route.path },
          '',
          url
        );
      }

      // Render component
      await this.renderComponent(component, route);

      // Update current route
      this.currentRoute = route;

      // Run afterEach hook
      if (this.afterEach) {
        await this.afterEach(route);
      }

      // Emit navigation event
      window.dispatchEvent(new CustomEvent('router:navigated', {
        detail: { route, url }
      }));

    } catch (error) {
      console.error('Navigation error:', error);
      this.handleNavigationError(error);
    } finally {
      this.isNavigating = false;
      
      // Process queued navigation
      if (this.navigationQueue.length > 0) {
        const next = this.navigationQueue.shift();
        await this.push(next.path, next.options);
      }
    }
  }

  /**
   * Replace current route
   */
  async replace(path) {
    return this.push(path, { replace: true });
  }

  /**
   * Go back in history
   */
  back() {
    history.back();
  }

  /**
   * Go forward in history
   */
  forward() {
    history.forward();
  }

  /**
   * Navigate by route name
   */
  async pushByName(name, params = {}, query = {}) {
    const route = this.routes.get(`name:${name}`);
    if (!route) {
      console.error(`Route "${name}" not found`);
      return;
    }

    let path = route.path;
    
    // Replace parameters
    Object.entries(params).forEach(([key, value]) => {
      path = path.replace(`:${key}`, value);
    });

    // Add query parameters
    if (Object.keys(query).length > 0) {
      const queryString = new URLSearchParams(query).toString();
      path += '?' + queryString;
    }

    return this.push(path);
  }

  /**
   * Match route by URL
   */
  matchRoute(url) {
    const urlObj = new URL(url, window.location.origin);
    const pathname = urlObj.pathname;

    for (const [, route] of this.routes) {
      if (route.name && route.name.startsWith('name:')) continue;

      const match = pathname.match(route.pattern);
      if (match) {
        const params = this.extractParams(route.path, pathname);
        const query = Object.fromEntries(urlObj.searchParams);

        return {
          ...route,
          params,
          query,
          url: pathname,
          fullUrl: url,
        };
      }
    }

    return null;
  }

  /**
   * Create route pattern for matching
   */
  createRoutePattern(path) {
    // Convert :param to regex capture group
    const pattern = path
      .replace(/:[^/]+/g, '([^/]+)')
      .replace(/\*/g, '.*');
    
    return new RegExp(`^${pattern}$`);
  }

  /**
   * Extract parameters from URL
   */
  extractParams(routePath, url) {
    const params = {};
    const routeParts = routePath.split('/');
    const urlParts = url.split('/');

    routeParts.forEach((part, index) => {
      if (part.startsWith(':')) {
        const paramName = part.slice(1);
        params[paramName] = urlParts[index];
      }
    });

    return params;
  }

  /**
   * Load route component (lazy or eager)
   */
  async loadComponent(route) {
    // Check cache first
    if (this.componentCache.has(route.path)) {
      return this.componentCache.get(route.path);
    }

    let component;

    if (route.lazy) {
      // Lazy load component
      if (typeof route.lazy === 'function') {
        const module = await route.lazy();
        component = module.default || module;
      } else if (typeof route.lazy === 'string') {
        // Dynamic import from path
        const module = await import(route.lazy);
        component = module.default || module;
      }
    } else if (route.component) {
      component = route.component;
    } else {
      throw new Error(`No component defined for route: ${route.path}`);
    }

    // Cache component
    this.componentCache.set(route.path, component);

    return component;
  }

  /**
   * Render component with transition
   */
  async renderComponent(component, route) {
    const container = document.querySelector('#app-content') || 
                     document.querySelector('.admin-main');

    if (!container) {
      console.error('Router: Container not found');
      return;
    }

    // Exit transition
    await this.transitionOut(container, route.transition);

    // Render component
    if (typeof component === 'function') {
      const html = await component(route);
      container.innerHTML = html;
    } else if (typeof component === 'string') {
      container.innerHTML = component;
    } else if (component.render) {
      const html = await component.render(route);
      container.innerHTML = html;
    }

    // Execute scripts in new content
    await this.executeScripts(container);

    // Enter transition
    await this.transitionIn(container, route.transition);

    // Update meta tags
    this.updateMetaTags(route);
  }

  /**
   * Run route guards
   */
  async runGuards(route) {
    for (const guardName of route.guards) {
      const guard = this.guards.get(guardName);
      if (!guard) {
        console.warn(`Guard "${guardName}" not found`);
        continue;
      }

      const result = await guard(route, this.currentRoute);
      if (result === false) {
        return false;
      }
    }
    return true;
  }

  /**
   * Run middlewares
   */
  async runMiddlewares(route) {
    for (const middleware of this.middlewares) {
      const result = await middleware(route, this.currentRoute);
      if (result === false) {
        return false;
      }
    }
    return true;
  }

  /**
   * Transition out animation
   */
  async transitionOut(element, transition) {
    return new Promise(resolve => {
      element.classList.add(`transition-${transition}-out`);
      element.addEventListener('animationend', () => {
        element.classList.remove(`transition-${transition}-out`);
        resolve();
      }, { once: true });

      // Fallback timeout
      setTimeout(resolve, 500);
    });
  }

  /**
   * Transition in animation
   */
  async transitionIn(element, transition) {
    return new Promise(resolve => {
      element.classList.add(`transition-${transition}-in`);
      element.addEventListener('animationend', () => {
        element.classList.remove(`transition-${transition}-in`);
        resolve();
      }, { once: true });

      // Fallback timeout
      setTimeout(resolve, 500);
    });
  }

  /**
   * Execute scripts in container
   */
  async executeScripts(container) {
    const scripts = container.querySelectorAll('script');
    for (const script of scripts) {
      const newScript = document.createElement('script');
      
      if (script.src) {
        newScript.src = script.src;
        await new Promise(resolve => {
          newScript.onload = resolve;
          newScript.onerror = resolve;
          document.head.appendChild(newScript);
        });
      } else {
        newScript.textContent = script.textContent;
        document.head.appendChild(newScript);
      }
      
      script.remove();
    }
  }

  /**
   * Update meta tags for SEO
   */
  updateMetaTags(route) {
    if (route.meta.title) {
      document.title = route.meta.title;
    }

    if (route.meta.description) {
      this.updateMeta('description', route.meta.description);
    }

    if (route.meta.keywords) {
      this.updateMeta('keywords', route.meta.keywords);
    }

    // Open Graph tags
    if (route.meta.og) {
      Object.entries(route.meta.og).forEach(([key, value]) => {
        this.updateMeta(`og:${key}`, value, 'property');
      });
    }

    // Twitter Card tags
    if (route.meta.twitter) {
      Object.entries(route.meta.twitter).forEach(([key, value]) => {
        this.updateMeta(`twitter:${key}`, value, 'name');
      });
    }
  }

  /**
   * Update or create meta tag
   */
  updateMeta(name, content, attribute = 'name') {
    let meta = document.querySelector(`meta[${attribute}="${name}"]`);
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute(attribute, name);
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', content);
  }

  /**
   * Handle 404 errors
   */
  async handleNotFound(url) {
    console.error(`Route not found: ${url}`);
    
    const container = document.querySelector('#app-content') || 
                     document.querySelector('.admin-main');
    
    if (container) {
      container.innerHTML = `
        <div class="not-found">
          <h1>404 - Page Not Found</h1>
          <p>The page "${url}" could not be found.</p>
          <a href="/" class="btn">Go Home</a>
        </div>
      `;
    }
  }

  /**
   * Handle navigation errors
   */
  handleNavigationError(error) {
    console.error('Navigation error:', error);
    
    window.dispatchEvent(new CustomEvent('router:error', {
      detail: { error }
    }));
  }

  /**
   * Initialize router
   */
  init() {
    // Handle popstate (back/forward)
    window.addEventListener('popstate', (event) => {
      if (event.state && event.state.path) {
        this.push(event.state.path, { replace: true });
      }
    });

    // Intercept link clicks
    document.addEventListener('click', (event) => {
      const link = event.target.closest('a');
      
      if (!link || !link.href) return;
      if (link.hasAttribute('data-no-spa')) return;
      if (link.target === '_blank') return;
      if (link.href.startsWith('mailto:')) return;
      if (link.href.startsWith('tel:')) return;
      
      // Check if internal link
      const url = new URL(link.href);
      if (url.origin !== window.location.origin) return;
      
      event.preventDefault();
      this.push(link.href);
    });

    // Handle initial route
    const currentPath = window.location.pathname + window.location.search;
    this.push(currentPath, { replace: true });

    return this;
  }

  /**
   * Utility methods
   */
  normalizePath(path) {
    return path.replace(/\/+/g, '/').replace(/\/$/, '') || '/';
  }

  resolveUrl(path) {
    if (path.startsWith('http')) {
      return path;
    }
    return window.location.origin + this.normalizePath(this.baseUrl + '/' + path);
  }

  /**
   * Get current route
   */
  getCurrentRoute() {
    return this.currentRoute;
  }

  /**
   * Check if route is active
   */
  isActive(path) {
    return this.currentRoute && this.currentRoute.path === this.normalizePath(path);
  }
}

export default Router;
