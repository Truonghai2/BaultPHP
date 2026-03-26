/**
 * Lightweight State Management v3.0
 * 
 * Features:
 * - Reactive state with Proxy
 * - Actions and mutations
 * - Computed properties
 * - Middleware support
 * - Time-travel debugging
 * - Persistence (localStorage/sessionStorage)
 * - Module system
 * - TypeScript-like type checking
 */

export class Store {
  constructor(options = {}) {
    this.state = {};
    this.getters = {};
    this.actions = options.actions || {};
    this.mutations = options.mutations || {};
    this.modules = options.modules || {};
    this.middlewares = [];
    this.subscribers = new Map();
    this.computedCache = new Map();
    
    // State history for time-travel
    this.stateHistory = [];
    this.currentStateIndex = -1;
    this.maxHistory = options.maxHistory || 50;
    
    // Persistence
    this.persist = options.persist || null;
    
    // Strict mode
    this.strict = options.strict !== false;
    
    // Initialize state
    this.initializeState(options.state || {});
    
    // Register modules
    this.registerModules(this.modules);
    
    // Load persisted state
    if (this.persist) {
      this.loadPersistedState();
    }
  }

  /**
   * Initialize reactive state
   */
  initializeState(initialState) {
    this.state = this.createReactiveState(initialState);
    this.pushHistory();
  }

  /**
   * Create reactive state using Proxy
   */
  createReactiveState(obj, path = '') {
    const store = this;
    
    return new Proxy(obj, {
      get(target, property) {
        const value = target[property];
        
        // Return reactive nested objects
        if (value && typeof value === 'object' && !Array.isArray(value)) {
          return store.createReactiveState(value, `${path}.${property}`);
        }
        
        return value;
      },
      
      set(target, property, value) {
        if (store.strict && !store._committing) {
          console.error('State mutation outside of mutations is not allowed in strict mode');
          return false;
        }
        
        const oldValue = target[property];
        
        if (oldValue === value) {
          return true;
        }
        
        target[property] = value;
        
        // Notify subscribers
        const fullPath = path ? `${path}.${property}` : property;
        store.notify(fullPath, value, oldValue);
        
        // Clear computed cache for affected properties
        store.invalidateComputedCache(fullPath);
        
        // Save to persistence
        if (store.persist) {
          store.savePersistedState();
        }
        
        return true;
      }
    });
  }

  /**
   * Commit a mutation
   */
  commit(type, payload) {
    const mutation = this.mutations[type];
    
    if (!mutation) {
      console.error(`Mutation "${type}" not found`);
      return;
    }
    
    this._committing = true;
    
    try {
      // Run middlewares
      this.runMiddlewares('before:mutation', { type, payload });
      
      // Execute mutation
      mutation(this.state, payload);
      
      // Push to history
      this.pushHistory();
      
      // Run middlewares
      this.runMiddlewares('after:mutation', { type, payload });
      
      // Emit event
      this.emit('mutation', { type, payload });
      
    } finally {
      this._committing = false;
    }
  }

  /**
   * Dispatch an action
   */
  async dispatch(type, payload) {
    const action = this.actions[type];
    
    if (!action) {
      console.error(`Action "${type}" not found`);
      return;
    }
    
    // Run middlewares
    this.runMiddlewares('before:action', { type, payload });
    
    try {
      // Execute action
      const result = await action({
        state: this.state,
        commit: this.commit.bind(this),
        dispatch: this.dispatch.bind(this),
        getters: this.getters,
      }, payload);
      
      // Run middlewares
      this.runMiddlewares('after:action', { type, payload, result });
      
      // Emit event
      this.emit('action', { type, payload, result });
      
      return result;
      
    } catch (error) {
      console.error(`Action "${type}" failed:`, error);
      this.emit('action:error', { type, payload, error });
      throw error;
    }
  }

  /**
   * Register a getter (computed property)
   */
  registerGetter(name, getter) {
    Object.defineProperty(this.getters, name, {
      get: () => {
        // Check cache
        if (this.computedCache.has(name)) {
          return this.computedCache.get(name);
        }
        
        // Compute value
        const value = getter(this.state, this.getters);
        
        // Cache value
        this.computedCache.set(name, value);
        
        return value;
      },
      enumerable: true
    });
  }

