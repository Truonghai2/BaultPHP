# BaultSPA v3.0 - Modern SPA Framework

## 📋 Tổng quan

BaultSPA v3.0 là một framework SPA hiện đại, nhẹ và mạnh mẽ được xây dựng cho BaultPHP. Framework cung cấp đầy đủ các tính năng cần thiết để xây dựng ứng dụng Single Page Application với performance cao và trải nghiệm người dùng tốt nhất.

## ✨ Tính năng chính

### 1. **Router** (`router.js`)
- ✅ Dynamic route matching với parameters
- ✅ Lazy loading components
- ✅ Route guards (authentication, permissions)
- ✅ Nested routes support
- ✅ Query parameters handling
- ✅ Route transitions
- ✅ History management (HTML5 History API)
- ✅ Middleware support
- ✅ Named routes
- ✅ Route groups

### 2. **State Management** (`store.js`)
- ✅ Reactive state với Proxy
- ✅ Actions và mutations
- ✅ Computed properties
- ✅ Middleware support
- ✅ Time-travel debugging
- ✅ Persistence (localStorage/sessionStorage)
- ✅ Module system
- ✅ Watchers
- ✅ Event emitter

### 3. **Component System** (`component.js`)
- ✅ Lifecycle hooks (beforeCreate, created, beforeMount, mounted, beforeUpdate, updated, beforeDestroy, destroyed)
- ✅ Props và reactive data
- ✅ Computed properties
- ✅ Watchers
- ✅ Event emitters ($emit, $on, $off)
- ✅ Template compilation
- ✅ Directives (v-if, v-for, v-show, v-bind, v-on)
- ✅ Scoped styles
- ✅ Component composition

### 4. **Animations** (`animations.js`)
- ✅ Web Animations API wrapper
- ✅ Pre-defined animations (fade, slide, scale, bounce, shake, pulse, flip)
- ✅ Animation sequences
- ✅ Parallel animations
- ✅ Stagger animations
- ✅ Custom easing functions
- ✅ Performance optimized

### 5. **Lazy Loading** (`lazy-load.js`)
- ✅ Intersection Observer API
- ✅ Progressive image loading
- ✅ Responsive images (srcset)
- ✅ WebP support detection
- ✅ Blur placeholder
- ✅ Loading states
- ✅ Error handling
- ✅ Background image lazy loading

### 6. **Service Worker** (`service-worker-manager.js`)
- ✅ Offline support
- ✅ Cache strategies (CacheFirst, NetworkFirst, StaleWhileRevalidate)
- ✅ Background sync
- ✅ Push notifications
- ✅ Update notifications
- ✅ Resource precaching
- ✅ Network status monitoring

### 7. **SEO Optimization** (`seo.js`)
- ✅ Dynamic meta tags
- ✅ Open Graph tags
- ✅ Twitter Cards
- ✅ Structured data (JSON-LD)
- ✅ Canonical URLs
- ✅ Multiple language support
- ✅ Robot meta tags
- ✅ DNS prefetch
- ✅ Resource preload
- ✅ Analytics integration

### 8. **Performance Monitoring** (`performance-monitor.js`)
- ✅ Real-time performance metrics
- ✅ Core Web Vitals (LCP, FID, CLS)
- ✅ Resource timing
- ✅ Long tasks detection
- ✅ Memory usage tracking
- ✅ Custom metrics
- ✅ Performance dashboard UI
- ✅ Server reporting
- ✅ Performance scoring

## 🚀 Cài đặt và sử dụng

### Bước 1: Import các modules

```javascript
import { createApp } from './spa/app.js';
```

### Bước 2: Định nghĩa routes

```javascript
const routes = [
  {
    path: '/',
    name: 'home',
    component: async () => `<div>Home Page</div>`,
    meta: {
      title: 'Home',
      description: 'Home page description'
    }
  },
  {
    path: '/about',
    name: 'about',
    lazy: () => import('./pages/about.js'),
    meta: {
      title: 'About Us'
    }
  },
  {
    path: '/products/:id',
    name: 'product',
    component: async (route) => {
      const id = route.params.id;
      // Load product data
      return `<div>Product ${id}</div>`;
    }
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    guards: ['auth'],
    component: () => import('./pages/dashboard.js')
  }
];
```

### Bước 3: Định nghĩa store

```javascript
const store = {
  state: {
    user: null,
    cart: [],
    loading: false
  },
  
  mutations: {
    SET_USER(state, user) {
      state.user = user;
    },
    
    ADD_TO_CART(state, product) {
      state.cart.push(product);
    }
  },
  
  actions: {
    async login({ commit }, credentials) {
      const user = await fetch('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify(credentials)
      }).then(r => r.json());
      
      commit('SET_USER', user);
      return user;
    }
  },
  
  persist: {
    key: 'app-state',
    storage: 'local',
    paths: ['user', 'cart']
  }
};
```

