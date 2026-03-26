/**
 * BaultSPA + Page Blocks Integration Example
 * 
 * Demonstrates how to use SPA framework with existing page block system
 */

import { createApp } from './assets/js/spa/app.js';
import { BlockBridge, loadPageRoutes } from './assets/js/spa/block-bridge.js';
import { seo } from './assets/js/spa/seo.js';

// Initialize Block Bridge
const blockBridge = new BlockBridge({
  apiEndpoint: '/api/pages',
  cacheBlocks: true,
  cacheDuration: 300000, // 5 minutes
  enableInlineEdit: true
});

// Define custom SPA routes (không phải page blocks)
const customRoutes = [
  {
    path: '/spa/dashboard',
    name: 'spa-dashboard',
    component: async () => `
      <div class="dashboard">
        <h1>SPA Dashboard</h1>
        <p>This is a custom SPA route (not from page blocks)</p>
        <div class="stats">
          <div class="stat-card">
            <h3>Users</h3>
            <p class="stat-number">1,234</p>
          </div>
          <div class="stat-card">
            <h3>Pages</h3>
            <p class="stat-number">56</p>
          </div>
          <div class="stat-card">
            <h3>Blocks</h3>
            <p class="stat-number">89</p>
          </div>
        </div>
      </div>
    `,
    meta: {
      title: 'Dashboard',
      requiresAuth: true
    }
  },
  {
    path: '/spa/about-spa',
    name: 'about-spa',
    component: () => `
      <div class="about-spa">
        <h1>About BaultSPA</h1>
        <p>This demonstrates SPA working with page blocks!</p>
        <ul>
          <li>✅ Page blocks work normally</li>
          <li>✅ SPA navigation is fast</li>
          <li>✅ Inline editing still works</li>
          <li>✅ Both systems integrate seamlessly</li>
        </ul>
      </div>
    `,
    meta: {
      title: 'About SPA'
    }
  }
];

// Main app initialization
async function initializeApp() {
  console.log('🚀 Initializing BaultSPA with Page Blocks...');

  try {
    // Load all page routes from block system
    console.log('📦 Loading page routes from block system...');
    const pageRoutes = await loadPageRoutes(blockBridge);
    console.log(`✅ Loaded ${pageRoutes.length} page routes`);

    // Combine with custom SPA routes
    const allRoutes = [...pageRoutes, ...customRoutes];

    // Create app with combined routes
    const app = createApp({
      debug: true,

      router: {
        routes: allRoutes,
        
        beforeEach: async (to, from) => {
          console.log(`Navigating to: ${to.path}`);
          
          // Show loading for block pages
          if (to.meta && to.meta.isBlockPage) {
            document.body.classList.add('loading-blocks');
          }
          
          return true;
        },
        
        afterEach: async (to) => {
          console.log(`Navigation complete: ${to.path}`);
          
          // Hide loading
          document.body.classList.remove('loading-blocks');
          
          // Update SEO for block pages
          if (to.meta && to.meta.isBlockPage) {
            // SEO meta will be handled by page data
            const pageSlug = to.path.replace('/', '') || 'home';
            try {
              const pageData = await blockBridge.loadPage(pageSlug);
              if (pageData && pageData.meta) {
                seo.updateMeta({
                  title: pageData.meta.title,
                  description: pageData.meta.description,
                  keywords: pageData.meta.keywords,
                  url: window.location.href
                });
              }
            } catch (e) {
              console.error('Failed to update SEO', e);
            }
          }
          
          // Re-initialize inline editor if in edit mode
          if (window.blockEditor && window.blockEditor.editMode) {
            setTimeout(() => {
              window.blockEditor.enableEditMode();
            }, 100);
          }
        }
      },

      store: {
        state: {
          user: null,
          editMode: false,
          currentPage: null
        },
        
        mutations: {
          SET_USER(state, user) {
            state.user = user;
          },
          
          SET_EDIT_MODE(state, mode) {
            state.editMode = mode;
          },
          
          SET_CURRENT_PAGE(state, page) {
            state.currentPage = page;
          }
        },
        
        actions: {
          async loadCurrentUser({ commit }) {
            try {
              const response = await fetch('/api/user');
              const user = await response.json();
              commit('SET_USER', user);
            } catch (error) {
              console.error('Failed to load user', error);
            }
          },
          
          toggleEditMode({ commit, state }) {
            const newMode = !state.editMode;
            commit('SET_EDIT_MODE', newMode);
            
            if (window.blockEditor) {
              if (newMode) {
                window.blockEditor.enableEditMode();
              } else {
                window.blockEditor.disableEditMode();
              }
            }
          }
        }
      },

      serviceWorker: {
        precache: [
          '/',
          '/assets/css/app.css',
          '/assets/css/spa.css',
          '/assets/js/spa/app.js'
        ]
      },

      seo: {
        default: {
          siteName: 'BaultPHP',
          title: 'BaultPHP - Modern PHP Framework',
          description: 'SPA integrated with Page Block System',
          locale: 'en_US'
        }
      }
    });

    // Integrate block bridge with router
    blockBridge.integrateWithRouter(app.router);

    // Make block bridge globally available
    window.$blockBridge = blockBridge;

    // Initialize app
    await app.init();

    console.log('✅ BaultSPA with Page Blocks initialized successfully!');

    // Listen for block updates
    window.addEventListener('region:reloaded', (event) => {
      console.log('Region reloaded:', event.detail);
    });

    // Add keyboard shortcut for edit mode (Ctrl+E)
    document.addEventListener('keydown', (e) => {
      if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        window.$store.dispatch('toggleEditMode');
      }
    });

    return app;

  } catch (error) {
    console.error('❌ Failed to initialize app:', error);
    
    // Show error to user
    document.getElementById('app-content').innerHTML = `
      <div class="init-error">
        <h1>Failed to Load Application</h1>
        <p>${error.message}</p>
        <button onclick="location.reload()">Retry</button>
      </div>
    `;
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeApp);
} else {
  initializeApp();
}

// Export for debugging
window.initializeApp = initializeApp;