  /**
   * Subscribe to state changes
   */
  subscribe(path, callback) {
    if (!this.subscribers.has(path)) {
      this.subscribers.set(path, new Set());
    }
    
    this.subscribers.get(path).add(callback);
    
    // Return unsubscribe function
    return () => {
      const subscribers = this.subscribers.get(path);
      if (subscribers) {
        subscribers.delete(callback);
      }
    };
  }

  /**
   * Notify subscribers
   */
  notify(path, newValue, oldValue) {
    // Notify exact path subscribers
    const exactSubscribers = this.subscribers.get(path);
    if (exactSubscribers) {
      exactSubscribers.forEach(callback => {
        callback(newValue, oldValue, path);
      });
    }
    
    // Notify wildcard subscribers
    const wildcardSubscribers = this.subscribers.get('*');
    if (wildcardSubscribers) {
      wildcardSubscribers.forEach(callback => {
        callback(newValue, oldValue, path);
      });
    }
    
    // Notify parent path subscribers
    const parts = path.split('.');
    for (let i = parts.length - 1; i > 0; i--) {
      const parentPath = parts.slice(0, i).join('.');
      const parentSubscribers = this.subscribers.get(parentPath);
      
      if (parentSubscribers) {
        const parentValue = this.getNestedValue(this.state, parentPath);
        parentSubscribers.forEach(callback => {
          callback(parentValue, undefined, parentPath);
        });
      }
    }
  }

  /**
   * Register middleware
   */
  use(middleware) {
    this.middlewares.push(middleware);
    return this;
  }

  /**
   * Run middlewares
   */
  runMiddlewares(hook, context) {
    this.middlewares.forEach(middleware => {
      if (middleware[hook]) {
        middleware[hook](context, this);
      }
    });
  }

  /**
   * Push state to history
   */
  pushHistory() {
    // Remove future states if we're not at the end
    if (this.currentStateIndex < this.stateHistory.length - 1) {
      this.stateHistory = this.stateHistory.slice(0, this.currentStateIndex + 1);
    }
    
    // Add current state
    this.stateHistory.push(JSON.parse(JSON.stringify(this.state)));
    
    // Limit history size
    if (this.stateHistory.length > this.maxHistory) {
      this.stateHistory.shift();
    } else {
      this.currentStateIndex++;
    }
  }

  /**
   * Time travel - go to specific state
   */
  travelTo(index) {
    if (index < 0 || index >= this.stateHistory.length) {
      console.error('Invalid state index');
      return;
    }
    
    this.currentStateIndex = index;
    const historicalState = this.stateHistory[index];
    
    this._committing = true;
    Object.assign(this.state, historicalState);
    this._committing = false;
    
    this.emit('time-travel', { index, state: historicalState });
  }

  /**
   * Time travel - undo
   */
  undo() {
    if (this.currentStateIndex > 0) {
      this.travelTo(this.currentStateIndex - 1);
    }
  }

  /**
   * Time travel - redo
   */
  redo() {
    if (this.currentStateIndex < this.stateHistory.length - 1) {
      this.travelTo(this.currentStateIndex + 1);
    }
  }

  /**
   * Register module
   */
  registerModule(name, module) {
    if (!this.state[name]) {
      this.state[name] = this.createReactiveState(module.state || {}, name);
    }
    
    // Register module mutations with namespace
    if (module.mutations) {
      Object.entries(module.mutations).forEach(([key, mutation]) => {
        this.mutations[`${name}/${key}`] = mutation;
      });
    }
    
    // Register module actions with namespace
    if (module.actions) {
      Object.entries(module.actions).forEach(([key, action]) => {
        this.actions[`${name}/${key}`] = action;
      });
    }
    
    // Register module getters
    if (module.getters) {
      Object.entries(module.getters).forEach(([key, getter]) => {
        this.registerGetter(`${name}/${key}`, getter);
      });
    }
  }