### Bước 4: Khởi tạo app

```javascript
const app = createApp({
  debug: true, // Enable development mode
  
  router: {
    routes,
    guards: {
      auth: async (to, from) => {
        const user = window.$store.state.user;
        if (!user) {
          window.$router.push('/login');
          return false;
        }
        return true;
      }
    },
    beforeEach: async (to, from) => {
      console.log('Navigating to:', to.path);
      return true;
    },
    afterEach: async (to) => {
      console.log('Navigation complete');
    }
  },
  
  store,
  
  serviceWorker: {
    precache: [
      '/',
      '/assets/css/app.css',
      '/assets/js/app.js'
    ]
  },
  
  seo: {
    default: {
      siteName: 'My App',
      title: 'My Amazing App',
      description: 'App description',
      image: '/og-image.jpg'
    }
  }
});

// Initialize
app.init();
```

## 📚 API Documentation

### Router API

#### Navigate to route
```javascript
// By path
$router.push('/about');

// By name
$router.pushByName('product', { id: 123 }, { sort: 'price' });

// Replace current route
$router.replace('/dashboard');

// Go back
$router.back();
```

#### Define routes
```javascript
router.route('/path', {
  component: () => {},
  lazy: () => import('./component.js'),
  guards: ['auth'],
  meta: { title: 'Page Title' },
  transition: 'fade'
});
```

#### Route groups
```javascript
router.group('/admin', (router) => {
  router.route('/users', { /* config */ });
  router.route('/settings', { /* config */ });
}, {
  middleware: [authMiddleware]
});
```

### Store API

#### Access state
```javascript
// Read state
const user = $store.state.user;

// Commit mutation
$store.commit('SET_USER', userData);

// Dispatch action
await $store.dispatch('login', credentials);
```

#### Subscribe to changes
```javascript
const unsubscribe = $store.subscribe('user', (newValue, oldValue) => {
  console.log('User changed:', newValue);
});

// Unsubscribe
unsubscribe();
```

#### Time travel
```javascript
// Undo
$store.undo();

// Redo
$store.redo();

// Travel to specific state
$store.travelTo(5);
```

### Component API

#### Create component
```javascript
import { createComponent } from './spa/component.js';

const counter = createComponent({
  data() {
    return {
      count: 0
    };
  },
  
  computed: {
    double() {
      return this.$data.count * 2;
    }
  },
  
  watch: {
    count(newValue, oldValue) {
      console.log(`Count changed: ${oldValue} -> ${newValue}`);
    }
  },
  
  methods: {
    increment() {
      this.$data.count++;
    }
  },
  
  template: `
    <div>
      <p>Count: {{ count }}</p>
      <p>Double: {{ double }}</p>
      <button @click="increment">Increment</button>
    </div>
  `,
  
  created() {
    console.log('Component created');
  },
  
  mounted() {
    console.log('Component mounted');
  }
});

// Mount component
counter.mount('#app');
```

### Animations API

```javascript
import { animator } from './spa/animations.js';

// Pre-defined animations
await animator.fadeIn(element);
await animator.slideIn(element, 'left');
await animator.scale(element, 0, 1);
await animator.bounce(element);
await animator.shake(element);

// Custom animation
await animator.animate(element, [
  { transform: 'translateX(0)' },
  { transform: 'translateX(100px)' }
], {
  duration: 500,
  easing: 'ease-out'
});

// Stagger animations
await animator.stagger(elements, (el) => animator.fadeIn(el), 100);
```

### Lazy Loading API

```html
<!-- Lazy load image -->
<img data-src="image.jpg" data-srcset="image-320w.jpg 320w, image-640w.jpg 640w" alt="Description" class="lazy">

<!-- Lazy load background -->
<div data-bg="background.jpg" class="lazy-bg"></div>

<!-- WebP support -->
<img data-src="image.jpg" data-src-webp="image.webp" alt="Description" class="lazy">
```

### SEO API

```javascript
import { seo } from './spa/seo.js';

// Update meta tags
seo.updateMeta({
  title: 'Page Title',
  description: 'Page description',
  keywords: 'keyword1, keyword2',
  image: 'og-image.jpg',
  url: window.location.href
});

// Add structured data
seo.addStructuredData({
  '@context': 'https://schema.org',
  '@type': 'Article',
  headline: 'Article Title',
  author: { '@type': 'Person', name: 'Author Name' }
});

// Preload resources
seo.preload([
  { url: '/critical.css', as: 'style' },
  { url: '/font.woff2', as: 'font', crossorigin: 'anonymous' }
]);
```

### Performance Monitoring API

