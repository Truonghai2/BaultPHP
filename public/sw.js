// public/sw.js

const CACHE_VERSION = 2;
const STATIC_CACHE_NAME = `baultphp-static-v${CACHE_VERSION}`;
const DYNAMIC_CACHE_NAME = `baultphp-dynamic-v${CACHE_VERSION}`;

// Các file cốt lõi của ứng dụng (app shell) cần được cache để hoạt động offline.
// Hãy cập nhật danh sách này cho phù hợp với các tài sản tĩnh quan trọng của bạn.
const urlsToCache = [
  "/",
  "/manifest.json",
  // Ví dụ: '/css/app.css', '/js/app.js', '/images/logo.png'
];

/**
 * 1. Cài đặt Service Worker và cache các file cốt lõi
 */
self.addEventListener("install", (event) => {
  // Buộc service worker mới được kích hoạt ngay lập tức
  self.skipWaiting();

  event.waitUntil(
    caches.open(STATIC_CACHE_NAME).then((cache) => {
      console.log("Service Worker: Caching app shell");
      return cache.addAll(urlsToCache);
    }),
  );
});

/**
 * 2. Kích hoạt Service Worker, nắm quyền kiểm soát và xóa các cache cũ
 */
self.addEventListener("activate", (event) => {
  const cacheWhitelist = [STATIC_CACHE_NAME, DYNAMIC_CACHE_NAME];

  event.waitUntil(
    (async () => {
      // Xóa các cache cũ không còn được sử dụng
      const cacheNames = await caches.keys();
      await Promise.all(
        cacheNames.map(async (cacheName) => {
          if (!cacheWhitelist.includes(cacheName)) {
            console.log("Service Worker: Deleting old cache", cacheName);
            await caches.delete(cacheName);
          }
        }),
      );
      // Nắm quyền kiểm soát các client (tab) đang mở ngay lập tức
      await self.clients.claim();
    })(),
  );
});

/**
 * 3. Xử lý các request mạng với chiến lược caching phù hợp
 */
self.addEventListener("fetch", (event) => {
  // Bỏ qua các request không phải GET
  if (event.request.method !== "GET") {
    return;
  }

  // Chiến lược: Network First cho các lời gọi API
  if (event.request.url.includes("/api/")) {
    event.respondWith(
      (async () => {
        const dynamicCache = await caches.open(DYNAMIC_CACHE_NAME);
        try {
          const networkResponse = await fetch(event.request);
          // Nếu request thành công, cache lại và trả về
          dynamicCache.put(event.request, networkResponse.clone());
          return networkResponse;
        } catch (error) {
          // Nếu mạng lỗi, thử tìm trong cache
          console.log(
            "Service Worker: Network request failed, trying cache for API.",
            error,
          );
          const cachedResponse = await dynamicCache.match(event.request);
          return (
            cachedResponse ||
            new Response(JSON.stringify({ error: "Offline" }), {
              headers: { "Content-Type": "application/json" },
            })
          );
        }
      })(),
    );
    return;
  }

  // Chiến lược: Stale-While-Revalidate cho các tài sản tĩnh và trang
  event.respondWith(
    (async () => {
      const cache = await caches.open(STATIC_CACHE_NAME);
      const cachedResponse = await cache.match(event.request);
      const networkResponsePromise = fetch(event.request).then(
        (networkResponse) => {
          // Cập nhật cache động với phiên bản mới
          caches.open(DYNAMIC_CACHE_NAME).then((dynamicCache) => {
            dynamicCache.put(event.request, networkResponse.clone());
          });
          return networkResponse;
        },
      );

      // Trả về từ cache ngay lập tức nếu có, nếu không thì chờ mạng
      return cachedResponse || networkResponsePromise;
    })(),
  );
});
