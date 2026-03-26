/**
 * Component System v3.0
 * 
 * Features:
 * - Lifecycle hooks (created, mounted, updated, destroyed)
 * - Props and reactive data
 * - Computed properties
 * - Watchers
 * - Event emitters
 * - Slots and named slots
 * - Scoped styles
 * - Template compilation
 * - Component composition
 */

export class Component {
  constructor(options = {}) {
    this.options = options;
    this.$el = null;
    this.$parent = options.parent || null;
    this.$children = [];
    this.$props = options.props || {};
    this.$data = null;
    this.$computed = {};
    this.$watchers = new Map();
    this.$listeners = new Map();
    this._isMounted = false;
    this._isDestroyed = false;
    this._updateScheduled = false;
    
    // Initialize component
    this.init();
  }

  /**
   * Initialize component
   */
  init() {
    // Call created hook
    this.callHook('beforeCreate');
    
    // Initialize data
    this.initData();
    
    // Initialize computed properties
    this.initComputed();
    
    // Initialize watchers
    this.initWatchers();
    
    // Call created hook
    this.callHook('created');
  }

  /**
   * Initialize reactive data
   */
  initData() {
    const dataFn = this.options.data;
    const initialData = typeof dataFn === 'function' ? dataFn.call(this) : (dataFn || {});
    
    this.$data = this.makeReactive(initialData);
  }

  /**
   * Make object reactive with Proxy
   */
  makeReactive(obj, path = '') {
    const component = this;
    
    return new Proxy(obj, {
      get(target, property) {
        const value = target[property];
        
        // Return reactive nested objects
        if (value && typeof value === 'object' && !Array.isArray(value)) {
          return component.makeReactive(value, `${path}.${property}`);
        }
        
        return value;
      },
      
      set(target, property, value) {
        const oldValue = target[property];
        
        if (oldValue === value) {
          return true;
        }
        
        target[property] = value;
        
        // Trigger watchers
        const fullPath = path ? `${path}.${property}` : property;
        component.triggerWatchers(fullPath, value, oldValue);
        
        // Schedule update
        component.scheduleUpdate();
        
        return true;
      }
    });
  }

  /**
   * Initialize computed properties
   */
  initComputed() {
    const computed = this.options.computed || {};
    
    Object.entries(computed).forEach(([key, getter]) => {
      Object.defineProperty(this.$computed, key, {
        get: () => {
          return typeof getter === 'function' ? 
            getter.call(this) : 
            getter.get.call(this);
        },
        enumerable: true
      });
    });
  }

  /**
   * Initialize watchers
   */
  initWatchers() {
    const watch = this.options.watch || {};
    
    Object.entries(watch).forEach(([key, handler]) => {
      this.watch(key, handler);
    });
  }

  /**
   * Watch a data property
   */
  watch(path, handler, options = {}) {
    if (!this.$watchers.has(path)) {
      this.$watchers.set(path, []);
    }
    
    const watcher = {
      handler,
      immediate: options.immediate || false,
      deep: options.deep || false,
    };
    
    this.$watchers.get(path).push(watcher);
    
    // Run immediately if requested
    if (watcher.immediate) {
      const value = this.getNestedValue(this.$data, path);
      handler.call(this, value, undefined);
    }
    
    // Return unwatch function
    return () => {
      const watchers = this.$watchers.get(path);
      if (watchers) {
        const index = watchers.indexOf(watcher);
        if (index > -1) {
          watchers.splice(index, 1);
        }
      }
    };
  }

  /**
   * Trigger watchers
   */
  triggerWatchers(path, newValue, oldValue) {
    const watchers = this.$watchers.get(path);
    if (watchers) {
      watchers.forEach(watcher => {
        watcher.handler.call(this, newValue, oldValue);
      });
    }
  }

  /**
   * Mount component to DOM element
   */
  async mount(el) {
    if (typeof el === 'string') {
      this.$el = document.querySelector(el);
    } else {
      this.$el = el;
    }
    
    if (!this.$el) {
      console.error('Mount target not found');
      return;
    }
    
    // Call beforeMount hook
    this.callHook('beforeMount');
    
    // Render component
    await this.render();
    
    // Mark as mounted
    this._isMounted = true;
    
    // Call mounted hook
    this.callHook('mounted');
    
    return this;
  }

