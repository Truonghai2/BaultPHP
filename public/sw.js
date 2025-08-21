// public/sw.js

const CACHE_NAME = "baultphp-cache-v1";
// Các file cốt lõi của ứng dụng cần được cache để hoạt động offline.
// Bạn cần cập nhật danh sách này cho phù hợp với các tài sản tĩnh của bạn.
const urlsToCache = [
  "/",
  // Ví dụ:
  // '/css/app.css',
  // '/js/app.js',
  // '/images/logo.png',
  "/manifest.json",
];

/**
 * 1. Cài đặt Service Worker và cache các file cốt lõi
 */
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log("Service Worker: Caching app shell");
      return cache.addAll(urlsToCache);
    }),
  );
});

/**
 * 2. Kích hoạt Service Worker và xóa các cache cũ
 */
self.addEventListener("activate", (event) => {
  const cacheWhitelist = [CACHE_NAME];

  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          // Nếu cache không nằm trong danh sách trắng, hãy xóa nó
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            console.log("Service Worker: Deleting old cache", cacheName);
            return caches.delete(cacheName);
          }
        }),
      );
    }),
  );
});

/**
 * 3. Xử lý các request, trả về từ cache nếu có (Cache-First Strategy)
 */
self.addEventListener("fetch", (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      // Nếu tìm thấy trong cache, trả về response từ cache
      if (response) {
        return response;
      }

      // Nếu không, thực hiện request mạng
      return fetch(event.request);
    }),
  );
});
