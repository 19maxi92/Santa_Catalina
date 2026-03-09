// Service Worker - Santa Catalina Pedidos Online
const CACHE_NAME = 'santa-catalina-v1';
const URLS_TO_CACHE = [
    '/pedido_online/index.php',
    '/pedido_online/manifest.json',
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(URLS_TO_CACHE).catch(() => {});
        })
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

// Network first, fallback to cache
self.addEventListener('fetch', (event) => {
    // Solo cachear GETs a la misma origen
    if (event.request.method !== 'GET') return;
    if (!event.request.url.startsWith(self.location.origin)) return;

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // Guardar en cache si fue exitoso
                if (response.ok) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // Si no hay red, devolver del cache
                return caches.match(event.request);
            })
    );
});
