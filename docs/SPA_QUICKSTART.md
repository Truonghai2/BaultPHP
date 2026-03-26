# BaultSPA v3.0 - Quick Start Guide

## 🚀 Bắt đầu trong 5 phút

### 1. Include Files

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My BaultSPA App</title>
    
    <!-- SPA Styles -->
    <link rel="stylesheet" href="/assets/css/spa.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <!-- App Container -->
    <div id="app-content">
        <!-- Content will be loaded here -->
    </div>
    
    <!-- App Script -->
    <script type="module" src="/assets/js/my-app.js"></script>
</body>
</html>
```

### 2. Create App File (`my-app.js`)

```javascript
import { createApp } from './spa/app.js';

// Define routes
const routes = [
  {
    path: '/',
    name: 'home',
    component: async () => `
      <div class="home">
        <h1>Welcome to BaultSPA v3.0!</h1>
        <p>A modern SPA framework.</p>
        <a href="/about">Learn More</a>
      </div>
    `,
    meta: {
      title: 'Home - My App',
      description: 'Welcome to my awesome app'
    }
  },
  {
    path: '/about',
    name: 'about',
    component: async () => `
      <div class="about">
        <h1>About Us</h1>
        <p>This is the about page.</p>
        <a href="/">Back to Home</a>
      </div>
    `,
    meta: {
      title: 'About - My App'
    }
  }
];

// Create app
const app = createApp({
  router: {
    routes
  }
});

// Initialize
app.init();
```

### 3. Đó là tất cả! 🎉

Mở browser và navigate qua các routes. SPA đã hoạt động!

---

## 📚 Các ví dụ thực tế

### Example 1: Blog App

```javascript
import { createApp } from './spa/app.js';
import { seo } from './spa/seo.js';

const routes = [
  {
    path: '/',
    component: async () => {
      const posts = await fetch('/api/posts').then(r => r.json());
      return `
        <div class="blog-home">
          <h1>My Blog</h1>
          ${posts.map(post => `
            <article>
              <h2><a href="/post/${post.id}">${post.title}</a></h2>
              <p>${post.excerpt}</p>
            </article>
          `).join('')}
        </div>
      `;
    }
  },
  {
    path: '/post/:id',
    component: async (route) => {
      const post = await fetch(`/api/posts/${route.params.id}`).then(r => r.json());
      
      // Add structured data
      seo.addStructuredData(seo.createArticleStructuredData({
        title: post.title,
        description: post.excerpt,
        image: post.image,
        author: post.author,
        publishedAt: post.created_at
      }));
      
      return `
        <article class="blog-post">
          <img src="${post.image}" alt="${post.title}">
          <h1>${post.title}</h1>
          <div class="meta">
            By ${post.author} on ${new Date(post.created_at).toLocaleDateString()}
          </div>
          <div class="content">${post.content}</div>
        </article>
      `;
    },
    meta: {
      title: (route) => `${route.params.title} - My Blog`
    }
  }
];

createApp({ router: { routes } }).init();
```

### Example 2: E-commerce với State Management

```javascript
import { createApp } from './spa/app.js';

const store = {
  state: {
    cart: [],
    user: null
  },
  
  mutations: {
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
    
    SET_USER(state, user) {
      state.user = user;
    }
  },
  
  actions: {
    async addToCart({ commit }, product) {
      commit('ADD_TO_CART', product);
      
      // Save to server
      await fetch('/api/cart', {
        method: 'POST',
        body: JSON.stringify(product)
      });
    }
  },
  
  persist: {
    key: 'shop-cart',
    storage: 'local',
    paths: ['cart']
  }
};

const routes = [
  {
    path: '/products',
    component: async () => {
      const products = await fetch('/api/products').then(r => r.json());
      return `
        <div class="products">
          <h1>Products</h1>
          <div class="product-grid">
            ${products.map(p => `
              <div class="product-card">
                <img data-src="${p.image}" alt="${p.name}" class="lazy">
                <h3>${p.name}</h3>
                <p>$${p.price}</p>
                <button onclick="$store.dispatch('addToCart', ${JSON.stringify(p)})">
                  Add to Cart
                </button>
              </div>
            `).join('')}
          </div>
        </div>
      `;
    }
  },
  {
    path: '/cart',
    component: () => {
      const cart = window.$store.state.cart;
      const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
      
      return `
        <div class="cart">
          <h1>Shopping Cart</h1>
          ${cart.length === 0 ? '<p>Your cart is empty</p>' : `
            <div class="cart-items">
              ${cart.map(item => `
                <div class="cart-item">
                  <img src="${item.image}" alt="${item.name}">
                  <div>${item.name}</div>
                  <div>Qty: ${item.quantity}</div>
                  <div>$${item.price * item.quantity}</div>
                  <button onclick="$store.commit('REMOVE_FROM_CART', ${item.id})">
                    Remove
                  </button>
                </div>
              `).join('')}
            </div>
            <div class="cart-total">
              <strong>Total: $${total}</strong>
            </div>
            <button class="checkout-btn">Checkout</button>
          `}
        </div>
      `;
    }
  }
];

