/**
 * Apna Invoice — service worker
 *
 * Strategy (deliberately conservative):
 *  - GET /build/* (CSS/JS hashed by Vite) → cache-first, with background refresh.
 *    Hashed filenames mean the cache key changes when content changes, so this
 *    is safe and never serves stale assets to a user on the new HTML.
 *  - GET /brand/*, /favicon.ico, /manifest.json → cache-first.
 *  - Everything else (HTML pages, API/POSTs/PATCHes, anything dynamic) →
 *    network-only. We never cache HTML or write methods because the app deals
 *    with money, sessions, and CSRF tokens that must always be fresh.
 *
 * Bumping the CACHE_VERSION will invalidate every cache the next time a user
 * visits — increment when this file or the cached asset rules change.
 */
const CACHE_VERSION = 'v1';
const STATIC_CACHE = `apna-static-${CACHE_VERSION}`;

const SHOULD_CACHE = (url) =>
    url.pathname.startsWith('/build/')
    || url.pathname.startsWith('/brand/')
    || url.pathname === '/favicon.ico'
    || url.pathname === '/manifest.json';

self.addEventListener('install', (event) => {
    // Take over from any older worker as soon as we're installed.
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        // Clean up any cache from a previous version.
        caches.keys().then((names) =>
            Promise.all(names.filter((n) => n !== STATIC_CACHE).map((n) => caches.delete(n)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Only handle GET. Anything else (POST/PATCH/DELETE) goes straight to the
    // network so CSRF, session, and write-side semantics are untouched.
    if (req.method !== 'GET') return;

    let url;
    try { url = new URL(req.url); } catch { return; }

    // Only cache same-origin requests. Don't touch fonts.bunny.net etc.
    if (url.origin !== self.location.origin) return;

    if (!SHOULD_CACHE(url)) return; // network-only for HTML & dynamic routes

    event.respondWith(
        caches.open(STATIC_CACHE).then(async (cache) => {
            const cached = await cache.match(req);
            const networkFetch = fetch(req).then((res) => {
                // Only cache 200-OK basic responses to avoid storing errors.
                if (res && res.status === 200 && res.type === 'basic') {
                    cache.put(req, res.clone());
                }
                return res;
            }).catch(() => cached); // offline → fall back to cache if any

            return cached || networkFetch;
        })
    );
});
