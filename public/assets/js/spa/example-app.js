/**
 * Example BaultSPA Application
 * 
 * Demonstrates how to use all features of BaultSPA v3.0
 */

import { animator } from './animations.js';
import { createApp } from './app.js';
import { seo } from './seo.js';

// Define routes
const routes = [
  {
    path: '/',
    name: 'home',
    component: async () => `
      <div class="home-page">
        <h1>Welcome to BaultSPA v3.0</h1>
        <p>Modern SPA framework with all the features you need.</p>
        <a href="/about">Learn More</a>
      </div>
    `,
    meta: {
      title: 'Home - BaultSPA',
      description: 'Modern SPA framework for BaultPHP',
      keywords: 'spa, framework, php, javascript'
    },
    transition: 'fade'
  },
  {
    path: '/about',
    name: 'about',
    lazy: () => import('./pages/about.js'),
    meta: {
      title: 'About - BaultSPA',
      description: 'Learn more about BaultSPA framework'
    },
    transition: 'slide'
  },
  {
    path: '/products',
    name: 'products',
    component: async () => {
      // Fetch products from API
      const products = await fetch('/api/products').then(r => r.json());
      
      return `
        <div class="products-page">
          <h1>Products</h1>
          <div class="product-grid">
            ${products.map(product => `
              <div class="product-card">
                <img data-src="${product.image}" alt="${product.name}" class="lazy">
                <h3>${product.name}</h3>
                <p>${product.description}</p>
                <span class="price">$${product.price}</span>
              </div>
            `).join('')}
          </div>
        </div>
      `;
    },
    meta: {
      title: 'Products - BaultSPA',
      description: 'Browse our amazing products'
    }
  },
  {
    path: '/product/:id',
    name: 'product-detail',
    component: async (route) => {
      const product = await fetch(`/api/products/${route.params.id}`).then(r => r.json());
      
      // Add product structured data
      seo.addStructuredData(seo.createProductStructuredData(product));
      
      return `
        <div class="product-detail">
          <img src="${product.image}" alt="${product.name}">
          <h1>${product.name}</h1>
          <p class="description">${product.description}</p>
          <p class="price">$${product.price}</p>
          <button class="add-to-cart" data-id="${product.id}">Add to Cart</button>
        </div>
      `;
    },
    meta: {
      title: (route) => `${route.params.name} - Products`,
      description: 'View product details'
    }
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('./pages/dashboard.js'),
    guards: ['auth'],
    meta: {
      requiresAuth: true
    }
  }
];

// Define store state and actions
const store = {
  state: {
    user: null,
    cart: [],
    products: [],
    loading: false,
    notifications: []
  },
  
  mutations: {
    SET_USER(state, user) {
      state.user = user;
    },
    
    ADD_TO_CART(state, product) {
      const existing = state.cart.find(item => item.id === product.id);
      if (existing) {
        existing.quantity++;
      } else {
        state.cart.push({ ...product, quantity: 1 });
      }
    },
    
    REMOVE_FROM_CART(state, productId) {
      state.cart = state.cart.filter(item => item.id !== productId);
    },
    
    SET_PRODUCTS(state, products) {
      state.products = products;
    },
    
    SET_LOADING(state, loading) {
      state.loading = loading;
    },
    
    ADD_NOTIFICATION(state, notification) {
      state.notifications.push({
        id: Date.now(),
        ...notification
      });
    },
    
    REMOVE_NOTIFICATION(state, id) {
      state.notifications = state.notifications.filter(n => n.id !== id);
    }
  },
  
  actions: {
    async login({ commit }, credentials) {
      try {
        const user = await fetch('/api/auth/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(credentials)
        }).then(r => r.json());
        
        commit('SET_USER', user);
        return user;
      } catch (error) {
        console.error('Login failed:', error);
        throw error;
      }
    },
    
    async logout({ commit }) {
      await fetch('/api/auth/logout', { method: 'POST' });
      commit('SET_USER', null);
    },
    
    async fetchProducts({ commit }) {
      commit('SET_LOADING', true);
      try {
        const products = await fetch('/api/products').then(r => r.json());
        commit('SET_PRODUCTS', products);
      } finally {
        commit('SET_LOADING', false);
      }
    },
    
    addToCart({ commit, state }, product) {
      commit('ADD_TO_CART', product);
      commit('ADD_NOTIFICATION', {
        type: 'success',
        message: `${product.name} added to cart`
      });
    }
  },
  
  persist: {
    key: 'bault-app-state',
    storage: 'local',
    paths: ['user', 'cart']
  }
};

// Define route guards
const guards = {
  auth: async (to, from) => {
    const user = window.$store.state.user;
    
    if (!user) {
      // Redirect to login
      window.$router.push('/login');
      return false;
    }
    
    return true;
  },
  
  guest: async (to, from) => {
    const user = window.$store.state.user;
    
    if (user) {
      // Already logged in, redirect to dashboard
      window.$router.push('/dashboard');
      return false;
    }
    
    return true;
  }
};

// Create and initialize app
const app = createApp({
  debug: true, // Enable debug mode
  
  router: {
    routes,
    guards,
    baseUrl: '',
    mode: 'history',
    transition: 'fade',
    
    beforeEach: async (to, from) => {
      console.log('Navigating to:', to.path);
      
      // Show loading indicator
      document.body.classList.add('route-loading');
      
      return true;
    },
    
    afterEach: async (to) => {
      console.log('Navigation complete:', to.path);
      
      // Hide loading indicator
      document.body.classList.remove('route-loading');
      
      // Scroll to top
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  },
  
  store,
  
  serviceWorker: {
    precache: [
      '/',
      '/assets/css/app.css',
      '/assets/js/app.js',
      '/assets/images/logo.png'
    ]
  },
  
  seo: {
    default: {
      siteName: 'BaultSPA Demo',
      title: 'BaultSPA - Modern SPA Framework',
      description: 'Build amazing single page applications with BaultSPA',
      image: '/assets/images/og-image.jpg',
      url: window.location.origin,
      locale: 'en_US',
      twitterCard: 'summary_large_image'
    },
    structuredData: {
      '@context': 'https://schema.org',
      '@type': 'WebApplication',
      name: 'BaultSPA',
      description: 'Modern SPA framework for BaultPHP',
      applicationCategory: 'DeveloperApplication',
      operatingSystem: 'Any'
    }
  }
});

// Initialize app when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    app.init();
  });
} else {
  app.init();
}

// Listen to app events
window.addEventListener('app:ready', (event) => {
  console.log('App ready!', event.detail.metrics);
  
  // Add entrance animation
  animator.fadeIn(document.querySelector('#app-content'));
});

window.addEventListener('router:navigated', (event) => {
  console.log('Page navigated:', event.detail);
});

// Example: Add to cart functionality
document.addEventListener('click', (event) => {
  if (event.target.classList.contains('add-to-cart')) {
    const productId = event.target.dataset.id;
    const product = window.$store.state.products.find(p => p.id === productId);
    
    if (product) {
      window.$store.dispatch('addToCart', product);
      
      // Animate button
      animator.bounce(event.target);
    }
  }
});

export default app;
