// Service worker TypeMaster.
// - POST (ukládání výsledků, login) nikdy necachujeme.
// - Statika (css/js/ikony): stale-while-revalidate — soubory NEMAJÍ hash
//   ve jméně, takže cache-first by po deployi navždy servíroval starou
//   verzi. Takhle se ukáže cache a na pozadí se stáhne čerstvá kopie.
// - HTML/stránky síť napřed, cache jako záloha při výpadku připojení.
const CACHE_VERSION = "typemaster-v3";
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
    // statika: stale-while-revalidate; no-cache obchází HTTP cache
    // prohlížeče (stará expirace 1 týden by jinak revalidaci zablokovala)
    event.respondWith(
      caches.match(req).then((cached) => {
        const fresh = fetch(req, { cache: "no-cache" })
          .then((res) => {
            if (res.ok) {
              const copy = res.clone();
              caches.open(CACHE_VERSION).then((c) => c.put(req, copy));
            }
            return res;
          })
          .catch(() => cached);
        return cached || fresh;
      })
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
