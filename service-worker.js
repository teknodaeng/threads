const CACHE_NAME = 'threads-v1';
const ASSETS_TO_CACHE = [
    'offline.html',
    'assets/output.css',
    'assets/app.js',
    'assets/image-modal.js',
    'assets/default-avatar.png',
    'assets/icon-192.png',
    'assets/icon-512.png'
];

// Install Event: Cache static assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Opened cache');
                return cache.addAll(ASSETS_TO_CACHE);
            })
    );
});

// Activate Event: Clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Fetch Event: Network First for HTML, Stale-While-Revalidate for Assets
self.addEventListener('fetch', event => {
    const requestUrl = new URL(event.request.url);

    // Handle HTML requests (Navigation)
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    return caches.match('offline.html');
                })
        );
        return;
    }

    // Handle Static Assets (CSS, JS, Images)
    if (requestUrl.pathname.startsWith('/assets/')) {
        event.respondWith(
            caches.match(event.request).then(cachedResponse => {
                const fetchPromise = fetch(event.request).then(networkResponse => {
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, networkResponse.clone());
                    });
                    return networkResponse;
                });
                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // Default: Network First
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});
