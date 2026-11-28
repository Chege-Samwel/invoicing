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

/*
 * The cache version, derived from the files themselves.
 *
 * This was a hand-edited string ('adomin-v2'), which is a promise nobody can
 * keep: the runtime changes on a deploy, the constant does not, so the worker
 * installs nothing new and keeps answering `staleWhileRevalidate` with the
 * previous adomin-app.js. The page then runs the OLD handlers against the NEW
 * markup - and a copy button that no longer finds the URL its earlier version
 * looked for simply does nothing, with no toast and no animation. That reads as
 * "the copy link button does not work", on a deploy where the source is
 * correct.
 *
 * The list below is the shell this worker must own; its contents are hashed
 * into the version at install time, so any change to the runtime retires the
 * old cache by construction rather than by remembering to bump a string.
 */
const RUNTIME = './assets/public/js/adomin-app.js';
const STYLE = './assets/public/css/adomin-animations.css';
const SHELL = 'adomin-shell-';
const PAGES = 'adomin-pages-';
let BUILD = 'adomin-v3';

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

/*
 * A short fingerprint of one file's bytes. Fetched with cache:'reload' so the
 * hash describes what the server has now, not what the browser was holding.
 */
async function fingerprint(url) {
    try {
        const res = await fetch(new Request(url, { cache: 'reload' }));
        if (!res || !res.ok) return 'none';
        const buf = await res.arrayBuffer();
        // FNV-1a over the bytes: tiny, synchronous, and enough to notice a deploy
        let hash = 0x811c9dc5;
        const bytes = new Uint8Array(buf);
        for (let i = 0; i < bytes.length; i++) {
            hash ^= bytes[i];
            hash = (hash * 0x01000193) >>> 0;
        }
        return hash.toString(36);
    } catch (e) {
        return 'offline';
    }
}

async function resolveBuild() {
    const parts = await Promise.all([fingerprint(RUNTIME), fingerprint(STYLE)]);
    return BUILD + '-' + parts.join('');
}

/*
 * The fetch handlers run in a later event than install, and a worker that was
 * woken for one request may never have installed in this lifetime. The build
 * is therefore resolved once and memoised, so a fetch never opens a cache
 * under a name the activate step does not know about.
 */
let buildPromise = null;
function build() {
    if (!buildPromise) { buildPromise = resolveBuild(); }
    return buildPromise;
}

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const name = await build();
        const cache = await caches.open(SHELL + name);
        await Promise.allSettled(SHELL_ASSETS.map((url) => cache.add(new Request(url, { cache: 'reload' }))));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        /*
         * Retire every shell and page cache that is not this one. The old test
         * only deleted keys whose prefix differed from BUILD, so two builds
         * that both started with "adomin-" were kept - which is how a stale
         * runtime survived a deploy in the first place.
         */
        const name = await build();
        const keep = [SHELL + name, PAGES + name];
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter((k) => (k.indexOf(SHELL) === 0 || k.indexOf(PAGES) === 0) && keep.indexOf(k) === -1)
                .map((k) => caches.delete(k))
        );
        await self.clients.claim();
    })());
});

// Takes a PATH, and is defensive about what it is handed: a worker that throws
// inside a fetch handler answers the page with a network error, so a single
// bad argument costs the whole navigation, not just one asset.
function isStatic(path) {
    if (typeof path !== 'string' || path === '') {
        return false;
    }
    /*
     * File types only. 'json' was in this list and does not belong here: an
     * endpoint that answers in JSON is not a shell asset, and serving it
     * cache-first hands the page a stale answer to a question it just asked.
     */
    if (/\.(webmanifest|json)(\?|$)/i.test(path)) {
        return path === '/manifest.json';          // the shell's manifest only
    }
    return /\.(css|js|mjs|png|jpg|jpeg|svg|gif|webp|ico|woff2?|ttf|eot|map)(\?|$)/i.test(path)
        || (path.indexOf('/assets/') !== -1 && /\.(css|js|png|jpg|jpeg|svg|gif|webp|ico|woff2?|ttf|eot|map)$/i.test(path));
}

function isPdf(request, response) {
    const type = (response && response.headers && response.headers.get('content-type')) || '';
    return type.indexOf('application/pdf') !== -1 || /\.pdf(\?|$)/i.test(new URL(request.url).pathname);
}

function cacheablePage(request, url) {
    if (request.method !== 'GET') return false;

    /*
     * Pages are addressed with a language prefix - /en/user/invoices - so a
     * check against the start of the path sees '/en/...' and lets a
     * session-scoped page through. The prefix is stripped before anything is
     * decided, and every segment is then tested, so /en/user/ and /fr/admin/
     * are treated exactly like their unprefixed forms.
     */
    const path = url.pathname
        .replace(/\/index\.php/, '')
        .replace(/^\/[a-z]{2}(?=\/|$)/i, '') || '/';

    const segments = path.split('/').filter(Boolean);
    const scoped = ['user', 'admin', 'login', 'password', 'registration', 'loader', 'logout', 'reset'];
    if (segments.length > 0 && scoped.indexOf(segments[0].toLowerCase()) !== -1) {
        return false;
    }
    if (request.headers && (request.headers.get('authorization') || (request.headers.get('cookie') || '').indexOf('tyson=') !== -1)) {
        // a page fetched with a session is that session's page
        return false;
    }
    return true; // public share / accept pages and the landing page
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(SHELL + await build());
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
                const cache = await caches.open(PAGES + await build());
                cache.put(request, response.clone());
            }
            if (response) return response;

            const cache = await caches.open(PAGES + await build());
            const fallback = await cache.match(request);
            if (fallback) return fallback;
            const offline = await caches.open(SHELL + await build()).then((c) => c.match('./offline.html'));
            // waking a sleeping service is slow: nudge it now so the retry works
            fetch(request, { cache: 'reload' }).catch(() => {});
            return offline || Response.error();
        })());
        return;
    }

    /*
     * Everything else: straight through, and stored only if it is a page.
     *
     * This branch used to cache any 200 outside /user/ - AJAX responses
     * included. A cached status change is a status that never changes: the
     * click reaches the worker, the worker answers from the cache, the server
     * is never asked, and the menu closes over the old value. The response is
     * now stored only when it is a document the reader navigated to, and
     * pages that exist to change something are not stored at all.
     */
    event.respondWith((async () => {
        const response = await fetch(request).catch(() => null);
        if (!response) return Response.error();
        if (isPdf(request, response) || (response.headers.get('set-cookie') || '') !== '') return response;

        const type = (response.headers.get('content-type') || '').toLowerCase();
        const isDocument = type.indexOf('text/html') !== -1;
        const mutating = /\b(change|set|save|update|delete|remove|status|share|accept|decline|approve|reject)\b/i.test(url.pathname);
        if (response.ok && isDocument && !mutating && cacheablePage(request, url) && url.pathname.indexOf('/user/') !== 0) {
            const cache = await caches.open(PAGES + await build());
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