  /**
   * Render component
   */
  async render() {
    if (!this.$el) return;
    
    const template = await this.getTemplate();
    const html = this.compileTemplate(template);
    
    // Update DOM
    this.$el.innerHTML = html;
    
    // Mount child components
    await this.mountChildren();
    
    // Attach event listeners
    this.attachEventListeners();
    
    // Apply scoped styles
    this.applyStyles();
  }

  /**
   * Get component template
   */
  async getTemplate() {
    const template = this.options.template;
    
    if (typeof template === 'function') {
      return template.call(this);
    }
    
    if (typeof template === 'string') {
      // Check if it's a selector
      if (template.startsWith('#') || template.startsWith('.')) {
        const templateEl = document.querySelector(template);
        return templateEl ? templateEl.innerHTML : template;
      }
      return template;
    }
    
    return '';
  }

  /**
   * Compile template with data
   */
  compileTemplate(template) {
    let html = template;
    
    // Replace {{ variable }} with data
    html = html.replace(/\{\{(.+?)\}\}/g, (match, expression) => {
      try {
        const value = this.evaluateExpression(expression.trim());
        return value !== undefined ? value : '';
      } catch (error) {
        console.error('Template compilation error:', error);
        return match;
      }
    });
    
    // Process v-if directives
    html = this.processVIf(html);
    
    // Process v-for directives
    html = this.processVFor(html);
    
    // Process v-show directives
    html = this.processVShow(html);
    
    // Process v-bind directives
    html = this.processVBind(html);
    
    return html;
  }

  /**
   * Evaluate expression in component context
   */
  evaluateExpression(expression) {
    try {
      // Create function with component context
      const fn = new Function(
        '$data',
        '$props',
        '$computed',
        `with($data) { with($props) { with($computed) { return ${expression}; }}}`
      );
      
      return fn(this.$data, this.$props, this.$computed);
    } catch (error) {
      console.error(`Expression evaluation error: ${expression}`, error);
      return undefined;
    }
  }