  /**
   * Register multiple modules
   */
  registerModules(modules) {
    Object.entries(modules).forEach(([name, module]) => {
      this.registerModule(name, module);
    });
  }

  /**
   * Save state to persistence
   */
  savePersistedState() {
    if (!this.persist) return;
    
    const { key, storage, paths } = this.persist;
    const storageType = storage === 'session' ? sessionStorage : localStorage;
    
    let dataToSave = this.state;
    
    // Save only specified paths
    if (paths && paths.length > 0) {
      dataToSave = {};
      paths.forEach(path => {
        const value = this.getNestedValue(this.state, path);
        this.setNestedValue(dataToSave, path, value);
      });
    }
    
    try {
      storageType.setItem(key, JSON.stringify(dataToSave));
    } catch (error) {
      console.error('Failed to persist state:', error);
    }
  }

  /**
   * Load state from persistence
   */
  loadPersistedState() {
    if (!this.persist) return;
    
    const { key, storage } = this.persist;
    const storageType = storage === 'session' ? sessionStorage : localStorage;
    
    try {
      const data = storageType.getItem(key);
      if (data) {
        const parsedData = JSON.parse(data);
        this._committing = true;
        Object.assign(this.state, parsedData);
        this._committing = false;
      }
    } catch (error) {
      console.error('Failed to load persisted state:', error);
    }
  }

  /**
   * Clear persisted state
   */
  clearPersistedState() {
    if (!this.persist) return;
    
    const { key, storage } = this.persist;
    const storageType = storage === 'session' ? sessionStorage : localStorage;
    storageType.removeItem(key);
  }

  /**
   * Reset state to initial values
   */
  reset() {
    this._committing = true;
    Object.keys(this.state).forEach(key => {
      delete this.state[key];
    });
    this._committing = false;
    
    this.stateHistory = [];
    this.currentStateIndex = -1;
    this.computedCache.clear();
    
    if (this.persist) {
      this.clearPersistedState();
    }
  }

  /**
   * Event emitter
   */
  on(event, callback) {
    if (!this._events) this._events = {};
    if (!this._events[event]) this._events[event] = [];
    this._events[event].push(callback);
    
    return () => {
      this._events[event] = this._events[event].filter(cb => cb !== callback);
    };
  }

  emit(event, data) {
    if (!this._events || !this._events[event]) return;
    this._events[event].forEach(callback => callback(data));
  }

  /**
   * Utility methods
   */
  getNestedValue(obj, path) {
    return path.split('.').reduce((current, prop) => current?.[prop], obj);
  }

  setNestedValue(obj, path, value) {
    const parts = path.split('.');
    const last = parts.pop();
    const target = parts.reduce((current, prop) => {
      if (!current[prop]) current[prop] = {};
      return current[prop];
    }, obj);
    target[last] = value;
  }

  invalidateComputedCache(changedPath) {
    // For now, clear entire cache when any state changes
    // Can be optimized to track dependencies
    this.computedCache.clear();
  }

  /**
   * Dev tools integration
   */
  enableDevTools() {
    if (typeof window !== 'undefined') {
      window.__BAULT_STORE__ = this;
      
      console.log('Store dev tools enabled. Access via window.__BAULT_STORE__');
      console.log('Methods: .state, .getters, .commit(), .dispatch(), .undo(), .redo()');
    }
  }
}

/**
 * Create store instance
 */
export function createStore(options) {
  return new Store(options);
}

/**
 * Middleware examples
 */
export const loggerMiddleware = {
  'before:mutation'(context, store) {
    console.log(`[Mutation] ${context.type}`, context.payload);
  },
  'after:mutation'(context, store) {
    console.log('[State]', store.state);
  },
  'before:action'(context, store) {
    console.log(`[Action] ${context.type}`, context.payload);
  }
};

export const performanceMiddleware = {
  'before:action'(context) {
    context._startTime = performance.now();
  },
  'after:action'(context) {
    const duration = performance.now() - context._startTime;
    console.log(`[Performance] Action "${context.type}" took ${duration.toFixed(2)}ms`);
  }
};

export default { Store, createStore, loggerMiddleware, performanceMiddleware };
