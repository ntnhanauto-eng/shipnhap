const CACHE_NAME = 'smart-system-v1';
const urlsToCache = [
  '/',
  'index.html',
  // Thêm các file css hoặc ảnh icon của bạn vào đây
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => response || fetch(event.request))
  );
});
