var CACHE_NAME = 'volleyplay-v1';

// Core assets loaded on every page — pre-cached at install
var PRECACHE_URLS = [
    '/assets/lib.css',
    '/assets/style.css',
    '/assets/lib.js',
    '/assets/script.js',
];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll(PRECACHE_URLS);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.filter(function(name) {
                    return name !== CACHE_NAME;
                }).map(function(name) {
                    return caches.delete(name);
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', function(event) {
    if (event.request.method !== 'GET') return;

    var url = new URL(event.request.url);

    // Only cache same-origin static assets
    if (url.origin !== self.location.origin) return;

    if (
        url.pathname.startsWith('/assets/') ||
        url.pathname.startsWith('/img/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/build/') ||
        url.pathname.match(/\.(css|js|png|jpg|jpeg|webp|svg|woff2?|ttf|eot)$/)
    ) {
        event.respondWith(
            // ignoreSearch allows matching /assets/lib.css?v=TIMESTAMP against cached /assets/lib.css
            caches.match(event.request, { ignoreSearch: true }).then(function(response) {
                if (response) return response;
                return fetch(event.request).then(function(networkResponse) {
                    if (networkResponse && networkResponse.status === 200) {
                        var responseToCache = networkResponse.clone();
                        // Store without query params to avoid repeated cache misses on versioned URLs
                        var normalizedRequest = new Request(url.origin + url.pathname);
                        caches.open(CACHE_NAME).then(function(cache) {
                            cache.put(normalizedRequest, responseToCache);
                        });
                    }
                    return networkResponse;
                });
            })
        );
    }
});
