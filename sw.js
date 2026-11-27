/*
  --------------------------------------------------------------------------
  Adomin Invoices — service worker
  --------------------------------------------------------------------------
  What it buys you:
    * the app shell (css, js, fonts, icons) is served from cache, so the
      Android shell / home-screen PWA paints instantly and survives a subway
      tunnel;
    * a document you opened through a "send via link" stays readable offline;
    * when the Render free tier instance is cold (it sleeps after 15 idle
      minutes) a navigation shows the offline card instead of a browser error,
      and retries in the background so the second tap is warm.

  What it deliberately does NOT do:
    * it never caches a POST, anything with Set-Cookie, or a page under
      /user/ - those are session scoped and must not be replayed to the next
      person on the device;
    * it never touches PDF bodies (they are streamed, and the browser already
      offers its own download UI).
  --------------------------------------------------------------------------
*/

const BUILD = 'adomin-v1';
const SHELL = `${BUILD}-shell`;
const PAGES = `${BUILD}-pages`;

// resolved against the registration scope, so a sub-folder install works
const SHELL_ASSETS = [
    './',
    './offline.html',
    './assets/public/css/adomin-brand.css',
    './assets/public/css/adomin-animations.css',
    './assets/public/js/adomin-app.js',
    './assets/jquery/jquery.min.js',
    './assets/bootstrap/css/bootstrap.min.css',
    './assets/bootstrap/js/bootstrap.min.js',
    './assets/font-awesome/css/font-awesome.min.css',
    './assets/users/css/general.css',
    './assets/public/imgs/logo.png',
    './assets/public/imgs/adomin-mark.svg',
    './assets/public/imgs/icons/icon-192.png',
    './manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL)
            .then((cache) => Promise.allSettled(SHELL_ASSETS.map((url) => cache.add(new Request(url, { cache: 'reload' })))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.indexOf(BUILD) !== 0 && k.indexOf('adomin-') === 0).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

// Takes a PATH, and is defensive about what it is handed: a worker that throws
// inside a fetch handler answers the page with a network error, so a single
// bad argument costs the whole navigation, not just one asset.
function isStatic(path) {
    if (typeof path !== 'string' || path === '') {
        return false;
    }
    return /\.(css|js|mjs|json|webmanifest|png|jpg|jpeg|svg|gif|webp|ico|woff2?|ttf|eot|map)(\?|$)/i.test(path)
        || path.indexOf('/assets/') !== -1;
}

function isPdf(request, response) {
    const type = (response && response.headers && response.headers.get('content-type')) || '';
    return type.indexOf('application/pdf') !== -1 || /\.pdf(\?|$)/i.test(new URL(request.url).pathname);
}

function cacheablePage(request, url) {
    if (request.method !== 'GET') return false;
    const path = url.pathname.replace(/\/index\.php/, '');
    if (path.indexOf('/user/') === 0 || path === '/user') return false;      // session scoped
    if (path.indexOf('/admin') === 0) return false;                          // admin panel
    if (path.indexOf('/login') === 0 || path.indexOf('/password') === 0) return false;
    if (path.indexOf('/registration') === 0) return false;
    if (path.indexOf('/loader') === 0) return false;
    return true; // public share / accept pages and the landing page
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(SHELL);
    const cached = await cache.match(request);
    const network = fetch(request).then((response) => {
        if (response && (response.status === 200 || response.status === 0)) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => null);
    return cached || network || Response.error();
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;   // never proxy a third party

    // assets: cache first, refresh in background
    if (isStatic(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request));
        return;
    }

    // navigations: network first, with an offline card and a background wake-up
    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            let response = null;
            try {
                response = await fetch(request);
            } catch (e) {
                response = null;
            }
            if (response && response.ok && cacheablePage(request, url)) {
                const cache = await caches.open(PAGES);
                cache.put(request, response.clone());
            }
            if (response) return response;

            const cache = await caches.open(PAGES);
            const fallback = await cache.match(request);
            if (fallback) return fallback;
            const offline = await caches.open(SHELL).then((c) => c.match('./offline.html'));
            // waking a sleeping service is slow: nudge it now so the retry works
            fetch(request, { cache: 'reload' }).catch(() => {});
            return offline || Response.error();
        })());
        return;
    }

    // PDFs and everything else: straight through
    event.respondWith((async () => {
        const response = await fetch(request).catch(() => null);
        if (!response) return Response.error();
        if (isPdf(request, response) || (response.headers.get('set-cookie') || '') !== '') return response;
        if (response.ok && url.pathname.indexOf('/user/') !== 0) {
            const cache = await caches.open(PAGES);
            cache.put(request, response.clone());
        }
        return response;
    })());
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING' || (event.data && event.data.type === 'SKIP_WAITING')) {
        self.skipWaiting();
    }
});
