const VERSION = 'v1';
const STATIC_CACHE = `volylegume-static-${VERSION}`;
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/images/logo-sm.png',
    '/images/icon-192.png',
    '/images/icon-512.png',
    '/images/apple-touch-icon.png',
    '/images/favicon-32.png',
    '/favicon.ico',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('volylegume-') && key !== STATIC_CACHE)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

function isStaticAsset(request, url) {
    return ['style', 'script', 'image', 'font', 'manifest'].includes(request.destination)
        || url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/images/');
}

async function cacheFirst(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());
    }

    return response;
}

async function networkFirstNavigation(request) {
    try {
        return await fetch(request);
    } catch {
        const cache = await caches.open(STATIC_CACHE);

        return (await cache.match(OFFLINE_URL)) ?? Response.error();
    }
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    if (isStaticAsset(request, url)) {
        event.respondWith(cacheFirst(request));
    }
});
