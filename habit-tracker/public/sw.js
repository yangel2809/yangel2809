/*
 * Service worker de Hábitos.
 *
 * - HTML (navegación): SIEMPRE de la red. Nunca se cachea: lleva datos del día
 *   y el token CSRF (una copia vieja provocaría errores 419). Sin red se
 *   muestra offline.html.
 * - /build/assets/* (nombres con hash) e iconos: caché primero; son inmutables.
 * - Todo lo demás (PUT/PATCH, JSON, otras rutas): pasa directo a la red.
 *
 * Solo se guardan respuestas 200, del mismo origen y con el Content-Type
 * esperado. Esto evita cachear por error la página HTML del "security
 * system" de InfinityFree (desafío JS/cookie), que responde 200 text/html
 * en lugar del archivo pedido.
 */
const VERSION = 'v1';
const CACHE = `habitos-${VERSION}`;
const BASE = new URL(self.registration.scope);
const OFFLINE = new URL('offline.html', BASE).href;
const PRECACHE = [OFFLINE, new URL('pwa/icon-192.png', BASE).href];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => Promise.all(PRECACHE.map((url) =>
                fetch(url, { cache: 'reload' }).then((res) => {
                    if (isCacheable(res, url)) return cache.put(url, res);
                })
            )))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('habitos-') && k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== BASE.origin) return;

    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
        return;
    }

    if (isStatic(url)) {
        event.respondWith(cacheFirst(req, url));
    }
});

function isStatic(url) {
    const path = url.pathname.slice(BASE.pathname.length);
    return path.startsWith('build/assets/') || path.startsWith('pwa/') || path === 'offline.html';
}

async function cacheFirst(req, url) {
    const cache = await caches.open(CACHE);
    const hit = await cache.match(req);
    if (hit) return hit;

    const res = await fetch(req);
    if (isCacheable(res, url.href)) {
        await pruneOldBuilds(cache, url);
        await cache.put(req, res.clone());
    }
    return res;
}

/** Un solo archivo vigente por entrada de Vite: app-HASH.js reemplaza al anterior. */
async function pruneOldBuilds(cache, url) {
    const match = url.pathname.match(/\/build\/assets\/(.+)-[\w-]{8,}\.(js|css)$/);
    if (!match) return;
    const [, name, ext] = match;
    for (const key of await cache.keys()) {
        const m = new URL(key.url).pathname.match(/\/build\/assets\/(.+)-[\w-]{8,}\.(js|css)$/);
        if (m && m[1] === name && m[2] === ext && key.url !== url.href) await cache.delete(key);
    }
}

function isCacheable(res, href) {
    if (!res || res.status !== 200 || res.type !== 'basic') return false;
    const type = res.headers.get('Content-Type') || '';
    if (href.endsWith('.js')) return type.includes('javascript');
    if (href.endsWith('.css')) return type.includes('css');
    if (href.endsWith('.html')) return type.includes('html');
    if (/\.(png|svg|ico|webp)$/.test(href)) return type.startsWith('image/');
    return false;
}
