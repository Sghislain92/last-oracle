const VERSION = "v19";
const SHELL_CACHE = `oracle-shell-${VERSION}`;
const RUNTIME_CACHE = `oracle-runtime-${VERSION}`;

// Toutes les ressources nécessaires à un fonctionnement 100% hors ligne :
// coquille de l'app, manifeste, icônes, polices, et librairies CDN
// (Tailwind, Lucide, Leaflet). Rien ici ne dépend du réseau ANaTT.
const CORE_RESOURCES = [
  "/",
  "/index.html",
  "/js/auth-api.js",
  "/manifest.json",
  "/images/favicon/favicon-16x16.png",
  "/images/favicon/favicon-32x32.png",
  "/images/favicon/apple-touch-icon.png",
  "/images/favicon/android-chrome-192x192.png",
  "/images/favicon/android-chrome-512x512.png",
  "/images/logo/launchericon-192x192.png",
  "/images/logo/launchericon-512x512.png",
  "/images/baner-oracle.png",
  "https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700;800&display=swap",
  "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css",
  "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js",
  "https://cdn.tailwindcss.com",
  "https://unpkg.com/lucide@latest"
];

self.addEventListener("install", (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(SHELL_CACHE);
    // Chaque ressource est mise en cache indépendamment : une seule CDN
    // indisponible ne doit jamais faire échouer toute l'installation.
    await Promise.allSettled(CORE_RESOURCES.map(async (url) => {
      try {
        const isCrossOrigin = url.startsWith("http") && !url.startsWith(self.location.origin);
        const response = await fetch(url, isCrossOrigin ? { mode: "no-cors" } : {});
        if (response.ok || response.type === "opaque") await cache.put(url, response);
      } catch (_) {
        // Best effort par conception ; les autres ressources continuent.
      }
    }));
    await self.skipWaiting();
  })());
});

self.addEventListener("activate", (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((key) => key !== SHELL_CACHE && key !== RUNTIME_CACHE).map((key) => caches.delete(key)));
    await self.clients.claim();
  })());
});

function isApiRequest(url) {
  return url.pathname.startsWith("/api/");
}

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") return;
  const url = new URL(event.request.url);

  // Les appels /api/* (proxy de recherche châssis) ne sont JAMAIS mis en
  // cache : chaque recherche doit interroger le réseau. Un ancien résultat
  // en cache serait une donnée fausse pour un nouveau châssis recherché.
  // Hors ligne, l'app gère elle-même la file d'attente (outbox) — on
  // renvoie juste une erreur JSON propre plutôt que la coquille HTML.
  if (isApiRequest(url)) {
    event.respondWith(
      fetch(event.request).catch(() => new Response(
        JSON.stringify({ status: "error", message: "Hors ligne — recherche mise en attente." }),
        { status: 503, headers: { "Content-Type": "application/json; charset=utf-8" } }
      ))
    );
    return;
  }

  // Navigation (ouverture/rechargement de l'app) : réseau d'abord pour
  // avoir la dernière version déployée, secours sur le cache hors ligne.
  if (event.request.mode === "navigate") {
    event.respondWith((async () => {
      try {
        const fresh = await fetch(event.request);
        const cache = await caches.open(SHELL_CACHE);
        cache.put("/index.html", fresh.clone());
        return fresh;
      } catch (_) {
        const cache = await caches.open(SHELL_CACHE);
        return (await cache.match("/index.html")) || (await cache.match("/")) ||
          new Response("Oracle hors ligne", { status: 503, headers: { "Content-Type": "text/plain; charset=utf-8" } });
      }
    })());
    return;
  }

  // Tout le reste (CSS, JS, polices, icônes, librairies CDN) : cache
  // d'abord pour un chargement instantané même en mode avion, avec mise
  // à jour silencieuse en arrière-plan dès que le réseau est disponible.
  event.respondWith((async () => {
    const cached = await caches.match(event.request);
    const networkUpdate = fetch(event.request).then(async (response) => {
      if (response.ok || response.type === "opaque") {
        const cache = await caches.open(RUNTIME_CACHE);
        cache.put(event.request, response.clone());
      }
      return response;
    }).catch(() => null);

    if (cached) {
      networkUpdate; // rafraîchit le cache sans bloquer la réponse
      return cached;
    }
    return (await networkUpdate) || new Response("Oracle hors ligne", { status: 503, headers: { "Content-Type": "text/plain; charset=utf-8" } });
  })());
});

// Demande aux onglets ouverts de vider leur file d'attente
// IndexedDB/localStorage dès qu'un créneau de synchronisation existe.
self.addEventListener("sync", (event) => {
  if (event.tag === "oracle-sync") {
    event.waitUntil(self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((clients) => clients.forEach((client) => client.postMessage({ type: "ORACLE_SYNC_NOW" }))));
  }
});