```javascript
import { performanceMonitor } from './spa/performance-monitor.js';

// Custom marks
performanceMonitor.mark('feature-start');
// ... do something
performanceMonitor.mark('feature-end');

// Measure
performanceMonitor.measure('feature-duration', 'feature-start', 'feature-end');

// Track custom metric
performanceMonitor.trackMetric('api-calls', 15);

// Get metrics
const metrics = performanceMonitor.getMetrics();

// Get summary
const summary = performanceMonitor.getSummary();

// Show dashboard
performanceMonitor.createDashboard();
```

## 🎨 Styling và Animations

Import CSS file:

```html
<link rel="stylesheet" href="/assets/css/spa.css">
```

Các transitions có sẵn:
- `fade` - Fade in/out
- `slide` - Slide in/out
- `scale` - Scale in/out

Utility classes:
- `lazy-loading` - Loading state
- `lazy-loaded` - Loaded state
- `lazy-error` - Error state
- `animate-bounce` - Bounce animation
- `animate-shake` - Shake animation
- `animate-pulse` - Pulse animation

## 🔧 Configuration

### Router Configuration
```javascript
{
  baseUrl: '',
  mode: 'history', // or 'hash'
  transition: 'fade',
  beforeEach: (to, from) => {},
  afterEach: (to) => {},
  onNotFound: (url) => {}
}
```

### Store Configuration
```javascript
{
  state: {},
  mutations: {},
  actions: {},
  modules: {},
  persist: {
    key: 'app-state',
    storage: 'local', // or 'session'
    paths: ['user', 'cart'] // only persist specific paths
  },
  strict: true, // enable strict mode
  maxHistory: 50 // max history states for time-travel
}
```

### Service Worker Configuration
```javascript
{
  swPath: '/sw.js',
  scope: '/',
  precache: ['/','/ assets/css/app.css'],
  onUpdateFound: () => {},
  onUpdateReady: () => {},
  onOffline: () => {},
  onOnline: () => {}
}
```

### Performance Monitor Configuration
```javascript
{
  enabled: true,
  sampleRate: 1, // 100%
  reportInterval: 30000, // 30 seconds
  endpoint: '/api/metrics',
  enableReporting: true
}
```

## 📊 Performance Best Practices

### 1. Lazy Loading
- Lazy load routes với `lazy: () => import()`
- Lazy load images với `data-src`
- Sử dụng WebP images khi có thể

### 2. Code Splitting
```javascript
// Thay vì
import HeavyComponent from './heavy.js';

// Sử dụng
const HeavyComponent = () => import('./heavy.js');
```

### 3. Caching
- Sử dụng Service Worker để cache resources
- Enable persistent state cho store
- Cache API responses

### 4. Optimizations
- Minimize reflows/repaints
- Use CSS transforms thay vì position properties
- Debounce/throttle event handlers
- Use passive event listeners

## 🔒 Security Best Practices

### 1. XSS Prevention
- Template engine tự động escape HTML
- Sử dụng CSP headers
- Validate user input

### 2. CSRF Protection
- Include CSRF token trong requests
- Verify tokens trên server

### 3. Authentication
- Store tokens securely
- Implement route guards
- Refresh tokens periodically

## 🧪 Testing

```javascript
// Example test
import { createStore } from './spa/store.js';

describe('Store', () => {
  it('should commit mutation', () => {
    const store = createStore({
      state: { count: 0 },
      mutations: {
        INCREMENT(state) {
          state.count++;
        }
      }
    });
    
    store.commit('INCREMENT');
    expect(store.state.count).toBe(1);
  });
});
```

## 🐛 Debugging

Enable debug mode:
```javascript
const app = createApp({
  debug: true
});
```

Access dev tools:
```javascript
// Store dev tools
window.$store.enableDevTools();

// Access in console
window.$store.state
window.$router
```

## 📱 Browser Support

- Chrome/Edge: Latest 2 versions
- Firefox: Latest 2 versions
- Safari: Latest 2 versions
- Mobile browsers: iOS Safari 12+, Chrome Mobile

## 🚀 Migration từ v2.0

### Breaking Changes
1. Router API đã thay đổi - sử dụng `Router` class thay vì object
2. Store sử dụng Proxy thay vì Object.defineProperty
3. Component lifecycle hooks đã được đổi tên

### Migration Steps
1. Update router initialization
2. Update store configuration
3. Update component definitions
4. Test thoroughly

## 📖 Examples

Xem `example-app.js` để có ví dụ đầy đủ về cách sử dụng tất cả features.

## 🤝 Contributing

Contributions are welcome! Please follow the coding style and add tests for new features.

## 📝 License

MIT License

## 📞 Support

- Documentation: `/docs/SPA_FRAMEWORK_V3.md`
- Issues: GitHub Issues
- Email: support@baultphp.com

---

**BaultSPA v3.0** - Built with ❤️ for modern web applications