  /**
   * Process v-if directive
   */
  processVIf(html) {
    const vIfRegex = /<([a-z][a-z0-9]*)[^>]*v-if="([^"]+)"[^>]*>(.*?)<\/\1>/gis;
    
    return html.replace(vIfRegex, (match, tag, condition, content) => {
      const shouldRender = this.evaluateExpression(condition);
      return shouldRender ? match.replace(/ v-if="[^"]+"/, '') : '';
    });
  }

  /**
   * Process v-for directive
   */
  processVFor(html) {
    const vForRegex = /<([a-z][a-z0-9]*)[^>]*v-for="([^"]+)"[^>]*>(.*?)<\/\1>/gis;
    
    return html.replace(vForRegex, (match, tag, forExpression, content) => {
      const [itemVar, listVar] = forExpression.split(' in ').map(s => s.trim());
      const list = this.evaluateExpression(listVar);
      
      if (!Array.isArray(list)) {
        return '';
      }
      
      return list.map((item, index) => {
        let itemHtml = content;
        
        // Replace item variable
        itemHtml = itemHtml.replace(new RegExp(`\\{\\{\\s*${itemVar}\\s*\\}\\}`, 'g'), item);
        itemHtml = itemHtml.replace(new RegExp(`\\{\\{\\s*${itemVar}\\.(\\w+)\\s*\\}\\}`, 'g'), (m, prop) => {
          return item[prop];
        });
        
        return `<${tag}${match.match(/<[^>]+/)[0].substring(tag.length + 1).replace(/ v-for="[^"]+"/, '')}>${itemHtml}</${tag}>`;
      }).join('');
    });
  }

  /**
   * Process v-show directive
   */
  processVShow(html) {
    return html.replace(/v-show="([^"]+)"/g, (match, condition) => {
      const shouldShow = this.evaluateExpression(condition);
      return shouldShow ? '' : 'style="display: none;"';
    });
  }

  /**
   * Process v-bind directive
   */
  processVBind(html) {
    return html.replace(/:(\w+)="([^"]+)"/g, (match, attr, expression) => {
      const value = this.evaluateExpression(expression);
      return `${attr}="${value}"`;
    });
  }

  /**
   * Mount child components
   */
  async mountChildren() {
    const childComponents = this.options.components || {};
    
    for (const [name, componentClass] of Object.entries(childComponents)) {
      const elements = this.$el.querySelectorAll(name);
      
      for (const el of elements) {
        const props = this.extractProps(el);
        const child = new componentClass({ ...this.options, props, parent: this });
        await child.mount(el);
        this.$children.push(child);
      }
    }
  }

  /**
   * Extract props from element attributes
   */
  extractProps(el) {
    const props = {};
    Array.from(el.attributes).forEach(attr => {
      if (attr.name.startsWith(':')) {
        const propName = attr.name.slice(1);
        props[propName] = this.evaluateExpression(attr.value);
      } else {
        props[attr.name] = attr.value;
      }
    });
    return props;
  }

  /**
   * Attach event listeners
   */
  attachEventListeners() {
    const events = this.options.methods || {};
    
    Object.entries(events).forEach(([name, handler]) => {
      const elements = this.$el.querySelectorAll(`[v-on:${name}], [@${name}]`);
      
      elements.forEach(el => {
        el.addEventListener(name, handler.bind(this));
      });
    });
  }

  /**
   * Apply scoped styles
   */
  applyStyles() {
    const styles = this.options.styles;
    
    if (!styles) return;
    
    const scopeId = `data-v-${this.generateId()}`;
    this.$el.setAttribute(scopeId, '');
    
    // Add scoped styles to document
    const styleEl = document.createElement('style');
    styleEl.textContent = styles.replace(/\{/g, `[${scopeId}] {`);
    document.head.appendChild(styleEl);
  }

  /**
   * Schedule component update
   */
  scheduleUpdate() {
    if (this._updateScheduled || !this._isMounted) return;
    
    this._updateScheduled = true;
    
    requestAnimationFrame(() => {
      this.update();
      this._updateScheduled = false;
    });
  }

  /**
   * Update component
   */
  async update() {
    if (!this._isMounted || this._isDestroyed) return;
    
    // Call beforeUpdate hook
    this.callHook('beforeUpdate');
    
    // Re-render
    await this.render();
    
    // Call updated hook
    this.callHook('updated');
  }

  /**
   * Destroy component
   */
  destroy() {
    if (this._isDestroyed) return;
    
    // Call beforeDestroy hook
    this.callHook('beforeDestroy');
    
    // Destroy children
    this.$children.forEach(child => child.destroy());
    this.$children = [];
    
    // Remove event listeners
    this.$listeners.clear();
    
    // Clear watchers
    this.$watchers.clear();
    
    // Remove from parent
    if (this.$parent) {
      const index = this.$parent.$children.indexOf(this);
      if (index > -1) {
        this.$parent.$children.splice(index, 1);
      }
    }
    
    // Mark as destroyed
    this._isDestroyed = true;
    this._isMounted = false;
    
    // Call destroyed hook
    this.callHook('destroyed');
  }

  /**
   * Emit event
   */
  $emit(event, ...args) {
    const listeners = this.$listeners.get(event);
    if (listeners) {
      listeners.forEach(handler => handler(...args));
    }
    
    // Bubble to parent
    if (this.$parent && this.$parent.$emit) {
      this.$parent.$emit(event, ...args);
    }
  }

  /**
   * Listen to event
   */
  $on(event, handler) {
    if (!this.$listeners.has(event)) {
      this.$listeners.set(event, []);
    }
    
    this.$listeners.get(event).push(handler);
    
    return () => this.$off(event, handler);
  }

  /**
   * Remove event listener
   */
  $off(event, handler) {
    const listeners = this.$listeners.get(event);
    if (listeners) {
      const index = listeners.indexOf(handler);
      if (index > -1) {
        listeners.splice(index, 1);
      }
    }
  }

  /**
   * Call lifecycle hook
   */
  callHook(hook) {
    const handler = this.options[hook];
    if (typeof handler === 'function') {
      handler.call(this);
    }
  }

  /**
   * Utility methods
   */
  getNestedValue(obj, path) {
    return path.split('.').reduce((current, prop) => current?.[prop], obj);
  }

  generateId() {
    return Math.random().toString(36).substr(2, 9);
  }

  /**
   * Next tick (wait for DOM update)
   */
  $nextTick(callback) {
    return new Promise(resolve => {
      requestAnimationFrame(() => {
        callback && callback.call(this);
        resolve();
      });
    });
  }
}

/**
 * Create component
 */
export function createComponent(options) {
  return new Component(options);
}

/**
 * Define component
 */
export function defineComponent(options) {
  return class extends Component {
    constructor(props) {
      super({ ...options, props });
    }
  };
}

export default { Component, createComponent, defineComponent };
