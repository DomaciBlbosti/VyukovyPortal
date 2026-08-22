// Service worker TypeMaster. Filozofie (stejná jako Kuchařka):
// - POST (ukládání výsledků, login) nikdy necachujeme.
// - Statika (css/js/ikony/fonty) cache-first — mění se jen s verzí appky.
// - HTML/stránky síť napřed, cache jako záloha při výpadku připojení.
const CACHE_VERSION = "typemaster-v1";
const STATIC_RE = /\.(css|js|png|svg|woff2?)(\?.*)?$/;

self.addEventListener("install", (event) => {
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.endsWith("/sw.js")) return;

  if (STATIC_RE.test(url.pathname)) {
    // statika: cache-first
    event.respondWith(
      caches.match(req).then(
        (cached) =>
          cached ||
          fetch(req).then((res) => {
            if (res.ok) {
              const copy = res.clone();
              caches.open(CACHE_VERSION).then((c) => c.put(req, copy));
            }
            return res;
          })
      )
    );
    return;
  }

  // stránky: síť napřed, cache jako offline záloha
  event.respondWith(
    fetch(req)
      .then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(CACHE_VERSION).then((c) => c.put(req, copy));
        }
        return res;
      })
      .catch(() => caches.match(req))
  );
});