createApp({ router: { routes }, store }).init();
```

### Example 3: Protected Routes

```javascript
import { createApp } from './spa/app.js';

const guards = {
  auth: async (to, from) => {
    const user = window.$store.state.user;
    if (!user) {
      window.$router.push('/login');
      return false;
    }
    return true;
  },
  
  admin: async (to, from) => {
    const user = window.$store.state.user;
    if (!user || user.role !== 'admin') {
      window.$router.push('/');
      return false;
    }
    return true;
  }
};

const routes = [
  {
    path: '/login',
    component: () => `
      <form id="login-form">
        <input type="email" name="email" placeholder="Email">
        <input type="password" name="password" placeholder="Password">
        <button type="submit">Login</button>
      </form>
    `
  },
  {
    path: '/dashboard',
    guards: ['auth'],
    component: () => `
      <div class="dashboard">
        <h1>Dashboard</h1>
        <p>Welcome, ${window.$store.state.user.name}!</p>
      </div>
    `
  },
  {
    path: '/admin',
    guards: ['auth', 'admin'],
    component: () => `
      <div class="admin">
        <h1>Admin Panel</h1>
        <p>Admin-only content</p>
      </div>
    `
  }
];

createApp({
  router: { routes, guards }
}).init();

// Handle login
document.addEventListener('submit', async (e) => {
  if (e.target.id === 'login-form') {
    e.preventDefault();
    const formData = new FormData(e.target);
    const user = await fetch('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify(Object.fromEntries(formData))
    }).then(r => r.json());
    
    window.$store.commit('SET_USER', user);
    window.$router.push('/dashboard');
  }
});
```

### Example 4: Animations

```javascript
import { createApp } from './spa/app.js';
import { animator } from './spa/animations.js';

const routes = [
  {
    path: '/',
    component: async () => `
      <div class="home">
        <h1 class="title">Welcome!</h1>
        <button id="animate-btn">Animate Me!</button>
        <div id="box" style="width: 100px; height: 100px; background: blue;"></div>
      </div>
    `,
    transition: 'fade'
  }
];

createApp({ router: { routes } }).init();

// Add animations
document.addEventListener('click', (e) => {
  if (e.target.id === 'animate-btn') {
    const box = document.getElementById('box');
    animator.bounce(box);
  }
});

// Animate on page load
window.addEventListener('router:navigated', () => {
  const title = document.querySelector('.title');
  if (title) {
    animator.slideIn(title, 'left');
  }
});
```

### Example 5: Performance Monitoring

```javascript
import { createApp } from './spa/app.js';
import { performanceMonitor } from './spa/performance-monitor.js';

const app = createApp({
  router: { routes: [/* your routes */] }
});

app.init();

// Show performance dashboard (development only)
if (window.location.hostname === 'localhost') {
  performanceMonitor.createDashboard();
}

// Track custom metrics
performanceMonitor.mark('app-init-start');
// ... your code
performanceMonitor.mark('app-init-end');
performanceMonitor.measure('app-init-duration', 'app-init-start', 'app-init-end');

// Listen to performance events
performanceMonitor.on('lcp', (data) => {
  console.log('LCP:', data.value, data.rating);
});
```

---

## 🎨 Styling Tips

```css
/* Add smooth transitions */
#app-content {
  min-height: 100vh;
}

/* Loading state */
.route-loading #app-content {
  opacity: 0.5;
  pointer-events: none;
}

/* Lazy loading images */
img.lazy-loading {
  filter: blur(5px);
}

img.lazy-loaded {
  filter: blur(0);
  transition: filter 0.3s;
}

/* Custom animations */
@keyframes customFade {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.custom-enter {
  animation: customFade 0.5s ease-out;
}
```

---

## 🐛 Troubleshooting

### Issue: Routes không hoạt động
**Solution:** Check console for errors, verify routes are defined correctly.

### Issue: Images không lazy load
**Solution:** Ensure images have `data-src` attribute and `lazy` class.

### Issue: State không persist
**Solution:** Check `persist` configuration in store, verify localStorage is enabled.

### Issue: Service Worker không register
**Solution:** Verify HTTPS (required for SW), check console for errors.

### Issue: Performance dashboard không hiển thị
**Solution:** Call `performanceMonitor.createDashboard()` after app initialization.

---

## 📚 Next Steps

1. ✅ Read full documentation: `docs/SPA_FRAMEWORK_V3.md`
2. ✅ Check example app: `public/assets/js/spa/example-app.js`
3. ✅ Add your routes
4. ✅ Configure store if needed
5. ✅ Enable Service Worker
6. ✅ Optimize performance
7. ✅ Add SEO meta tags
8. ✅ Test thoroughly

---

**Happy coding with BaultSPA v3.0! 🚀**
