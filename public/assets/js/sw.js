// public/sw.js

const CACHE_VERSION = 8; // Tăng phiên bản cache để kích hoạt activate
const STATIC_CACHE_NAME = `baultphp-static-v${CACHE_VERSION}`;
const DYNAMIC_CACHE_NAME = `baultphp-dynamic-v${CACHE_VERSION}`;
const APP_SHELL_CACHE_NAME = `baultphp-shell-v${CACHE_VERSION}`;
const IMAGE_CACHE_NAME = `baultphp-images-v${CACHE_VERSION}`;
const FONT_CACHE_NAME = `baultphp-fonts-v${CACHE_VERSION}`;

const APP_SHELL_URLS = [
  "/",
  "/index.php",
  "/manifest.json",
  "/offline.html",
  "/assets/css/app.css",
  "/assets/js/app.js",
];

/**
 * 1. Cài đặt Service Worker và cache App Shell
 */
self.addEventListener("install", (event) => {
  self.skipWaiting();

  event.waitUntil(
    caches.open(APP_SHELL_CACHE_NAME).then((cache) => {
      console.log("Service Worker: Caching app shell");
      const cachePromises = APP_SHELL_URLS.map((url) => {
        return fetch(new Request(url, { cache: "reload" }))
          .then((response) => {
            if (response.ok) {
              return cache.put(url, response);
            }
            console.warn(
              `SW: Failed to cache ${url} during install. Status: ${response.status}`,
            );
            return Promise.resolve(); // Bỏ qua lỗi, không làm gián đoạn install
          })
          .catch((err) =>
            console.warn(`SW: Fetch error for ${url} during install:`, err),
          );
      });
      return Promise.allSettled(cachePromises);
    }),
  );
});

/**
 * 2. Kích hoạt Service Worker và xóa cache cũ
 */
self.addEventListener("activate", (event) => {
  const cacheWhitelist = [
    STATIC_CACHE_NAME,
    DYNAMIC_CACHE_NAME,
    APP_SHELL_CACHE_NAME,
    IMAGE_CACHE_NAME,
    FONT_CACHE_NAME,
  ];

  event.waitUntil(
    (async () => {
      const cacheNames = await caches.keys();
      await Promise.all(
        cacheNames.map(async (cacheName) => {
          if (!cacheWhitelist.includes(cacheName)) {
            console.log("Service Worker: Deleting old cache", cacheName);
            await caches.delete(cacheName);
          }
        }),
      );
      await self.clients.claim();
    })(),
  );
});

/**
 * 3. Xử lý các request mạng với các chiến lược caching khác nhau
 */
self.addEventListener("fetch", (event) => {
  if (
    event.request.method !== "GET" ||
    event.request.url.startsWith("chrome-extension://") ||
    event.request.url.includes("/bault-debug/data")
  ) {
    return;
  }

  // ƯU TIÊN 1: Xử lý các tài nguyên cốt lõi của App Shell trước tiên.
  if (APP_SHELL_URLS.includes(new URL(event.request.url).pathname)) {
    event.respondWith(
      staleWhileRevalidate(
        event.request,
        APP_SHELL_CACHE_NAME,
        "/offline.html",
      ),
    );
    return;
  }

  // ƯU TIÊN: Kiểm tra các yêu cầu điều hướng từ SPA trước.
  // Đây là các yêu cầu fetch() được kích hoạt bởi spa-navigation.js.
  if (event.request.headers.has("X-SPA-NAVIGATE")) {
    event.respondWith(staleWhileRevalidate(event.request, DYNAMIC_CACHE_NAME));
    return;
  }

  const url = new URL(event.request.url);

  if (event.request.mode === "navigate") {
    event.respondWith(
      staleWhileRevalidate(
        event.request,
        APP_SHELL_CACHE_NAME,
        "/offline.html",
      ),
    );
    return;
  }

  if (event.request.destination === "image") {
    event.respondWith(staleWhileRevalidate(event.request, IMAGE_CACHE_NAME));
    return;
  }

  if (event.request.url.includes("/api/")) {
    event.respondWith(networkFirst(event.request));
    return;
  }

  if (
    event.request.destination === "style" ||
    event.request.destination === "script"
  ) {
    event.respondWith(staleWhileRevalidate(event.request, STATIC_CACHE_NAME));
    return;
  }

  if (
    event.request.destination === "font" ||
    url.pathname.match(/\.(woff2?|eot|ttf|otf)$/)
  ) {
    event.respondWith(cacheFirst(event.request, FONT_CACHE_NAME));
    return;
  }

  event.respondWith(cacheFirst(event.request, STATIC_CACHE_NAME));
});

/**
 * Gửi message tới tất cả các client (tab) đang mở.
 */
async function postMessageToClients(message) {
  const clients = await self.clients.matchAll({
    includeUncontrolled: true,
    type: "window",
  });
  clients.forEach((client) => {
    client.postMessage(message);
  });
}

// --- CÁC HÀM CHIẾN LƯỢC CACHING ---

async function networkFirst(
  request,
  cacheName = DYNAMIC_CACHE_NAME,
  fallbackUrl = null,
) {
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      // Mở cache và ghi chỉ khi request mạng thành công
      const dynamicCache = await caches.open(cacheName);
      dynamicCache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }

    if (fallbackUrl) {
      const fallbackResponse = await caches.match(fallbackUrl);
      return (
        fallbackResponse ||
        new Response("You are offline.", { status: 404, statusText: "Offline" })
      );
    }

    // Fallback cho API nếu không có cache và không có fallbackUrl
    return new Response(JSON.stringify({ error: "Offline" }), {
      headers: { "Content-Type": "application/json" },
      status: 503,
    });
  }
}

/**
 * Cache First, Fallback to Network
 * Tốt cho tài sản tĩnh bất biến (có hash trong tên file).
 */
async function cacheFirst(request, cacheName = STATIC_CACHE_NAME) {
  const cachedResponse = await caches.match(request, { cacheName });
  if (cachedResponse) {
    return cachedResponse;
  }

  // Nếu không có trong cache, fetch từ mạng và cache lại
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    console.log("SW: Failed to fetch and cache static asset:", request.url);
    // Có thể trả về một response lỗi mặc định cho ảnh/font ở đây nếu cần
  }
}

/**
 * Stale-While-Revalidate
 * Tốt cho App Shell và các tài nguyên cần cập nhật ngầm.
 */
async function staleWhileRevalidate(
  request,
  cacheName = DYNAMIC_CACHE_NAME,
  fallbackUrl = null,
) {
  const cachedResponsePromise = caches.match(request, { cacheName });

  const fetchPromise = fetch(request)
    .then(async (networkResponse) => {
      if (networkResponse.ok) {
        const cache = await caches.open(cacheName);
        cache.put(request, networkResponse.clone());
        const cachedResponse = await cachedResponsePromise;
        if (cachedResponse) {
          await postMessageToClients({
            type: "CACHE_UPDATED",
            payload: { url: request.url },
          });
        }
      }
      return networkResponse;
    })
    .catch(async (error) => {
      console.log(
        "SW: Network fetch failed for stale-while-revalidate:",
        request.url,
      );
      if (fallbackUrl) {
        const fallbackResponse = await caches.match(fallbackUrl);
        if (fallbackResponse) return fallbackResponse;
      }
      return new Response("Network error", {
        status: 408,
        headers: { "Content-Type": "text/plain" },
      });
    });

  return (await cachedResponsePromise) || fetchPromise;
}
