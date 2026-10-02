/*
 * KSA Express rider app service worker (scope /rider/).
 *
 * - Built assets (/build/assets/*) never change once published — the file
 *   name carries a hash — so they're served from cache after the first load.
 * - Pages go to the network first (they carry who is signed in), falling
 *   back to the last copy of the app page when there's no signal.
 * - /rider/api/* is never cached: parcel status must always be live.
 * - Byte-range requests (video) go straight to the network: a partial
 *   response can't be cached, and answering one with the whole file breaks
 *   playback on iPhone.
 *
 * Bump VERSION to drop old caches.
 */
const VERSION = 'v1';
const ASSETS = `rider-assets-${VERSION}`;
const PAGES = `rider-pages-${VERSION}`;
const APP_PAGE = '/rider/app';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('rider-') && ![ASSETS, PAGES].includes(key)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/rider/api/')) return;
    if (request.headers.has('range')) return;

    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/rider-icons/')) {
        event.respondWith(cacheFirst(request));
        return;
    }

    if (request.mode === 'navigate' && url.pathname.startsWith('/rider/')) {
        event.respondWith(networkFirstPage(request, url));
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(ASSETS);
    const cached = await cache.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response.status === 200) cache.put(request, response.clone());
    return response;
}

async function networkFirstPage(request, url) {
    const cache = await caches.open(PAGES);

    try {
        const response = await fetch(request);
        // Keep only the app page: activation links are single-use.
        if (response.ok && url.pathname === APP_PAGE) cache.put(APP_PAGE, response.clone());
        return response;
    } catch (error) {
        const cached = await cache.match(APP_PAGE);
        if (cached) return cached;
        throw error;
    }
}
