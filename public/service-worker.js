/**
 * Apna Invoice — service worker
 *
 * Strategy (deliberately conservative):
 *  - GET /build/assets/* (CSS/JS hashed by Vite) → cache-first, no revalidation.
 *    A hashed file never changes, so asking the network again on every page
 *    view only re-downloads it. When a new build of the same entry is cached,
 *    the older copies of that entry are dropped so the cache does not grow
 *    with every deploy.
 *  - GET /brand/*, /favicon.ico, /manifest.json (and anything else under
 *    /build) → cache-first, refreshed in
 *    the background (these names are stable, so they can change in place).
 *  - Everything else (HTML pages, API/POSTs/PATCHes, user uploads under
 *    /storage, anything dynamic) → network-only. We never cache HTML or write
 *    methods because the app deals with money, sessions, and CSRF tokens that
 *    must always be fresh.
 *
 * Bumping the CACHE_VERSION will invalidate every cache the next time a user
 * visits — increment when this file or the cached asset rules change.
 */
const CACHE_VERSION = 'v5';
const STATIC_CACHE = `apna-static-${CACHE_VERSION}`;

const isHashedBuildAsset = (url) => url.pathname.startsWith('/build/assets/');

const SHOULD_CACHE = (url) =>
    url.pathname.startsWith('/build/')
    || url.pathname.startsWith('/brand/')
    || url.pathname === '/favicon.ico'
    || url.pathname === '/manifest.json';

// "/build/assets/app-BQau9GzB.css" → "/build/assets/app.css": the same entry
// across builds, so a new build can replace the old one. Vite's hash is 8
// base64url characters; a name it does not fit is simply never pruned.
const buildEntryKey = (pathname) => pathname.replace(/-[A-Za-z0-9_-]{8}(\.[a-z0-9]+)$/i, '$1');

// Only a complete, same-origin 200 is worth keeping; errors, redirects and
// opaque responses are passed through untouched.
const cacheable = (res) => res && res.status === 200 && res.type === 'basic';

self.addEventListener('install', () => {
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

async function pruneOlderBuilds(cache, url) {
    const entry = buildEntryKey(url.pathname);
    const keys = await cache.keys();
    await Promise.all(keys.map((key) => {
        const path = new URL(key.url).pathname;
        return path !== url.pathname && path.startsWith('/build/assets/') && buildEntryKey(path) === entry
            ? cache.delete(key)
            : null;
    }));
}

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

    event.respondWith((async () => {
        // Cache storage can be full, blocked or broken (private windows, some
        // embedded browsers). Then behave as if there were no worker at all:
        // a cache problem must never cost the page its CSS, JS or images.
        let cache;
        let cached;
        try {
            cache = await caches.open(STATIC_CACHE);
            cached = await cache.match(req);
        } catch {
            return fetch(req);
        }

        if (cached && isHashedBuildAsset(url)) {
            return cached;
        }

        const network = fetch(req).then(async (res) => {
            if (cacheable(res)) {
                try {
                    await cache.put(req, res.clone());
                    if (isHashedBuildAsset(url)) {
                        await pruneOlderBuilds(cache, url);
                    }
                } catch {
                    // Not cached this time; the response itself is fine.
                }
            }
            return res;
        });

        if (cached) {
            // Stable-name asset: answer from cache now, refresh for next time.
            event.waitUntil(network.catch(() => {}));
            return cached;
        }

        return network;
    })());
});
